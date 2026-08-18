<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Saison;
use App\Models\Licence;
use App\Models\Affiliation;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StageRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Unifie les anciens LicencePaymentController, AffiliationPaymentController,
 * StagePaymentController, ExamenPaymentController — même machine à états
 * (pending -> declared -> paid) pour les 4 types de paiement club -> Ligue/
 * Fédération (payable_type: licence_lot | affiliation | stage | examen).
 */
class TransactionController extends Controller
{
    /**
     * Liste des transactions du club connecté, tous statuts confondus.
     * Utilisé pour la section "Paiements en attente" côté dashboard Club.
     * Filtre optionnel ?payable_type= pour ne cibler qu'un type.
     */
    public function mesLots(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $transactions = Transaction::with(['paymentMethod', 'items'])
            ->where('club_id', $activeId)
            ->when($request->filled('payable_type'), function ($q) use ($request) {
                $q->where('payable_type', $request->input('payable_type'));
            })
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Statut d'affiliation du club connecté pour la saison active de sa
     * fédération de rattachement. Utilisé pour l'alerte du dashboard Club :
     * si "requise" est vrai, le club doit régler/redéclarer sa cotisation.
     */
    public function monStatutAffiliation(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $club = Club::find($activeId);
        $league = $club ? \App\Models\League::find($club->league_id) : null;

        if (!$league || !$league->federation_id) {
            return response()->json([
                'success' => true,
                'data' => [
                    'requise' => false,
                    'message' => 'Votre club n\'est rattaché à aucune ligue/fédération pour le moment.',
                ],
            ]);
        }

        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $league->federation_id)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonActive) {
            return response()->json([
                'success' => true,
                'data' => [
                    'requise' => false,
                    'message' => 'Aucune saison active définie par votre fédération.',
                ],
            ]);
        }

        $affiliation = Affiliation::where('federation_id', $league->federation_id)
            ->where('saison_id', $saisonActive->id)
            ->first();

        if (!$affiliation) {
            return response()->json([
                'success' => true,
                'data' => [
                    'requise' => false,
                    'message' => 'Votre fédération n\'a pas encore défini de tarif d\'affiliation pour cette saison.',
                ],
            ]);
        }

