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

    public function addCandidate(Examen $examen, Student $student, Request $request)
    {
        //$this->authorize('create', ExamenCandidat::class);
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        try {

            // 2. Définition des accès
            $isOwner = ($examen->organisateur_id === $activeId);

            // Un club peut inscrire si l'examen est organisé par une Ligue
            $isClubAccessingLeague = ($activeType === 'Club' && $examen->organisateur_type === 'Ligue');

            if (!$isOwner && !$isClubAccessingLeague) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission d\'inscrire des candidats à cet examen.'
                ], 403);
            }
            Log::info('student current grade id', ['student' => $student->currentGrade]);
            $studentCurrentGradeId = $student->currentGrade?->current_grade_id;
            // Si l'élève n'a pas exactement le grade requis par l'examen
            if ($studentCurrentGradeId !== $examen->current_grade_id) {
                return response()->json([
                    'success' => false,
                    'message' => "Ce candidat n'a pas le grade requis pour participer à cet examen. Grade requis : " . ($examen->currentGrade->name ?? 'Inconnu'),
                ], 422);
            }

            // --- VÉRIFICATION OPTIONNELLE : Déjà inscrit ? ---
            $alreadyRegistered = ExamenCandidat::where('examen_id', $examen->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($alreadyRegistered) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet étudiant est déjà inscrit à cet examen.',
                ], 422);
            }
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
