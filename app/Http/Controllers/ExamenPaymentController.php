<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use App\Models\ExamenPayment;

class ExamenPaymentController extends Controller
{
    /**
     * Liste des lots de paiement du club connecté pour ses examens,
     * tous statuts confondus. Utilisé pour la section "Paiements en attente"
     * côté dashboard Club. Fonctionne quel que soit le type d'organisateur de l'examen.
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

        $lots = ExamenPayment::with(['examen', 'paymentMethod', 'items.examenCandidat.student'])
            ->where('club_id', $activeId)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $lots,
        ]);
    }

    /**
     * Le club déclare avoir effectué le paiement d'un examen.
     * Fonctionne quel que soit le type d'organisateur de l'examen
     * (Federation, Ligue ou Club) — pas de hardcode.
     */
    public function declarer(Request $request, ExamenPayment $payment)
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
        // de l'examen — fonctionne pour tout type (Ligue, Federation, Club)
        $examen = $payment->examen;
        $method = PaymentMethod::where('id', $validated['payment_method_id'])
            ->where('organisateur_id', $examen->organisateur_id)
            ->where('organisateur_type', $examen->organisateur_type)
            ->where('is_active', true)
            ->first();

        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => 'Ce moyen de paiement n\'appartient pas à l\'organisateur de cet examen.'
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
     * Liste des paiements pour un examen donné, vue organisateur.
     * Filtre optionnel par statut (?status=declared).
     * Fonctionne pour tout type d'organisateur.
     */
    public function parExamen(Request $request, Examen $examen)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($examen->organisateur_id !== $activeId || $examen->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à consulter ces paiements.'
            ], 403);
        }

        $query = ExamenPayment::with(['club', 'paymentMethod'])
            ->where('examen_id', $examen->id);

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
     * Paiements déclarés pour TOUS les examens de l'organisateur connecté.
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

        $payments = ExamenPayment::with(['club', 'paymentMethod', 'examen', 'items.examenCandidat.student'])
            ->join('examens', 'examens.id', '=', 'examen_payments.examen_id')
            ->where('examens.organisateur_id', $activeId)
            ->where('examens.organisateur_type', $activeType)
            ->where('examen_payments.status', 'declared')
            ->select('examen_payments.*')
            ->latest('examen_payments.declared_at')
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
    public function confirmer(Request $request, ExamenPayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $examen = $payment->examen;

        if (!$examen || $examen->organisateur_id !== $activeId || $examen->organisateur_type !== $activeType) {
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
    public function rejeter(Request $request, ExamenPayment $payment)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $examen = $payment->examen;

        if (!$examen || $examen->organisateur_id !== $activeId || $examen->organisateur_type !== $activeType) {
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

        $base = ExamenPayment::query()
            ->join('examens', 'examens.id', '=', 'examen_payments.examen_id')
            ->where('examens.organisateur_id', $activeId)
            ->where('examens.organisateur_type', $activeType);

        $totalAttendu   = $base->clone()->sum('examen_payments.amount');
        $totalEncaisse  = $base->clone()->where('examen_payments.status', 'paid')->sum('examen_payments.amount');
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
     * Recettes examens encaissées par mois, 6 derniers mois, pour l'organisateur connecté.
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

        $stats = ExamenPayment::query()
            ->join('examens', 'examens.id', '=', 'examen_payments.examen_id')
            ->where('examens.organisateur_id', $activeId)
            ->where('examens.organisateur_type', $activeType)
            ->where('examen_payments.status', 'paid')
            ->where('examen_payments.created_at', '>=', now()->subMonths(6))
            ->selectRaw('SUM(examen_payments.amount) as total, DATE_FORMAT(examen_payments.created_at, "%b") as month, MIN(examen_payments.created_at) as sort_date')
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