        $transaction = Transaction::firstOrCreate(
            [
                'payable_type' => Transaction::PAYABLE_AFFILIATION,
                'payable_id'   => $affiliation->id,
                'club_id'      => $activeId,
            ],
            [
                'organisateur_id'   => $affiliation->federation_id,
                'organisateur_type' => 'Federation',
                'saison_id'         => $affiliation->saison_id,
                'amount'            => $affiliation->cotisation,
                'status'            => 'pending',
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'requise' => $transaction->status !== 'paid',
                'saison' => $saisonActive->libele,
                'payment' => $transaction,
            ],
        ]);
    }

    /**
     * Le club déclare avoir effectué le paiement. Choisit le moyen de
     * paiement de l'organisateur receveur, indique son numéro d'envoi et la
     * référence. Statut passe à 'declared' en attente de vérification
     * manuelle par l'organisateur.
     */
    public function declarer(Request $request, Transaction $transaction)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club' || $transaction->club_id !== $activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à déclarer ce paiement.'
            ], 403);
        }

        if ($transaction->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà confirmé.'
            ], 422);
        }

        $validated = $request->validate([
            'payment_method_id' => 'required|uuid|exists:payment_methods,id',
            'sender_number'     => 'required|string|max:50',
            'transaction_id'    => 'nullable|string|max:255',
        ]);

        // Vérifie que le moyen de paiement appartient bien à l'organisateur receveur
        $method = PaymentMethod::where('id', $validated['payment_method_id'])
            ->where('organisateur_id', $transaction->organisateur_id)
            ->where('organisateur_type', $transaction->organisateur_type)
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => 'Ce moyen de paiement n\'appartient pas à l\'organisateur de ce paiement.'
            ], 422);
        }

        $transaction->update([
            'payment_method_id' => $method->id,
            'sender_number'     => $validated['sender_number'],
            'transaction_id'    => $validated['transaction_id'] ?? null,
            'status'            => 'declared',
            'declared_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement déclaré. Il sera vérifié par l\'organisateur avant confirmation.',
            'data'    => $transaction->load('paymentMethod'),
        ]);
    }

    /**
     * L'organisateur confirme un paiement déclaré par le club. Les effets de
     * bord dépendent du type (payable_type) : passage des licences au statut
     * Payé + génération du numéro pour un lot de licences, passage des
     * inscriptions au statut payé pour un stage — rien de plus pour une
     * affiliation ou un examen (comme dans les anciens contrôleurs séparés).
     */
    public function confirmer(Request $request, Transaction $transaction)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($transaction->organisateur_id !== $activeId || $transaction->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        if ($transaction->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà confirmé.'
            ], 422);
        }

        if ($transaction->payable_type === Transaction::PAYABLE_LICENCE_LOT) {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'paid']);

                // Verrou pessimiste sur la saison : sérialise les confirmations
                // concurrentes pour garantir l'unicité du numéro généré.
                $saison = Saison::where('id', $transaction->saison_id)->lockForUpdate()->first();

                $licenceIds = TransactionItem::where('transaction_id', $transaction->id)
                    ->where('itemable_type', TransactionItem::ITEMABLE_LICENCE)
                    ->pluck('itemable_id');

                $licences = Licence::whereIn('id', $licenceIds)
                    ->where('status', Licence::STATUS_EN_ATTENTE)
                    ->get();

                foreach ($licences as $licence) {
                    $licence->update([
                        'status' => Licence::STATUS_PAYE,
                        'numero' => $licence->numero ?? LicenceController::genererNumero($saison),
                    ]);
                }
            });
        } elseif ($transaction->payable_type === Transaction::PAYABLE_STAGE) {
            $transaction->update(['status' => 'paid']);

            $registrationIds = TransactionItem::where('transaction_id', $transaction->id)
                ->where('itemable_type', TransactionItem::ITEMABLE_STAGE_REGISTRATION)
                ->pluck('itemable_id');

            StageRegistration::whereIn('id', $registrationIds)->update(['payment_status' => 'paid']);
        } else {
            $transaction->update(['status' => 'paid']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé.',
            'data'    => $transaction,
        ]);
    }

    /**
     * L'organisateur rejette une déclaration invalide. Remet la transaction
     * à 'pending' pour que le club re-déclare.
     */
    public function rejeter(Request $request, Transaction $transaction)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($transaction->organisateur_id !== $activeId || $transaction->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $transaction->update([
            'status'      => 'pending',
            'declared_at' => null,
        ]);

        if ($transaction->payable_type === Transaction::PAYABLE_STAGE) {
            $registrationIds = TransactionItem::where('transaction_id', $transaction->id)
                ->where('itemable_type', TransactionItem::ITEMABLE_STAGE_REGISTRATION)
                ->pluck('itemable_id');

            StageRegistration::whereIn('id', $registrationIds)->update(['payment_status' => 'pending']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Déclaration rejetée. Le club doit re-déclarer son paiement.',
            'data'    => $transaction,
        ]);
    }

    /**
     * Liste des transactions pour l'organisateur connecté (Club/Ligue/
     * Federation), avec filtres optionnels ?status=, ?payable_type=,
     * ?payable_id=.
     */
    public function index(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $transactions = Transaction::with(['club', 'paymentMethod', 'items'])
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payable_type'), fn ($q) => $q->where('payable_type', $request->input('payable_type')))
            ->when($request->filled('payable_id'), fn ($q) => $q->where('payable_id', $request->input('payable_id')))
            ->latest('declared_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Transactions déclarées en attente de vérification pour l'organisateur
     * connecté, tous types confondus (licence/affiliation/stage/examen).
     */
    public function paiementsAVerifier(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $transactions = Transaction::with(['club', 'paymentMethod', 'items'])
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('status', 'declared')
            ->when($request->filled('payable_type'), fn ($q) => $q->where('payable_type', $request->input('payable_type')))
            ->latest('declared_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    /**
     * Statistiques financières pour l'organisateur connecté : total
     * attendu, encaissé, en attente. Filtre optionnel ?payable_type= pour
     * une répartition par type.
     */
    public function statistiques(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $base = Transaction::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->when($request->filled('payable_type'), fn ($q) => $q->where('payable_type', $request->input('payable_type')));

        $totalAttendu   = $base->clone()->sum('amount');
        $totalEncaisse  = $base->clone()->where('status', 'paid')->sum('amount');
        $totalEnAttente = $totalAttendu - $totalEncaisse;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_attendu'    => round($totalAttendu, 2),
                'total_encaisse'   => round($totalEncaisse, 2),
                'total_en_attente' => round($totalEnAttente, 2),
            ],
        ]);
    }

    /**
     * Recettes encaissées par mois, 6 derniers mois, pour l'organisateur
     * connecté. Filtre optionnel ?payable_type=.
     */
    public function statistiquesMensuelles(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier l\'organisateur connecté.'
            ], 403);
        }

        $stats = Transaction::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(6))
            ->when($request->filled('payable_type'), fn ($q) => $q->where('payable_type', $request->input('payable_type')))
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%b") as month, MIN(created_at) as sort_date')
            ->groupBy('month')
            ->orderBy('sort_date', 'asc')
            ->get()
            ->map(fn ($row) => ['month' => $row->month, 'total' => (float) $row->total]);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
