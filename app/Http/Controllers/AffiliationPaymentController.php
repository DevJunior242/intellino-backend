<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Saison;
use App\Models\Affiliation;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\AffiliationPayment;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class AffiliationPaymentController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Statut d'affiliation du club connecté pour la saison active de sa
     * fédération de rattachement. Utilisé pour l'alerte du dashboard Club :
     * si "requise" est vrai, le club doit régler/redéclarer sa cotisation.
     */
    public function monStatut(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $club = Club::find($activeId);
        $league = $club ? League::find($club->league_id) : null;

        if (!$league || !$league->federation_id) {
            return response()->json([
                'success' => true,
                'data' => [
                    'requise' => false,
                    'message' => 'Votre club n\'est rattaché à aucune ligue/fédération pour le moment.',
                ],
            ]);
        }

        $saisonActive = $this->saisonActivePour($activeId, $activeType);

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

        $payment = AffiliationPayment::firstOrCreate(
            [
                'affiliation_id' => $affiliation->id,
                'club_id' => $activeId,
            ],
            [
                'federation_id' => $affiliation->federation_id,
                'amount' => $affiliation->cotisation,
                'status' => 'pending',
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'requise' => $payment->status !== 'paid',
                'saison' => $saisonActive->libele,
                'payment' => $payment,
            ],
        ]);
    }

    /**
     * Le club déclare avoir payé sa cotisation d'affiliation. Choisit le
     * moyen de paiement de la fédération, indique son numéro d'envoi et la
     * référence. Statut passe à 'declared' en attente de vérification
     * manuelle par la fédération.
     */
    public function declarer(Request $request, AffiliationPayment $payment)
    {
        $activeId = $request->attributes->get('organisateur_id');
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
                'message' => 'Cette affiliation est déjà confirmée.'
            ], 422);
        }

        $validated = $request->validate([
            'payment_method_id' => 'required|uuid|exists:payment_methods,id',
            'sender_number' => 'required|string|max:50',
            'transaction_id' => 'nullable|string|max:255',
        ]);

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
            'sender_number' => $validated['sender_number'],
            'transaction_id' => $validated['transaction_id'] ?? null,
            'status' => 'declared',
            'declared_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement déclaré. Il sera vérifié par la fédération avant confirmation.',
            'data' => $payment,
        ]);
    }

    /**
     * Liste des cotisations d'affiliation à vérifier pour la fédération
     * connectée, sur la saison active. Filtre optionnel par statut.
     */
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter ces paiements.'
            ], 403);
        }

        $query = AffiliationPayment::with(['club', 'paymentMethod'])
            ->where('federation_id', $activeId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->latest('declared_at')->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * La fédération confirme une cotisation déclarée par le club.
     */
    public function confirmer(Request $request, AffiliationPayment $payment)
    {
        $activeId = $request->attributes->get('organisateur_id');
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
                'message' => 'Cette affiliation est déjà confirmée.'
            ], 422);
        }

        $payment->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Affiliation confirmée.',
            'data' => $payment,
        ]);
    }

    /**
     * La fédération rejette une déclaration invalide : le club doit
     * re-déclarer son paiement.
     */
    public function rejeter(Request $request, AffiliationPayment $payment)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($payment->federation_id !== $activeId || $activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $payment->update([
            'status' => 'pending',
            'declared_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Déclaration rejetée. Le club doit re-déclarer son paiement.',
            'data' => $payment,
        ]);
    }
}
