<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreCandidatRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CandidatController extends Controller
{
    use AuthorizesRequests;
    public function store(StoreCandidatRequest $request)
    {
        $validated = $request->validated();


        $candidat = ExamenCandidat::create($validated);

        return response()->json([
            'success' => true,
            'data' => $candidat,
            'message' => 'votre candidat a bien été créé'
        ], 201);
    }

    public function addCandidate(Examen $examen, Student $student)
    {
        //$this->authorize('create', ExamenCandidat::class);

        try {
            $user = auth()->user();
            $examenId = $examen->id;
            $studentId = $student->id;


            $candidat = ExamenCandidat::create([
                'student_id' => $studentId,
                'examen_id' => $examenId,
                'status' => 'registered',
            ]);

            return response()->json([
                'success' => true,
                'data' => $candidat,
                'message' => 'votre candidat a bien été créé'
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();
            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            //erreur 23000
            if ($errcode == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'vous ne pouvez pas ajouter cet étudiant à cet examen.Il est déjà inscrit',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la note',
            ], 500);
        }
    }

    //remove
    public function destroy(Examen $examen, ExamenCandidat $examenCandidat)

    {
        try {
            //examen ca
            $examenId = $examen->id;
            $examenCandidatId = $examenCandidat->student_id;
            Log::info('examenId', ['examenId' => $examenId]);
            Log::info('examenCandidatId', ['examenCandidatId' => $examenCandidatId]);
            if ($examenCandidat->examen_id !== $examenId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer cet étudiant car il n\'est pas inscrit à cet examen',
                ], 400);
            }

            $examenCandidat = ExamenCandidat::where('examen_id', $examenId)
                ->where('student_id', $examenCandidatId)
                ->first();
            if (!$examenCandidat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer cet étudiant car il n\'est pas inscrit à cet examen',
                ], 400);
            }
            $examenCandidat->delete();

            return response()->json([
                'success' => true,
                'message' => 'Votre candidat a bien été supprimé'
            ], 200);
        } catch (\Throwable $th) {
            Log::error('erreur', ['erreur' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du candidat'
            ], 500);
        }
    }
}
