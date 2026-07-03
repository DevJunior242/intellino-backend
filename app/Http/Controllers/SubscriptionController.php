<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\SubscribedRequest;

class SubscriptionController extends Controller
{

    public function index(Request $request)
    {
        $user = auth()->user();
        $clubId = $request->attributes->get('club_id');


        $role = $request->attributes->get('role');


        if ($role === 'super_admin') {

            $subscriptions = \App\Models\Subscription::with(['club', 'plan'])
                ->latest()
                ->paginate(10);

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions
            ]);
        }




        $subscriptions = \App\Models\Subscription::with(['club', 'plan'])
            ->where('club_id', $clubId)
            ->latest()
            ->paginate(8);

        return response()->json([
            'success' => true,
            'subscriptions' => $subscriptions
        ]);
    }

    public function store(SubscribedRequest $request)
    {
        $clubId = $request->validated()['club_id'] ?? null;
        if (!$clubId) {
            return response()->json(['message' => 'Club ID manquant dans la requête'], 422);
        }
        $subscription = Subscription::where('club_id', $clubId)
            ->where('plan_id', $request->plan_id)
            ->whereIn('status', ['pending_payment', 'paid'])
            ->first();

        if ($subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un abonnement pour ce type de plan',
            ], 400);
        }
        $validated = $request->validated();
        $start = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : now();

        $end = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : $start->copy()->addMonth();

        $subscription = Subscription::create([
            'plan_id' => $validated['plan_id'],
            'club_id' => $clubId,
            'start_date' => $start,
            'end_date' => $end,
        ]);
        $paymentUrl = config('services.stripe.payment_url') . $subscription->id;
        return response()->json([
            'success' => true,
            'message' => 'votre abonnement a été créé avec succès.vous serez redirigé vers la page de paiement',
            'subscription' => $subscription,
            'payment_url' => $paymentUrl,
        ], 201);
    }


    public function show(Request $request)
    {
        $user = auth()->user();
        $clubId = $request->attributes->get('club_id');
        Log::info('club_id', ['clubId' => $clubId]);
        $role = $request->attributes->get('role');
        //afficher les abonnements actif pour le club
        if ($role === 'super_admin') {
            $subscriptions = Subscription::with(['club', 'plan'])
                ->where('status', 'paid')
                ->latest()
                ->paginate(2);
            return response()->json([
                'success' => true,
                'active_subscriptions' => $subscriptions
            ]);
        }
        //afficher les abonnements actif pour le club
        
        $subscriptions = Subscription::with(['club', 'plan'])
            ->where('club_id', $clubId)
            ->where('status', 'paid')
            ->latest()
            ->paginate(10);
        return response()->json([
            'success' => true,
            'active_subscriptions' => $subscriptions
        ]);
    }
}
