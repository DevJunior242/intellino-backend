<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Affiliation;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAffiliationRequest;

/**
 * Le tarif d'affiliation (cotisation) d'une saison est toujours défini par
 * la Fédération elle-même — voir TransactionController
 * (payable_type = 'affiliation') pour le suivi, club par club, du paiement
 * de ce tarif.
 */
class AffiliationController extends Controller
{
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut consulter ses tarifs d\'affiliation',
            ], 422);
        }

        $affiliations = Affiliation::where('federation_id', $activeId)
            ->with('saison')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $affiliations,
        ]);
    }

    public function store(StoreAffiliationRequest $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une fédération peut définir un tarif d\'affiliation',
            ], 422);
        }

        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord définir une saison active pour créer un tarif d\'affiliation',
            ], 422);
        }

        $validated = $request->validated();

        // Une seule affiliation par saison : si elle existe déjà, on met à jour le tarif.
        $affiliation = Affiliation::updateOrCreate(
            [
                'federation_id' => $activeId,
                'saison_id' => $saisonActive->id,
            ],
            [
                'cotisation' => $validated['cotisation'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tarif d\'affiliation enregistré avec succès',
            'data' => $affiliation,
        ], 201);
    }
}
