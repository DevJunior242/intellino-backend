<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Student;
use App\Models\Category;
use Illuminate\Http\Request;


class CategoryController extends Controller
{

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $categories = Category::whereHas('saison', function ($query) use ($activeId, $activeType) {
            $query->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType);
        })
            ->orderBy('age_min', 'asc')
            ->get();

        $saisonActive =  Saison::where('active', true)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->first();
        if (!$saisonActive) {
            return null;
        }
        $saisonId = $saisonActive->id;
        $result = $categories->map(function ($cat) use ($activeId, $saisonId) {
            $dateAujourdhui = \Carbon\Carbon::now();
            $dateNaissanceMin = $dateAujourdhui->copy()->subYears($cat->age_max)->format('Y-m-d');
            $dateNaissanceMax = $dateAujourdhui->copy()->subYears($cat->age_min)->format('Y-m-d');

            $studentQuery = Student::whereHas('club', fn($q) => $q->where('league_id', $activeId))
                ->whereBetween('birthdate', [$dateNaissanceMin, $dateNaissanceMax]);

            if ($cat->sexe !== 'Mixte') {
                $studentQuery->where('sex', $cat->sexe);
            }

            $studentCount = $studentQuery->count();
            $licenciesCount = $cat->getLicenciesCount($activeId, $saisonId);

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

        $totalLicencies = Licence::where('league_id', $activeId)
            ->where('saison_id', $saisonId)
            ->where('status', Licence::STATUS_ACTIVE)
            ->distinct('student_id')
            ->count('student_id');

        return response()->json([
            'categories'      => $result,
            'total_licencies' => $totalLicencies,
        ]);
    }

    public function getCategories(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $categories = Category::whereHas('saison', function ($query) use ($activeId, $activeType) {
            $query->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType);
        })
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
