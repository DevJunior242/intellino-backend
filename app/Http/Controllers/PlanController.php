<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use App\Http\Requests\StorePlanRequest;

class PlanController extends Controller
{

    public function getPlans()
    {
        $plans = Plan::all();
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
}
