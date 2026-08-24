<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Requests\StorePlanRequest;

class PlanController extends Controller
{
    private function assertSuperAdmin(Request $request)
    {
        if ($request->attributes->get('role') !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission d\'accéder à cette page',
            ], 403);
        }
        return null;
    }

    public function getPlans()
    {
        $plans = Plan::orderBy('organisateur_type')->orderBy('min_users')->get();
        return response()->json([
            'plans' => $plans,
        ]);
    }
    public function storePlan(StorePlanRequest $request)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $plan = Plan::create($request->validated());
        return response()->json([
            'success' => true,
            'plan' => $plan,
            'message' => 'Plan a été créé avec succès',
        ], 201);
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('plans', 'name')->ignore($plan->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'organisateur_type' => ['sometimes', 'required', 'in:Club,Ligue,Federation'],
            'min_users' => ['sometimes', 'required', 'integer', 'min:0'],
            'max_users' => ['sometimes', 'nullable', 'integer', 'gt:min_users'],
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'plan' => $plan->fresh(),
            'message' => 'Palier mis à jour.',
        ]);
    }

    public function destroyPlan(Request $request, Plan $plan)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        // Suppression douce : les abonnements déjà créés sur ce palier
        // gardent leur historique (montant déjà figé sur la souscription,
        // nom du palier toujours affichable via Subscription::plan()).
        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Palier supprimé.',
        ]);
    }

    // Réduction appliquée au prix annuel (mensuel × 12 × (1 - %)) — calculée
    // à l'affichage plutôt que stockée par plan, pour qu'un changement de
    // réglage s'applique instantanément à tous les paliers.
    public function getAnnualDiscount()
    {
        return response()->json([
            'percent' => (float) PlatformSetting::get('annual_discount_percent', 0),
        ]);
    }

    public function updateAnnualDiscount(Request $request)
    {
        if ($refus = $this->assertSuperAdmin($request)) return $refus;

        $validated = $request->validate([
            'percent' => 'required|numeric|min:0|max:100',
        ]);

        PlatformSetting::set('annual_discount_percent', $validated['percent']);

        return response()->json([
            'success' => true,
            'message' => 'Réduction annuelle mise à jour.',
            'percent' => (float) $validated['percent'],
        ]);
    }
}
