<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddManuelClubController extends Controller
{
    public function store(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        // Validation des données
        $validated = $request->validate([
            'name' => 'required|string|unique:clubs,name',
            'discipline_id' => 'required|exists:disciplines,id',
            'city' => 'required|string',
            'address' => 'nullable|string',
        ], [
            'name.required' => 'Le nom du club est obligatoire',
            'name.unique' => 'Le nom du club existe déjà',
            'discipline_id.required' => 'Le discipline du club est obligatoire',
            'city.required' => 'La ville du club est obligatoire',
            'address.required' => 'L\'adresse du club est obligatoire',
        ]);
        // Création du club
        $league = League::where('id', $activeId)->firstOrFail();
        Log::info('league', ['league' => $league]);
        if (!$league) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être dans une ligue pour créer un club',
            ], 422);
        }
        Club::create([
            'name' => $validated['name'],
            'country_id' => $league->country_id,
            'city' => $validated['city'],
            'address' => $validated['address'],
            'discipline_id' => $validated['discipline_id'],
            'league_id' => $league->id,

        ]);
        return response()->json([
            'success' => true,
            'message' => 'Ajout manuel de club réussi',
        ]);
    }
}
