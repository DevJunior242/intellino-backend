<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\League;
use App\Models\Licence;
use App\Models\Student;
use App\Models\Category;
use Illuminate\Http\Request;


class CategoryController extends Controller
{

    /**
     * Les catégories et la saison appartiennent toujours à la Fédération
     * (voir SaisonController::store). Une Ligue consulte celles de sa
     * fédération de rattachement, en lecture seule ; seule la Fédération
     * peut en créer/modifier — voir le bouton "Nouvelle catégorie" côté front.
     */
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $federationId = $activeId;
        $leagueId = null;

        if ($activeType === 'Ligue') {
            $league = League::find($activeId);

            if (!$league || !$league->federation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre ligue doit être affiliée à une fédération pour charger les catégories.',
                ], 422);
            }

            $federationId = $league->federation_id;
            $leagueId = $activeId;
        } elseif ($activeType !== 'Federation') {
            return response()->json([
                'success' => false,
                'message' => 'Seules une ligue ou une fédération peuvent consulter les catégories.',
            ], 403);
        }

        $saisonActive = Saison::where('active', true)
            ->where('organisateur_id', $federationId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonActive) {
            return response()->json([
                'categories'      => [],
                'total_licencies' => 0,
            ]);
        }

        $categories = Category::where('saison_id', $saisonActive->id)
            ->orderBy('age_min', 'asc')
            ->get();

        $result = $categories->map(function ($cat) use ($federationId, $leagueId, $saisonActive) {
            $dateAujourdhui = \Carbon\Carbon::now();
            $dateNaissanceMin = $dateAujourdhui->copy()->subYears($cat->age_max)->format('Y-m-d');
            $dateNaissanceMax = $dateAujourdhui->copy()->subYears($cat->age_min)->format('Y-m-d');

            $studentQuery = Student::whereHas('clubs', function ($q) use ($leagueId) {
                if ($leagueId) {
                    $q->where('league_id', $leagueId);
                }
            })
                ->whereBetween('birthdate', [$dateNaissanceMin, $dateNaissanceMax]);

            if ($cat->sexe !== 'Mixte') {
                $studentQuery->where('sex', $cat->sexe);
            }

            $studentCount = $studentQuery->count();
            $licenciesCount = $cat->getLicenciesCount($federationId, $saisonActive->id, $leagueId);

            return [
                'id'              => $cat->id,
                'nom'             => $cat->nom,
                'age_min'         => $cat->age_min,
                'age_max'         => $cat->age_max,
                'sexe'            => $cat->sexe,
                'licencies_count' => $licenciesCount,
                'student_count'   => $studentCount,
                'taux'            => $studentCount > 0
                    ? round(($licenciesCount / $studentCount) * 100)
                    : 0,
            ];
        });

        $totalLicencies = Licence::where('federation_id', $federationId)
            ->where('saison_id', $saisonActive->id)
            ->where('status', Licence::STATUS_VALIDE)
            ->when($leagueId, function ($query) use ($leagueId) {
                $query->whereHas('club', fn($q) => $q->where('league_id', $leagueId));
            })
            ->distinct('student_id')
            ->count('student_id');

        return response()->json([
            'categories'      => $result,
            'total_licencies' => $totalLicencies,
        ]);
    }

    public function getCategories(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // 1. Déterminer l'ID de la Fédération cible
        $targetFederationId = $activeId;

        if ($activeType === 'Ligue') {
            $league = \App\Models\League::find($activeId);

            if (!$league || !$league->federation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre ligue doit être affiliée à une fédération pour charger les catégories.'
                ], 422);
            }

            $targetFederationId = $league->federation_id;
        }

        // 2. Trouver la saison active de cette Fédération
        $saisonFederation = \App\Models\Saison::where('active', true)
            ->where('organisateur_id', $targetFederationId)
            ->where('organisateur_type', 'Federation')
            ->first();

        if (!$saisonFederation) {
            return response()->json([], 200); // On renvoie un tableau vide propre si la Fédé n'a pas encore créé de saison
        }

        // 3. Récupérer les catégories de la saison fédérale avec leurs sous-disciplines pour le front
        $categories = Category::where('saison_id', $saisonFederation->id)
            ->with('disciplines:id,nom')
            ->orderBy('age_min', 'asc')
            ->get();

        return response()->json($categories);
    }


    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'nom'           => 'required|string|max:100',
    //         'sexe'          => 'required|in:M,F,Mixte',
    //         'age_min'       => 'nullable|integer|min:0',
    //         'age_max'       => 'nullable|integer|gt:age_min',
    //         'saison_id'     => 'required|exists:saisons,id',
    //         'disciplines'   => 'required|array',
    //         'disciplines.*' => 'exists:disciplines,id',
    //     ]);

    //     $category = Category::create($validated);

    //     // Attacher les disciplines (table pivot)
    //     $category->disciplines()->sync($request->disciplines);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Catégorie créée avec succès',
    //         'data' => $category
    //     ], 201);
    // }

    public function update(Request $request, Category $category)
    {
        $category->update($request->validated());
        $category->disciplines()->sync($request->disciplines);

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Category $category)
    {
        $category->disciplines()->detach(); // nettoie le pivot
        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }
}
