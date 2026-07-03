<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\SubDiscipline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubDisciplineController extends Controller
{

    public function store(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // ──  SÉCURITÉ RADICALE : Seule la Fédération passe ──────────────────
        if ($activeType !== 'Federation' || !$activeId) {
            return response()->json([
                'success' => false,
                'message' => 'Action interdite. Seule l\'administration de la Fédération peut définir la charte des disciplines et catégories.'
            ], 403);
        }

        // Récupération de la saison active de la Fédération
        $saison = Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saison) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir une saison fédérale active pour enregistrer cette configuration.',
            ], 422);
        }

        // ──  VALIDATION DU PAYLOAD ───────────────────────────────────────
        $validated = $request->validate([
            // Disciplines
            'disciplines'                => 'required|array|min:1',
            'disciplines.*.nom'          => 'required|string|max:100',
            'disciplines.*.description'  => 'nullable|string',

            // Catégories
            'categories'                 => 'required|array|min:1',
            'categories.*.nom'           => 'required|string|max:100',
            'categories.*.sexe'          => 'required|in:M,F,Mixte',
            'categories.*.age_min'       => 'required|integer|min:0',
            'categories.*.age_max'       => 'required|integer|gt:categories.*.age_min',
            'categories.*.disciplines'   => 'required|array|min:1',
            'categories.*.disciplines.*' => 'integer',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $saison, $activeId) {

                // ── ÉTAPE 1 : Créer / Récupérer les Disciplines de la Fédé ─────
                $disciplinesCreees = [];

                foreach ($validated['disciplines'] as $index => $disc) {
                    $discipline = SubDiscipline::firstOrCreate(
                        [
                            'nom'             => $disc['nom'],
                            'organisateur_id' => $activeId, // ID de la Fédé connectée
                        ],
                        [
                            'description'       => $disc['description'] ?? null,
                            'organisateur_type' => 'Federation',
                        ]
                    );
                    $disciplinesCreees[$index] = $discipline->id;
                }

                // ── ÉTAPE 2 : Créer les Catégories Officielles de la Saison ──
                $categoriesCreees = [];

                foreach ($validated['categories'] as $catData) {
                    $categorie = Category::firstOrCreate(
                        [
                            'nom'       => $catData['nom'],
                            'sexe'      => $catData['sexe'],
                            'saison_id' => $saison->id, // Lié à la saison Fédérale
                        ],
                        [
                            'age_min' => $catData['age_min'],
                            'age_max' => $catData['age_max'],
                        ]
                    );

                    // Mapping des index du tableau React vers les IDs auto-incrémentés
                    $discIds = collect($catData['disciplines'])
                        ->map(fn($index) => $disciplinesCreees[$index] ?? null)
                        ->filter()
                        ->values()
                        ->toArray();

                    // Synchro dans la table pivot
                    $categorie->disciplines()->sync($discIds);

                    $categoriesCreees[] = $categorie->load('disciplines');
                }

                return [
                    'saison'      => $saison,
                    'disciplines' => SubDiscipline::whereIn('id', $disciplinesCreees)->get(),
                    'categories'  => $categoriesCreees,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Charte nationale enregistrée avec succès par la Fédération.',
                'data'    => $result,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur configuration exclusive Fédération: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
