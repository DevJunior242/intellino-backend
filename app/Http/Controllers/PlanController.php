<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlanRequest;

class PlanController extends Controller
{

    public function getPlans()
    {
        $plans = Plan::orderBy('organisateur_type')->orderBy('min_users')->get();
        return response()->json([
            'plans' => $plans,
        ]);
    }
    public function storePlan(StorePlanRequest $request)
    {
        $role = $request->attributes->get('role');
        if ($role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission d\'accéder à cette page',
            ], 403);
        }
        $plan = Plan::create($request->validated());
        return response()->json([
            'success' => true,
            'plan' => $plan,
            'message' => 'Plan a été créé avec succès',
        ], 201);
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
        $role = $request->attributes->get('role');
        if ($role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission d\'accéder à cette page',
            ], 403);
        }

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
