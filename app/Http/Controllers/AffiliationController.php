<?php

namespace App\Http\Controllers;

use App\Models\Affiliation;
use App\Http\Controllers\Controller;

use App\Http\Requests\StoreAffiliationRequest;

class AffiliationController extends Controller
{
    public function store(StoreAffiliationRequest $request)
    {

        $user = auth()->user();
        $league = $user->current_league_id;
        if (!$league) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer une affiliation',
            ], 400);
        }
        $validated = $request->validated();
        //verifier si club deja dans une affiliation
        $affiliation = Affiliation::where('club_id', $validated['club_id'])->where('league_id', $league)->first();
        if ($affiliation) {
            return response()->json([
                'success' => false,
                'message' => 'Ce club est déjà dans une affiliation',
            ], 400);
        }

        $affiliation = Affiliation::create(
            $validated + [
                'league_id' => $league,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Affiliation enregistrée avec succès',
            'data' => $affiliation
        ], 201);
    }
}
