<?php

namespace App\Http\Controllers;

use App\Models\Saison;
use App\Models\Licence;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // On récupère toutes les catégories avec les displines associées, triées par age_min asc

        $categories = Category::with('disciplines')
            ->orderBy('age_min', 'asc')
            ->get();

        return response()->json($categories);
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'           => 'required|string|max:100',
            'sexe'          => 'required|in:M,F,Mixte',
            'age_min'       => 'nullable|integer|min:0',
            'age_max'       => 'nullable|integer|gt:age_min',
            'saison_id'     => 'required|exists:saisons,id',
            'disciplines'   => 'required|array',
            'disciplines.*' => 'exists:disciplines,id',
        ]);

        $category = Category::create($validated);

        // Attacher les disciplines (table pivot)
        $category->disciplines()->sync($request->disciplines);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès',
            'data' => $category
        ], 201);
    }

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
