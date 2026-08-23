<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Http\Controllers\Concerns\ResolvesEffectifOrganisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Abonnements Intellino (Club/Ligue/Fédération payant leur propre usage de
 * la plateforme) — flux déclarer/confirmer, même logique que
 * TransactionController pour les licences/stages/examens, plutôt que la
 * redirection Stripe jamais configurée qui existait avant.
 */
class SubscriptionController extends Controller
{
    use ResolvesEffectifOrganisation;

    private function estSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->isSuperAdmin();
    }

    public function index(Request $request)
    {
        if ($this->estSuperAdmin($request)) {
            $subscriptions = Subscription::with(['organisateur', 'plan'])
                ->latest()
                ->paginate(10);

            return response()->json(['success' => true, 'subscriptions' => $subscriptions]);
        }

        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $subscriptions = Subscription::with(['plan', 'payments' => function ($q) {
                $q->latest();
            }])
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->latest()
            ->paginate(8);

        return response()->json(['success' => true, 'subscriptions' => $subscriptions]);
    }

    /**
     * Crée un abonnement pour l'organisation connectée, sur le palier
     * qu'elle a choisi — pas nécessairement celui déduit de son effectif
     * actuel (une petite structure peut vouloir prendre de l'avance).
     */
    public function store(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !in_array($activeType, ['Club', 'Ligue', 'Federation'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Impossible d'identifier l'organisation connectée.",
            ], 403);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        if ($plan->organisateur_type !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => "Ce palier ne correspond pas au type de votre organisation.",
            ], 422);
        }

        $existant = Subscription::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_PAID])
            ->latest()
            ->first();

        if ($existant) {
            if ($existant->plan_id === $plan->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà un abonnement en cours pour ce palier.',
                ], 422);
            }

            if ($existant->status === Subscription::STATUS_PENDING
                && $existant->payments()->where('status', SubscriptionPayment::STATUS_DECLARED)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Un paiement pour votre abonnement actuel (\"{$existant->plan?->name}\") est en cours de vérification. Attendez sa confirmation ou son rejet avant de changer de palier.",
                ], 422);
            }

            // On change de palier : l'ancien abonnement (encore impayé, ou
            // payé mais qu'on remplace) est marqué "cancelled" plutôt que
            // supprimé, pour garder l'historique visible dans "Mon abonnement".
            $existant->update(['status' => Subscription::STATUS_CANCELLED]);
        }

        $start = now();
        $subscription = Subscription::create([
            'organisateur_id' => $activeId,
            'organisateur_type' => $activeType,
            'plan_id' => $plan->id,
            'amount' => $plan->amount,
            'start_date' => $start,
            'end_date' => $start->copy()->addMonth(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Abonnement créé. Déclarez votre paiement pour le faire vérifier.',
            'subscription' => $subscription->load('plan'),
        ], 201);
    }

    /**
     * L'organisation déclare avoir envoyé le paiement de son abonnement.
     */
    public function declarer(Request $request, Subscription $subscription)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($subscription->organisateur_id !== $activeId || $subscription->organisateur_type !== $activeType) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if ($subscription->status === Subscription::STATUS_PAID) {
            return response()->json(['success' => false, 'message' => 'Cet abonnement est déjà payé.'], 422);
        }

        if ($subscription->status === Subscription::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cet abonnement a été remplacé par un changement de palier. Rechargez la page.',
            ], 422);
        }

        if ($subscription->payments()->where('status', SubscriptionPayment::STATUS_DECLARED)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Une déclaration est déjà en attente de vérification pour cet abonnement.',
            ], 422);
        }

        $validated = $request->validate([
            'platform_payment_method_id' => ['required', 'uuid', 'exists:platform_payment_methods,id'],
            'sender_number' => ['required', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'platform_payment_method_id' => $validated['platform_payment_method_id'],
            'sender_number' => $validated['sender_number'] ?? null,
            'transaction_id' => $validated['transaction_id'],
            'amount' => $subscription->amount,
            'status' => SubscriptionPayment::STATUS_DECLARED,
            'declared_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement déclaré. Il sera vérifié sous peu.',
            'data' => $payment,
        ], 201);
    }

    public function paiementsAVerifier(Request $request)
    {
        if (!$this->estSuperAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $paiements = SubscriptionPayment::with(['subscription.organisateur', 'subscription.plan', 'platformPaymentMethod'])
            ->where('status', SubscriptionPayment::STATUS_DECLARED)
            ->latest('declared_at')
            ->get();

        return response()->json(['success' => true, 'data' => $paiements]);
    }

    public function confirmer(Request $request, SubscriptionPayment $subscriptionPayment)
    {
        if (!$this->estSuperAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if ($subscriptionPayment->status !== SubscriptionPayment::STATUS_DECLARED) {
            return response()->json(['success' => false, 'message' => 'Ce paiement a déjà été traité.'], 422);
        }

        DB::transaction(function () use ($subscriptionPayment, $request) {
            $subscriptionPayment->update([
                'status' => SubscriptionPayment::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $request->user()->id,
            ]);

            $subscription = $subscriptionPayment->subscription;
            $start = now();
            $subscription->update([
                'status' => Subscription::STATUS_PAID,
                'start_date' => $subscription->start_date ?? $start,
                'end_date' => $start->copy()->addMonth(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Paiement confirmé, abonnement activé.',
            'data' => $subscriptionPayment->fresh(),
        ]);
    }

    public function rejeter(Request $request, SubscriptionPayment $subscriptionPayment)
    {
        if (!$this->estSuperAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if ($subscriptionPayment->status !== SubscriptionPayment::STATUS_DECLARED) {
            return response()->json(['success' => false, 'message' => 'Ce paiement a déjà été traité.'], 422);
        }

        $subscriptionPayment->update([
            'status' => SubscriptionPayment::STATUS_REJECTED,
            'confirmed_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Déclaration rejetée.',
            'data' => $subscriptionPayment->fresh(),
        ]);
    }

    public function statistiques(Request $request)
    {
        if (!$this->estSuperAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $totalEncaisse = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_CONFIRMED)->sum('amount');
        $totalEnAttente = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_DECLARED)->sum('amount');
        $abonnesActifs = Subscription::where('status', Subscription::STATUS_PAID)->count();

        $parMois = SubscriptionPayment::where('status', SubscriptionPayment::STATUS_CONFIRMED)
            ->selectRaw("DATE_FORMAT(confirmed_at, '%b') as month, SUM(amount) as total")
            ->where('confirmed_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('confirmed_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_encaisse' => $totalEncaisse,
                'total_en_attente' => $totalEnAttente,
                'abonnes_actifs' => $abonnesActifs,
                'par_mois' => $parMois,
            ],
        ]);
    }
}
