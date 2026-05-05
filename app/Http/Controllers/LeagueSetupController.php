<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Disciplineleague;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeagueSetupController extends Controller
{

    public function store(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saison = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->first();
        if (!$saison) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir une saison active pour configurer la ligue',
            ], 422);
        }
        // 1. Validation globale de tout le payload d'un coup
        $validated = $request->validate([
            //organisateur
            'organisateur_id' => 'required|uuid',
            'organisateur_type' => 'required|string|in:Ligue,Federation',
            // Disciplines
            'disciplines'         => 'required|array|min:1',
            'disciplines.*.nom'   => 'required|string|max:100',

            // Catégories
            'categories'                   => 'required|array|min:1',
            'categories.*.nom'             => 'required|string|max:100',
            'categories.*.sexe'            => 'required|in:M,F,Mixte',
            'categories.*.age_min'         => 'required|integer|min:0',
            'categories.*.age_max'         => 'required|integer|gt:categories.*.age_min',
            'categories.*.disciplines'     => 'required|array|min:1',
            'categories.*.disciplines.*'   => 'integer',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $saison) {



                // ── Étape 2 : Créer les disciplines ───────────────────────
                // On garde un index [0 => discipline, 1 => discipline...]
                // pour faire le lien avec les catégories côté frontend
                $disciplinesCreees = [];

                foreach ($validated['disciplines'] as $index => $disc) {
                    // firstOrCreate évite les doublons si Kata existe déjà
                    $discipline = Disciplineleague::firstOrCreate(
                        [
                            'nom' => $disc['nom'],
                            'organisateur_id' => $validated['organisateur_id'],
                        ],
                        [
                            'description' => $disc['description'] ?? null,
                            'organisateur_type' => $validated['organisateur_type'],
                        ]

                    );
                    $disciplinesCreees[$index] = $discipline->id;
                }

                // ── Étape 3 : Créer les catégories + attacher disciplines ──
                $categoriesCreees = [];

                foreach ($validated['categories'] as $catData) {
                    $categorie = Category::firstOrCreate(
                        [
                            'nom'       => $catData['nom'],
                            'sexe'      => $catData['sexe'],
                            'saison_id' => $saison->id,
                        ],
                        [
                            'age_min' => $catData['age_min'],
                            'age_max' => $catData['age_max'],
                        ]
                    );

                    // Convertir les index frontend en vrais IDs MySQL
                    $discIds = collect($catData['disciplines'])
                        ->map(fn($index) => $disciplinesCreees[$index] ?? null)
                        ->filter()
                        ->values()
                        ->toArray();

                    $categorie->disciplines()->sync($discIds);

                    $categoriesCreees[] = $categorie->load('disciplines');
                }

                return [
                    'saison'      => $saison,
                    'disciplines' => Disciplineleague::whereIn('id', $disciplinesCreees)->get(),
                    'categories'  => $categoriesCreees,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Configuration enregistrée avec succès',
                'data'    => $result,
            ], 201);
        } catch (\Exception $e) {
            // La transaction a rollback automatiquement
            return response()->json([
                'message' => 'Erreur lors de la configuration',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
