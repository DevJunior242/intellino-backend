<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\SubDiscipline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class SubDisciplineController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Seul le gestionnaire EFFECTIF de la saison peut définir la charte
     * disciplines/catégories : la Fédération, ou une Ligue indépendante (pas
     * de federation_id) — même logique que la saison elle-même, voir
     * ResolvesActiveSaison. Une Ligue affiliée reste en lecture seule : elle
     * hérite de la charte de sa Fédération.
     */
    public function store(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $organisateur = $this->organisateurEffectifSaison($activeId, $activeType);

        if (!$organisateur || $organisateur['type'] !== $activeType) {
            return response()->json([
                'success' => false,
                'message' => $organisateur
                    ? "Action interdite. C'est à l'administration de votre "
                        . ($organisateur['type'] === 'Federation' ? 'fédération' : 'ligue')
                        . ($organisateur['nom'] ? " ({$organisateur['nom']})" : '')
                        . " de définir la charte des disciplines et catégories."
                    : 'Seules une ligue ou une fédération peuvent définir une charte de disciplines et catégories.',
            ], 403);
        }

        $saison = $this->saisonActivePour($activeId, $activeType);

        if (!$saison) {
            return response()->json([
                'success' => false,
                'message' => $this->messageAucuneSaisonActivePour($activeId, $activeType),
            ], 422);
        }

        // ──  VALIDATION DU PAYLOAD ───────────────────────────────────────
        $msg = [
            'disciplines.required' => 'Vous devez définir au moins une discipline.',
            'disciplines.*.nom.required' => 'La discipline doit avoir un nom.',
            'disciplines.*.nom.max' => 'Le nom de la discipline doit faire moins de 100 caractères.',
            'disciplines.*.description.max' => 'La description de la discipline doit faire moins de 1000 caractères.',
            'categories.required' => 'Vous devez définir au moins une catégorie.',
            'categories.*.nom.required' => 'La catégorie doit avoir un nom.',
            'categories.*.sexe.required' => 'La catégorie doit avoir un sexe.',
            'categories.*.age_min.required' => 'La catégorie doit avoir une borne inférieure.',
            'categories.*.age_max.required' => 'La catégorie doit avoir une borne supérieure.',
            'categories.*.age_min.min' => 'La borne inférieure doit être supérieure ou égale à la borne supérieure.',
            'categories.*.age_max.min' => 'La borne supérieure doit être supérieure ou égale à la borne inférieure.',
            'categories.*.age_max.gt' => 'La borne supérieure doit être supérieure à la borne inférieure.',
            'categories.*.disciplines.required' => 'Vous devez définir au moins un discipline pour la catégorie.',
            'categories.*.disciplines.*.required' => 'Vous devez définir un discipline valide pour la catégorie.',
        ];
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
            'categories.*.disciplines.*' => 'string',
            'categories.*.poids_min'     => 'nullable|numeric|min:0',
            'categories.*.poids_max'     => 'nullable|numeric|min:0',
        ], $msg);

        // Une catégorie rattachée au Kumite doit obligatoirement avoir une borne de poids
        // (au moins poids_min ou poids_max), pour permettre la pesée officielle.
        foreach ($validated['categories'] as $index => $catData) {
            $estKumite = collect($catData['disciplines'])
                ->contains(fn($nom) => str_contains(strtolower($nom), 'kumite'));

            if ($estKumite && empty($catData['poids_min']) && empty($catData['poids_max'])) {
                throw ValidationException::withMessages([
                    "categories.{$index}.poids_max" => "La catégorie « {$catData['nom']} » est rattachée au Kumite : au moins une borne de poids (min ou max) est requise.",
                ]);
            }

            if (!empty($catData['poids_min']) && !empty($catData['poids_max']) && $catData['poids_max'] <= $catData['poids_min']) {
                throw ValidationException::withMessages([
                    "categories.{$index}.poids_max" => "La catégorie « {$catData['nom']} » : le poids max doit être supérieur au poids min.",
                ]);
            }

            // WKF Kata Competition Rules, Art. 8.1.2 : l'âge sénior diffère
            // selon la discipline — 18 ans pour le Kumite, 16 ans pour le Kata.
            $estSenior = str_contains(strtolower($catData['nom']), 'senior')
                || str_contains(strtolower($catData['nom']), 'sénior');

            if ($estSenior) {
                $estKata = collect($catData['disciplines'])
                    ->contains(fn($nom) => str_contains(strtolower($nom), 'kata'));

                if ($estKumite && $catData['age_min'] < 18) {
                    throw ValidationException::withMessages([
                        "categories.{$index}.age_min" => "La catégorie « {$catData['nom']} » (Sénior Kumite) : l'âge minimum doit être de 18 ans.",
                    ]);
                }

                if ($estKata && $catData['age_min'] < 16) {
                    throw ValidationException::withMessages([
                        "categories.{$index}.age_min" => "La catégorie « {$catData['nom']} » (Sénior Kata) : l'âge minimum doit être de 16 ans.",
                    ]);
                }
            }
        }

        try {
            $result = DB::transaction(function () use ($validated, $saison, $organisateur) {

                // ── ÉTAPE 1 : Créer / Récupérer les Disciplines de l'organisateur ──
                $disciplinesCreees = [];

                foreach ($validated['disciplines'] as $disc) {
                    $discipline = SubDiscipline::firstOrCreate(
                        [
                            'nom'             => $disc['nom'],
                            'organisateur_id' => $organisateur['id'],
                            'organisateur_type' => $organisateur['type'],
                        ],
                        [
                            'description'       => $disc['description'] ?? null,
                        ]
                    );
                    $disciplinesCreees[strtolower($disc['nom'])] = $discipline->id;
                }

                // ── ÉTAPE 2 : Créer les Catégories Officielles de la Saison ──
                $categoriesCreees = [];

                foreach ($validated['categories'] as $catData) {
                    $categorie = Category::updateOrCreate(
                        [
                            'nom'       => $catData['nom'],
                            'sexe'      => $catData['sexe'],
                            'saison_id' => $saison->id,
                        ],
                        [
                            'age_min'   => $catData['age_min'],
                            'age_max'   => $catData['age_max'],
                            'poids_min' => $catData['poids_min'] ?? null,
                            'poids_max' => $catData['poids_max'] ?? null,
                        ]
                    );

                    // Mapping des noms de disciplines vers les IDs créés/existants
                    $discIds = collect($catData['disciplines'])
                        ->map(fn($nom) => $disciplinesCreees[strtolower($nom)] ?? null)
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
                'message' => 'Charte des disciplines et catégories enregistrée avec succès.',
                'data'    => $result,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur configuration disciplines/catégories: ' . $e->getMessage(), [
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
