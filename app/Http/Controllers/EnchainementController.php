<?php

namespace App\Http\Controllers;

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

            $enchainement = GradeEnchainement::create([
                ...$validated,
                'order' => $order,
                'examen_id' => $examen->id,
                'current_grade_id' => $examen->current_grade_id,
                'club_id' => $request->validated_club_id,
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
            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            //erreur 23000
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

    public function show(Examen $examen, Request $request)
    {
        $examenId = $examen->id;
        $enchainements = GradeEnchainement::where('examen_id', $examenId)
            ->with('grade')
            ->where('current_grade_id', $examen->current_grade_id)
            ->where('club_id', $request->validated_club_id)
            ->orderBy('order', 'asc')
            ->get();

        if ($enchainements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun enchainement trouvé',
                'data' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'enchainements' => $enchainements,
            'message' => 'Liste des enchainements',
        ], 200);
    }

    public function destroy(Examen $examen, string $id, Request $request)
    {
        $clubId= $request->validated_club_id;
        $enchainement = GradeEnchainement::where('id', $id)
            ->where('club_id', $clubId)
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
