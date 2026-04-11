<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Support\Str;
use App\Models\ExamenResult;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use App\Models\ExamenEvaluation;
use App\Models\GradeEnchainement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreEvaluationReq;

class EvaluationController extends Controller
{

    public function index(Examen $examen, Request $request)
    {
        //afficher les evaluations de chaque club 

        Log::info('examenId', ['examenId' => $examen->id]);
        $user = auth()->user();
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;
        //

        if ($examen->club_id != $clubId) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun examen trouvé pour ce club',
            ]);
        }

        //student
        $examenId = $examen->id;
        //parent 
        if ($role === 'parent') {

            $parent = ParentModel::where('user_id', $user->id)
                ->with([
                    'students' => function ($q) {
                        $q->with('club:id,name,logo')

                            ->select('students.id', 'fullname', 'birthdate', 'sex', 'photo', 'status', 'club_id');
                    }
                ])
                ->first();

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission d\'accéder à cette page',
                ], 403);
            }


            $studentId = $parent->students()->pluck('students.id');

            $evaluations = ExamenEvaluation::with(['student:id,fullname,birthdate', 'enchainement:id,name,order', 'examen.currentGrade'])
                ->whereIn('student_id', $studentId)
                ->where('examen_id', $examenId)->get();

            $exam = $evaluations->first()?->examen;

            $students = $evaluations->groupBy('student_id')->map(function ($items) {
                $student = $items->first()?->student;
                $notes = [];
                $totalScore = 0;
                $index = 1;
                foreach ($items as $note) {
                    $notes[] = [
                        'key' => "note{$index}",
                        'enchainement_id' => $note->enchainement?->id,
                        'label' => $note->enchainement?->name,
                        'value' => $note->score,
                    ];
                    $index++;

                    $score = $note->score;

                    $totalScore += $score;
                }


                $moyenne = round($totalScore, 2);

                $passage = $moyenne >= 50 ? 'Passable' : 'Redoublable';

                return [
                    'id' => $student->id,
                    'fullname' => $student?->fullname,
                    'birthdate' => $student?->birthdate,
                    'notes' => $notes,
                    'moyenne' => $moyenne,
                    'passage' => $passage,
                ];
            })->values();
            // Calcul du rang
            $students = $students->sortByDesc('moyenne')->values();
            foreach ($students as $index => &$student) {
                $student['rang'] = $index + 1;
                $students[$index] = $student;
            }
            return response()->json([
                'success' => true,
                'exam' => [
                    'id' => $exam?->id,
                    'start_date' => $exam?->start_date,
                    'end_date' => $exam?->end_date,
                    'grade' => $exam?->currentGrade?->name,
                ],
                'students' => $students,
            ], 200);
        }
        //admin , instructeur, secretary, super_admin
        $evaluations = ExamenEvaluation::with([
            'student:id,fullname,birthdate',
            'enchainement:id,name,order',
            'examen:id,current_grade_id,next_grade_id,start_date,end_date',
            'examen.currentGrade:id,name'
        ])
            ->where('examen_id', $examenId)
            ->get();
        Log::info('evaluations', ['evaluations' => $evaluations]);
        $exam = $evaluations->first()?->examen;
        Log::info('examen', [
            'examen_id' => $exam?->id,
            'current_grade_id' => $exam?->current_grade_id,
        ]);

        $students = $evaluations->groupBy('student_id')->map(function ($items) {
            $student = $items->first()?->student;
            $notes = [];
            $totalScore = 0;
            $index = 1;
            foreach ($items as $note) {
                $notes[] = [
                    'key' => "note{$index}",
                    'enchainement_id' => $note->enchainement?->id,

                    'label' => $note->enchainement?->name,
                    'value' => $note->score,
                ];
                $index++;

                $score = $note->score;

                $totalScore += $score;
            }


            $moyenne = round($totalScore, 2);

            $passage = $moyenne >= 50 ? 'Passable' : 'Redoublable';
            return [
                'id' => $student->id,
                'fullname' => $student?->fullname,
                'birthdate' => $student?->birthdate,
                'notes' => $notes,
                'moyenne' => $moyenne,
                'passage' => $passage,
            ];
        })->values();
        // Calcul du rang
        $students = $students->sortByDesc('moyenne')->values();

        $currentRank = 1;
        $previousMoyenne = null;

        // 2. On utilise map pour transformer la collection et ajouter le rang
        $students = $students->map(function ($student, $index) use (&$currentRank, &$previousMoyenne) {
            // Gestion des ex-aequo
            if ($previousMoyenne !== null && $student['moyenne'] !== $previousMoyenne) {
                $currentRank = $index + 1;
            }

            $student['rang'] = $currentRank;
            $previousMoyenne = $student['moyenne'];

            return $student;
        });
        return response()->json([
            'success' => true,
            'exam' => [
                'id' => $exam?->id,
                'start_date' => $exam?->start_date,
                'end_date' => $exam?->end_date,
                'grade' => $exam?->currentGrade?->name,
            ],
            'students' => $students,
        ], 200);
    }







    public function store(StoreEvaluationReq $request, Examen $examen, ExamenCandidat $candidat)
    {


        try {
            $validated = $request->validated();
            $user = auth()->user();
            if ($candidat->examen_id != $examen->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas noter cette étudiant',
                ], 403);
            }
            if ($candidat->status !== 'registered') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas noter cette étudiant',
                ], 403);
            }


            //////////////////////////////////////////////////////
            $enchainements = GradeEnchainement::where('examen_id', $examen->id)
                ->where('current_grade_id', $examen->current_grade_id)
                ->get()
                ->keyBy('id');


            if (!$enchainements) {
                return response()->json(['success' => false, 'message' => 'Enchaînement invalide'], 403);
            }

            foreach ($validated['notes'] as $note) {
                if (!isset($enchainements[$note['enchainement_id']])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Enchaînement invalide'
                    ], 403);
                }

                if ($note['score'] > $enchainements[$note['enchainement_id']]->diviseur) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Score supérieur au maximum autorisé'
                    ], 400);
                }
            }

            //////////////////////////
            // $data = array_map(function ($note) use ($user, $examen, $candidat, $validated) {
            //     return [
            //         'id' => Str::uuid(),
            //         'enchainement_id' => $note['enchainement_id'],
            //         'score' => (float) $note['score'],
            //         'evaluated_by' => $user->id,
            //         'examen_id' => $examen->id,
            //         'student_id' => $candidat?->student_id,
            //         'comment' => $validated['commentaire'] ?? '',
            //         'created_at' => now(),
            //         'updated_at' => now(),

            //     ];
            // }, $validated['notes']);

            // $evaluation = ExamenEvaluation::insert($data);
            foreach ($validated['notes'] as $note) {
                ExamenEvaluation::updateOrCreate(
                    [
                        'examen_id'       => $examen->id,
                        'student_id'      => $candidat->student_id,
                        'enchainement_id' => $note['enchainement_id'],
                        'evaluated_by'    => $user->id,
                    ],
                    [
                        'score'   => (float) $note['score'],
                        'comment' => $validated['commentaire'] ?? '',
                    ]
                );
            }
            //total score 
            // $totalScore = collect($validated['notes'])->sum('score');
            $totalScore = collect($validated['notes'])->sum(fn($n) => (float) $n['score']);

            // 2. Calculer la moyenne (Nombre de notes)
            $nombreDeNotes = count($validated['notes']);
            $moyenne = $nombreDeNotes > 0 ? $totalScore / $nombreDeNotes : 0;
            Log::info('totalScore', ['totalScore' => $totalScore]);
            //passage
            $passage = $totalScore >= 50 ? 'Admis' : 'Ajourné';
            Log::info('examen', ['examen' => $examen]);
            //mettre a jour la table examen_result
            $result = ExamenResult::updateOrCreate([
                'examen_id' => $examen->id,
                'student_id' => $candidat?->student_id,
            ], [
                'total_score' => $totalScore,
                'decision' => $passage,
                'decided_by' => $user->id,
                'new_grade_id' => $passage == 'Admis' ? $examen->next_grade_id : $examen->current_grade_id,
            ]);
            //ajouter le nouveau grade dans student_grade 
            $studentGrade = StudentGrade::Create([
                'club_id' => $examen->club_id,
                'student_id' => $candidat?->student_id,
                'current_grade_id' => $passage == 'Admis' ? $examen->next_grade_id : $examen->current_grade_id,
                'awarded_at' => now(),
                'instructor_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre note a bien été enregistrée',

                'result' => $result,
                'studentGrade' => $studentGrade,
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();
            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            //erreur 23000
            if ($errcode == '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'vous avez déjà noté cet étudiant',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la note',
            ], 500);
        }
    }

    //update notes
    public function update(StoreEvaluationReq $request, Examen $examen, string $student_id)
    {
        $candidat = \App\Models\ExamenCandidat::where('examen_id', $examen->id)
            ->where('student_id', $student_id)
            ->first();

        if (!$candidat) {
            // Cela permet de voir quel ID pose problème dans tes logs
            return response()->json([
                'success' => false,
                'message' => "Lien non trouvé pour l'examen {$examen->id} et l'élève $student_id"
            ], 400);
        }
        return DB::transaction(function () use ($request, $examen, $candidat) {
            try {
                $validated = $request->validated();
                $user = auth()->user();

                // 1. Sécurités de base
                if ($candidat->examen_id !== $examen->id) {
                    return response()->json(['success' => false, 'message' => 'Lien examen/candidat invalide'], 403);
                }

                // 2. Supprimer les anciennes notes d'évaluation pour cet examen/étudiant
                ExamenEvaluation::where('examen_id', $examen->id)
                    ->where('student_id', $candidat->student_id)
                    ->delete();

                // 3. Insérer les nouvelles notes


                // la note doit etre inferieur au diviseur
                $enchainements = GradeEnchainement::where('examen_id', $examen->id)
                    ->where('current_grade_id', $examen->current_grade_id)
                    ->get()
                    ->keyBy('id');


                if (!$enchainements) {
                    return response()->json(['success' => false, 'message' => 'Enchaînement invalide'], 403);
                }

                foreach ($validated['notes'] as $note) {
                    if (!isset($enchainements[$note['enchainement_id']])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Enchaînement invalide'
                        ], 403);
                    }

                    if ($note['score'] > $enchainements[$note['enchainement_id']]->diviseur) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Score supérieur au maximum autorisé'
                        ], 400);
                    }
                }


                $data = array_map(function ($note) use ($user, $examen, $candidat, $validated) {


                    return [
                        'id' => (string) Str::uuid(),
                        'enchainement_id' => $note['enchainement_id'],
                        'score' => $note['score'],
                        'evaluated_by' => $user->id,
                        'examen_id' => $examen->id,
                        'student_id' => $candidat->student_id,
                        'comment' => $validated['commentaire'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['notes']);

                ExamenEvaluation::insert($data);

                // 4. Recalculer le score total et la décision
                $totalScore = collect($validated['notes'])->sum('score');
                $passage = $totalScore >= 50 ? 'Admis' : 'Ajourné';
                $finalGradeId = ($passage == 'Admis') ? $examen->next_grade_id : $examen->current_grade_id;

                // 5. Mettre à jour le résultat de l'examen
                $result = ExamenResult::updateOrCreate([
                    'examen_id' => $examen->id,
                    'student_id' => $candidat->student_id,
                ], [
                    'total_score' => $totalScore,
                    'decision' => $passage,
                    'decided_by' => $user->id,
                    'new_grade_id' => $finalGradeId,
                ]);

                // 6. Mettre à jour le grade actuel de l'étudiant
                // On cherche le dernier grade créé pour cet examen pour le modifier au lieu d'en créer un nouveau
                $studentGrade = StudentGrade::updateOrCreate([
                    'club_id' => $examen->club_id,
                    'student_id' => $candidat->student_id,
                ], [
                    'current_grade_id' => $finalGradeId,
                    'awarded_at' => now(),
                    'instructor_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Évaluation mise à jour avec succès',
                    'total_score' => $totalScore,
                    'decision' => $passage
                ], 200);
            } catch (\Exception $e) {
                Log::error('Erreur Update Evaluation', ['message' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Erreur technique'], 500);
            }
        });
    }
}
