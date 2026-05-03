<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Affiliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAffiliationRequest;

class AffiliationController extends Controller
{
    public function store(StoreAffiliationRequest $request)
    {

        $activeId = $request->attributes->get('organisateur_id');

        $saisonActive =  Saison::where('active', true)->first();
        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer une affiliation',
            ], 422);
        }

        if (!$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer une affiliation',
            ], 422);
        }
        $validated = $request->validated();
        //verifier si club deja dans une affiliation
        $affiliation = Affiliation::where('club_id', $validated['club_id'])
            ->where('saison_id', $saisonActive->id)
            ->where('league_id', $activeId)->first();
        if ($affiliation) {
            return response()->json([
                'success' => false,
                'message' => 'Ce club est déjà dans une affiliation',
            ], 422);
        }

        $affiliation = Affiliation::create(
            $validated + [
                'league_id' => $activeId,
                'saison_id' => $saisonActive->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Affiliation enregistrée avec succès',
            'data' => $affiliation
        ], 201);
    }
}
