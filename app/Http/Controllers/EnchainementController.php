<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Examen;
use Illuminate\Http\Request;
use App\Models\GradeEnchainement;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreGradeEnchainementRequest;

class EnchainementController extends Controller
{

    public function store(StoreGradeEnchainementRequest $request, Examen $examen)
    {


        try {
            $validated = $request->validated();
            $order = GradeEnchainement::where('current_grade_id', $request->current_grade_id)->max('order') + 1;

            if ($examen->organisateur_id !== $request->attributes->get('organisateur_id')) {
                return response()->json(['message' => 'Action non autorisée'], 403);
            }
            $enchainement = GradeEnchainement::create([
                ...$validated,
                'order' => $order,
                'examen_id' => $examen->id,
                'current_grade_id' => $examen->current_grade_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $enchainement,
                'current_grade_id' => $examen->current_grade_id,
                'message' => 'votre enchainement a bien été créé'
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();

            if ($errcode == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'vous avez déjà créé cet enchaînement',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue veuiilez réessayer',
            ], 500);
        }
    }

    public function show(Request $request, $examenId)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // 2. Charger l'examen avec ses enchaînements
        $examen = Examen::with(['enchainements' => function ($q) {
            $q->orderBy('order', 'asc');
        }])->findOrFail($examenId);

        $isOwner = ($examen->organisateur_id === $activeId);

        $isRattache = ($activeType === 'Club' &&
            $examen->organisateur_type === 'Ligue' &&
            Club::where('id', $activeId)->where('league_id', $examen->organisateur_id)->exists());

        if (!$isOwner && !$isRattache) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de voir les détails de cet examen.'
            ], 403);
        }

        // 4. Retourner les données
        return response()->json([
            'success' => true,
            'enchainements' => $examen->enchainements,
            'is_owner' => $isOwner,
        ], 200);
    }

    public function destroy(Examen $examen, string $id, Request $request)
    {
        $orgdId = $request->attributes->get('organisateur_id');
        $orgType = $request->attributes->get('organisateur_type');
        $enchainement = GradeEnchainement::where('id', $id)
            ->where('organisateur_id', $orgdId)
            ->where('organisateur_type', $orgType)
            ->where('examen_id', $examen->id)
            ->firstOrFail();

        if ($enchainement->evaluations()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cet enchaînement car il est utilisé dans des notes. Veuillez plutôt le désactiver.'
            ], 422);
        }
        $enchainement->delete();
        return response()->json(['success' => true, 'message' => 'Enchaînement supprimé']);
    }
}
