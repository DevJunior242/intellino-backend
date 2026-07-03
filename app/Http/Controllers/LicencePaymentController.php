<?php

namespace App\Http\Controllers;

use App\Models\Licence;
use App\Models\Saison;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\LicencePayment;
use Illuminate\Support\Facades\DB;

class LicencePaymentController extends Controller
{
    /**
     * Liste des lots de paiement du club connecté, tous statuts confondus,
     * pour la saison active. Utilisé pour afficher la section
     * "Paiements en attente" côté dashboard Club.
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

        $saisonActive = \App\Models\Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $lots = LicencePayment::with('items.licence.student')
            ->where('club_id', $activeId)
            ->where('saison_id', $saisonActive->id)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $lots,
        ]);
    }

    /**
     * Le club déclare avoir effectué le paiement d'un lot de licences.
     * Choisit le moyen de paiement de la fédération, indique son numéro
     * d'envoi et la référence. Statut passe à 'declared' en attente
     * de vérification manuelle par la fédération.
     */
    public function declarer(Request $request, LicencePayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club' || $payment->club_id !== $activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à déclarer ce paiement.'
            ], 403);
        }

        if ($payment->status === 'paid') {
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

        // Vérifie que le moyen de paiement appartient bien à la fédération
        $method = PaymentMethod::where('id', $validated['payment_method_id'])
            ->where('organisateur_id', $payment->federation_id)
            ->where('organisateur_type', 'Federation')
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => 'Ce moyen de paiement n\'appartient pas à votre fédération.'
            ], 422);
        }

        $payment->update([
            'payment_method_id' => $method->id,
            'payment_method'    => $method->provider,
            'sender_number'     => $validated['sender_number'],
            'transaction_id'    => $validated['transaction_id'] ?? null,
            'status'            => 'declared',
            'declared_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement déclaré. Il sera vérifié par la fédération avant confirmation.',
            'data'    => $payment,
        ]);
    }

    /**
     * La fédération confirme un paiement déclaré par le club.
     * Passe aussi les licences du lot au statut Payé.
     */
    public function confirmer(Request $request, LicencePayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($payment->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement est déjà confirmé.'
            ], 422);
        }

        $payment->update(['status' => 'paid']);

        // Les licences de ce lot passent au statut Payé, ET reçoivent
        // leur matricule à ce moment précis — jamais avant.
        $licenceIds = \App\Models\LicencePaymentItem::where('licence_payment_id', $payment->id)
            ->pluck('licence_id');

        DB::transaction(function () use ($licenceIds, $payment) {
            // Verrou pessimiste sur la saison : sérialise les confirmations
            // concurrentes pour garantir l'unicité du numéro généré.
            $saison = Saison::where('id', $payment->saison_id)->lockForUpdate()->first();

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

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé.',
            'data'    => $payment,
        ]);
    }

    /**
     * La fédération rejette une déclaration invalide.
     * Remet le lot à 'pending' pour que le club re-déclare.
     */
    public function rejeter(Request $request, LicencePayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($payment->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $payment->update([
            'status'      => 'pending',
            'declared_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Déclaration rejetée. Le club doit re-déclarer son paiement.',
            'data'    => $payment,
        ]);
    }
    /**
     * Recalcule et synchronise le montant dû par un club pour ses licences
     * d'une saison/fédération donnée, en fonction du nombre RÉEL de licences
     * actuellement enregistrées (somme des tarifs au moment de l'achat).
     *
     * Ne repasse jamais un statut déjà 'paid' à 'pending' automatiquement.
     */
    public static function syncMontant(string $saisonId, string $federationId, string $clubId): LicencePayment
    {
        $montantTotal = Licence::where('saison_id', $saisonId)
            ->where('federation_id', $federationId)
            ->where('club_id', $clubId)
            ->sum('montant_paye');

        $payment = LicencePayment::firstOrNew([
            'saison_id'     => $saisonId,
            'federation_id' => $federationId,
            'club_id'       => $clubId,
        ]);

        $payment->amount = $montantTotal;

        if (!$payment->exists) {
            $payment->status = 'pending';
        }

        $payment->save();

        return $payment;
    }

    /**
     * Liste des paiements de licences pour la fédération connectée,
     * sur la saison active. Filtre optionnel par statut (?status=declared
     * pour la section "Paiements à vérifier").
     */
    public function index(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter ces paiements.'
            ], 403);
        }

        $saisonActive = \App\Models\Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $query = LicencePayment::with(['club', 'paymentMethod', 'items.licence.student'])
            ->where('federation_id', $activeId)
            ->where('saison_id', $saisonActive->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->latest('declared_at')->get();

        return response()->json([
            'success' => true,
            'data'    => $payments,
        ]);
    }

    /**
     * Statistiques financières pour la fédération : total attendu,
     * encaissé, en attente, sur la saison active.
     */
    public function statistiques(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter ces statistiques.'
            ], 403);
        }

        $saisonActive = \App\Models\Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json(['message' => 'Aucune saison active trouvée'], 422);
        }

        $base = LicencePayment::where('federation_id', $activeId)
            ->where('saison_id', $saisonActive->id);

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
     * Recettes licences encaissées par mois, 6 derniers mois, pour la fédération connectée.
     * Utilisé par le graphique du dashboard Fédération.
     */
    public function statistiquesMensuelles(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter ces statistiques.'
            ], 403);
        }

        $stats = LicencePayment::where('federation_id', $activeId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%b") as month, MIN(created_at) as sort_date')
            ->groupBy('month')
            ->orderBy('sort_date', 'asc')
            ->get()
            ->map(fn($row) => ['month' => $row->month, 'total' => (float) $row->total]);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }
}
