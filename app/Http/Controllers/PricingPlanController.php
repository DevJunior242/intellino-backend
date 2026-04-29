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
        $activeId = $request->attributes->get('organisateur_id');

        Log::info('activeId', ['activeId' => $activeId]);
        $plans = PricingPlan::with('paymentCategory')
            ->where('club_id', $activeId)
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
        $activeId = $request->attributes->get('organisateur_id');

        $plan = PricingPlan::where('id', $id)
            ->where('club_id', $activeId)
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
