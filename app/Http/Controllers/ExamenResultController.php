<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\ExamenResult;
use Illuminate\Http\Request;
use App\Http\Requests\ExamenResultRequest;

class ExamenResultController extends Controller
{
    public function store(ExamenResultRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (!$user->club_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être membre d\'un club pour accéder à cette page',
            ], 403);
        }
        $examen = Examen::where('id', $request->examen_id)
            ->where('club_id', $user->club_id)
            ->first();
        if (!$examen) {
            return response()->json([
                'success' => false,
                'message' => 'L\'examen spécifié n\'existe pas',
            ], 404);
        }
        $examenResult = ExamenResult::create([
            'examen_id' => $examen->id,
            'student_id' => $validated['student_id'],
            'total_score' => $validated['total_score'],
            'decision' => $validated['decision'],
            'decided_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre résultat a bien été enregistré',
            'examenResult' => $examenResult
        ], 201);
    }
}
