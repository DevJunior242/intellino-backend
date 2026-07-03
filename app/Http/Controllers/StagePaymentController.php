<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\StagePayment;
use Illuminate\Http\Request;
use App\Models\StageRegistration;

class StagePaymentController extends Controller
{
    /**
     * Liste des lots de paiement du club connecté pour ses stages,
     * tous statuts confondus. Utilisé pour la section "Paiements en attente"
     * côté dashboard Club. Fonctionne quel que soit le type d'organisateur du stage.
     */
    public function mesLotsClub(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || $activeType !== 'Club') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'identifier le club connecté.'
            ], 403);
        }

        $lots = StagePayment::with(['stage', 'paymentMethod', 'items.stageRegistration.user'])
            ->where('club_id', $activeId)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $lots,
        ]);
    }

    /**
     * Le club déclare avoir effectué le paiement d'un stage.
     * Fonctionne quel que soit le type d'organisateur du stage
     * (Federation, Ligue ou Club) — pas de hardcode.
     */
    public function declarer(Request $request, StagePayment $payment)
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

        // Vérifie que le moyen de paiement appartient bien à l'organisateur
        // du stage — fonctionne pour tout type (Ligue, Federation, Club)
        $stage = $payment->stage;
        $method = \App\Models\PaymentMethod::where('id', $validated['payment_method_id'])
            ->where('organisateur_id', $stage->organisateur_id)
            ->where('organisateur_type', $stage->organisateur_type)
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => 'Ce moyen de paiement n\'appartient pas à l\'organisateur de ce stage.'
            ], 422);
        }

        $payment->update([
            'payment_method_id' => $method->id,
            'sender_number'     => $validated['sender_number'],
            'transaction_id'    => $validated['transaction_id'] ?? null,
            'status'            => 'declared',
            'declared_at'       => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement déclaré. Il sera vérifié par l\'organisateur avant confirmation.',
            'data'    => $payment->load('paymentMethod'),
        ]);
    }

    /**
     * Recalcule et synchronise le montant dû par un club pour un stage.
     * Appelée automatiquement après chaque inscription ou désinscription.
     */
    public static function syncMontant(Stage $stage, string $clubId): StagePayment
    {
        $totalParticipants = StageRegistration::where('stage_id', $stage->id)
            ->where('club_id', $clubId)
            ->count();

        $payment = StagePayment::firstOrNew([
            'stage_id' => $stage->id,
            'club_id'  => $clubId,
        ]);

        $payment->amount = $stage->price * $totalParticipants;

        if (!$payment->exists) {
            $payment->status = 'pending';
        }

        $payment->save();

        return $payment;
    }

    /**
     * Liste des paiements pour un stage donné, vue organisateur.
     * Filtre optionnel par statut (?status=declared).
     * Fonctionne pour tout type d'organisateur.
     */
    public function parStage(Request $request, Stage $stage)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à consulter ces paiements.'
            ], 403);
        }

        $query = StagePayment::with(['club', 'paymentMethod'])
            ->where('stage_id', $stage->id);

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
     * Paiements déclarés pour TOUS les stages de l'organisateur connecté.
     * Utilisé pour la section "Paiements à vérifier" côté dashboard.
     * Fonctionne pour tout type d'organisateur (Federation, Ligue, Club).
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

        $payments = StagePayment::with(['club', 'paymentMethod', 'stage'])
            ->join('stages', 'stages.id', '=', 'stage_payments.stage_id')
            ->where('stages.organisateur_id', $activeId)
            ->where('stages.organisateur_type', $activeType)
            ->where('stage_payments.status', 'declared')
            ->select('stage_payments.*')
            ->latest('stage_payments.declared_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $payments,
        ]);
    }

    /**
     * L'organisateur confirme un paiement déclaré par le club.
     * Fonctionne pour tout type d'organisateur.
     */
    public function confirmer(Request $request, StagePayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $stage = $payment->stage;

        if (!$stage || $stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
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

        StageRegistration::whereIn('id', $payment->items()->pluck('stage_registration_id'))
            ->update(['payment_status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé.',
            'data'    => $payment,
        ]);
    }

    /**
     * L'organisateur rejette une déclaration invalide.
     * Fonctionne pour tout type d'organisateur.
     */
    public function rejeter(Request $request, StagePayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $stage = $payment->stage;

        if (!$stage || $stage->organisateur_id !== $activeId || $stage->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.'
            ], 403);
        }

        $payment->update([
            'status'      => 'pending',
            'declared_at' => null,
        ]);

        StageRegistration::whereIn('id', $payment->items()->pluck('stage_registration_id'))
            ->update(['payment_status' => 'pending']);

        return response()->json([
            'success' => true,
            'message' => 'Déclaration rejetée. Le club doit re-déclarer son paiement.',
            'data'    => $payment,
        ]);
    }

    /**
     * Statistiques financières pour l'organisateur connecté.
     * Fonctionne pour tout type d'organisateur (Federation, Ligue, Club).
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

        $base = StagePayment::query()
            ->join('stages', 'stages.id', '=', 'stage_payments.stage_id')
            ->where('stages.organisateur_id', $activeId)
            ->where('stages.organisateur_type', $activeType);

        $totalAttendu   = $base->clone()->sum('stage_payments.amount');
        $totalEncaisse  = $base->clone()->where('stage_payments.status', 'paid')->sum('stage_payments.amount');
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
     * Recettes stages encaissées par mois, 6 derniers mois, pour l'organisateur connecté.
     * Utilisé par le graphique du dashboard Ligue/Fédération.
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

        $stats = StagePayment::query()
            ->join('stages', 'stages.id', '=', 'stage_payments.stage_id')
            ->where('stages.organisateur_id', $activeId)
            ->where('stages.organisateur_type', $activeType)
            ->where('stage_payments.status', 'paid')
            ->where('stage_payments.created_at', '>=', now()->subMonths(6))
            ->selectRaw('SUM(stage_payments.amount) as total, DATE_FORMAT(stage_payments.created_at, "%b") as month, MIN(stage_payments.created_at) as sort_date')
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
