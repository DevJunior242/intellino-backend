<?php

namespace App\Http\Controllers;

use App\Models\Affiliation;

use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreAffiliationRequest;

class AffiliationController extends Controller
{
    public function store(StoreAffiliationRequest $request)
    {

        $user = auth()->user();
        $league = $user->current_league_id;
        Log::info('leagueId', ['leagueId' => $league]);
        if (!$league) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer une affiliation',
            ], 400);
        }
        $validated = $request->validated();
        Log::info('validated', ['validated' => $validated]);

        $affiliation = Affiliation::create(
            $validated + [
                'league_id' => $league,
                'status' => 'Affilé'
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Affiliation enregistrée avec succès',
            'data' => $affiliation
        ], 201);
    }
}
