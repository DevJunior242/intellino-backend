<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Saison;
use App\Models\League;
use App\Models\Category;
use Illuminate\Http\Request;

class SaisonController extends Controller
{
    public function store(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // La Fédération gère toujours sa propre saison. Un Club/une Ligue
        // affilié(e) hérite de son parent (voir ResolvesActiveSaison) et ne
        // peut donc pas en créer une lui/elle-même. Seul un Club ou une Ligue
        // INDÉPENDANT(E) (sans parent) peut posséder sa propre saison.
        if ($activeType === 'Club') {
            $club = Club::find($activeId);

            if (!$club || $club->league_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul un club indépendant (non rattaché à une ligue) peut créer sa propre saison.'
                ], 403);
            }
        } elseif ($activeType === 'Ligue') {
            $league = League::find($activeId);

            if (!$league || $league->federation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seule une ligue indépendante (non rattachée à une fédération) peut créer sa propre saison.'
                ], 403);
            }
        } elseif ($activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour créer une saison.'
            ], 403);
        }
        $request->validate([
            'libelle' => 'required|string|max:255',
            'dateDebut' => 'required|date',
            'dateFin' => 'required|date',
        ]);


        $ancienneSaison = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->first();
        if ($ancienneSaison) {
            $ancienneSaison->update([
                'active' => false,
            ]);
        }
        $nouvelleSaison = Saison::create([
            'organisateur_id' => $activeId,
            'organisateur_type' => $activeType,
            'libele' => $request->libelle,
            'dateDebut' => $request->dateDebut,
            'dateFin' => $request->dateFin,
            'active' => true,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Saison créée avec succès',
            'saison' => $nouvelleSaison
        ], 201);
    }
}


    // Dupliquer les catégories pour la nouvelle saison
        // (les disciplines restent les mêmes via le pivot)
        // Category::where('saison_id', $ancienneSaison->id)
        //     ->get()
        //     ->each(function ($cat) use ($nouvelleSaison) {
        //         $nouvelle = $cat->replicate();
        //         $nouvelle->saison_id = $nouvelleSaison->id;
        //         $nouvelle->save();
        //         $nouvelle->disciplines()->sync($cat->disciplines->pluck('id'));
        //     });