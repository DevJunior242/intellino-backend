<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentCategory;
use App\Models\PricingPlan;
use Illuminate\Support\Str;
use App\Http\Requests\StorePaymentCategoryRequest;

class PaymentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');

        $categories = PaymentCategory::where('is_system', true)
            ->when($activeId, fn ($q) => $q->orWhere('club_id', $activeId))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(StorePaymentCategoryRequest $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($activeType !== 'Club' || !$activeId) {
            return response()->json([
                'message' => 'Seul un club peut créer une catégorie de paiement personnalisée.',
            ], 403);
        }

        $validated = $request->validated();

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (PaymentCategory::where('slug', $slug)->where('club_id', $activeId)->exists()) {
            $i++;
            $slug = "{$baseSlug}-{$i}";
        }

        $category = PaymentCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'affects_validity' => $validated['affects_validity'],
            'is_system' => false,
            'club_id' => $activeId,
        ]);

        return response()->json(['success' => true, 'category' => $category], 201);
    }

    public function destroy(Request $request, $id)
    {
        $activeId = $request->attributes->get('organisateur_id');

        $category = PaymentCategory::where('id', $id)
            ->where('club_id', $activeId)
            ->where('is_system', false)
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Catégorie introuvable'], 404);
        }

        if (PricingPlan::where('payment_category_id', $category->id)->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : des tarifs utilisent encore cette catégorie.',
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Catégorie supprimée']);
    }
}
