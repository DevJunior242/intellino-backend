<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Category;
use Illuminate\Http\Request;

class SaisonController extends Controller
{
    public function store(Request $request)
    {

        $request->validate([
            'libelle' => 'required|string|max:255',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
        ]);


        $ancienneSaison = Saison::where('active', true)->first();
        $nouvelleSaison = Saison::create([
            'league_id' => $request->league_id,
            'libele' => $request->libelle,
            'date_debut' => $request->date_debut,
            'date_fin' => $request->date_fin,
            'active' => true,
        ]);

        // Dupliquer les catégories pour la nouvelle saison
        // (les disciplines restent les mêmes via le pivot)
        Category::where('saison_id', $ancienneSaison->id)
            ->get()
            ->each(function ($cat) use ($nouvelleSaison) {
                $nouvelle = $cat->replicate();
                $nouvelle->saison_id = $nouvelleSaison->id;
                $nouvelle->save();
                $nouvelle->disciplines()->sync($cat->disciplines->pluck('id'));
            });
        return response()->json([
            'success' => true,
            'message' => 'Saison créée avec succès',
            'saison' => $nouvelleSaison
        ], 201);
    }
}
