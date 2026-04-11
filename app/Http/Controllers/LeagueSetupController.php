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

        // 1. Validation globale de tout le payload d'un coup
        $validated = $request->validate([
            // Saison
            'id' => 'nullable|uuid',
            'saison.libele'    => 'required|string|max:100',
            'saison.dateDebut' => 'required|date',
            'saison.dateFin'   => 'required|date|after:saison.dateDebut',
            'saison.active'     => 'boolean',

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
            $result = DB::transaction(function () use ($validated, $request) {

                // ── Étape 1 : Créer la saison ──────────────────────────────
                $saisonId = $request->input('saison_id');

                if ($validated['saison']['active']) {
                    // On désactive toutes les saisons SAUF celle qu'on est en train de traiter
                    Saison::where('id', '!=', $saisonId)
                        ->where('active', true)
                        ->update(['active' => false]);
                }

                $saison = Saison::updateOrCreate(
                    ['id' => $saisonId],
                    [
                        'libele'    => $validated['saison']['libele'],
                        'dateDebut' => $validated['saison']['dateDebut'],
                        'dateFin'   => $validated['saison']['dateFin'],
                        'active'     => $validated['saison']['active'] ?? true,
                    ]
                );
                if ($request->filled('saison_id')) {
                    $saison->categories()->delete();
                }
                Log::info('saison', ['saison' => $saison]);

                // ── Étape 2 : Créer les disciplines ───────────────────────
                // On garde un index [0 => discipline, 1 => discipline...]
                // pour faire le lien avec les catégories côté frontend
                $disciplinesCreees = [];

                foreach ($validated['disciplines'] as $index => $disc) {
                    // firstOrCreate évite les doublons si Kata existe déjà
                    $discipline = Disciplineleague::firstOrCreate(
                        [
                            'nom' => $disc['nom'],
                        ],
                        [
                            'description' => $disc['description'] ?? null
                        ]

                    );
                    $disciplinesCreees[$index] = $discipline->id;
                    Log::info('discipline', ['discipline' => $discipline]);
                }

                // ── Étape 3 : Créer les catégories + attacher disciplines ──
                $categoriesCreees = [];

                foreach ($validated['categories'] as $catData) {
                    $categorie = Category::create([
                        'nom'       => $catData['nom'],
                        'sexe'      => $catData['sexe'],
                        'age_min'   => $catData['age_min'],
                        'age_max'   => $catData['age_max'],
                        'saison_id' => $saison->id,
                    ]);
                    Log::info('categorie', ['categorie' => $categorie]);

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
