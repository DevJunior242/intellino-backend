<?php

namespace App\Http\Controllers;

use App\Models\PricingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StorePricingPlanRequest;

class PricingPlanController extends Controller
{
    public function index(Request $request)
    {
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;

        Log::info('clubId', ['clubId' => $clubId]);
        $plans = PricingPlan::with('paymentCategory')
            ->where('club_id', $clubId)
            ->get();
        return response()->json($plans);
    }

    public function store(StorePricingPlanRequest $request)
    {
        $plan = PricingPlan::create($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Tarif ajouté au catalogue',
            'plan' => $plan
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $plan = PricingPlan::where('id', $id)
            ->where('club_id', $request->validated_club_id)
            ->firstOrFail();

        if ($plan->paiements()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce tarif car il est utilisé dans des historiques de paiements. Veuillez plutôt le désactiver.'
            ], 422);
        }
        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Tarif supprimé']);
    }
}
