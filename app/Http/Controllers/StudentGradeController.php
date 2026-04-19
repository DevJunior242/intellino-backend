<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StudentGradeRequest;

class StudentGradeController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $role = $request->attributes->get('role');
        $user = auth()->user();

        $clubId = $request->attributes->get('club_id');

        if ($role === 'parent') {
            $parent = ParentModel::where('user_id', $user->id)->first();
            $studentIds = $parent->students()->pluck('students.id');
            $studentGrades = StudentGrade::with('student:id,fullname', 'currentGrade:id,name,description')
                ->whereIn('student_id', $studentIds)
                ->where('club_id', $clubId)
                ->get();
        } elseif ($role === 'karateka') {
            $student = Student::where('user_id', $user->id)->first();
            $studentGrades = StudentGrade::with('student:id,fullname', 'currentGrade:id,name,description')
                ->where('student_id', $student->id)
                ->get();
        } else {
            $studentGrades = StudentGrade::with('student:id,fullname', 'currentGrade:id,name,description')
                ->where('club_id', $clubId)
                ->whereIn('id', function ($query) use ($clubId) {
                    $query->selectRaw('MAX(id)')
                        ->from('student_grades')
                        ->where('club_id', $clubId)
                        ->groupBy('student_id');
                })
                ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'grade list',
            'studentGrades' => $studentGrades
        ]);
    }

    public function studentHistory(Student $student): JsonResponse
    {
        $clubId = request()->attributes->get('club_id');

        return response()->json([
            'all_club_grades' => Grade::select('id', 'name', 'description')
                ->get(),

            // Tous les passages de grades de cet élève
            'student_history' => StudentGrade::where('student_id', $student->id)
                ->where('club_id', $clubId)
                ->with('instructor')
                ->orderBy('awarded_at', 'desc')
                ->get()
        ]);
    }
    public function store(StudentGradeRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $clubId = $request->attributes->get('club_id');

            $validated = $request->validated();

            $hasGrades = StudentGrade::where('student_id', $request->student_id)->exists();

            if (!$hasGrades) {
                // C'est son PREMIER grade
                $studentGrade = StudentGrade::create([
                    ...$validated,
                    'instructor_id' => $user->id,
                    'club_id' => $clubId,
                    'is_current' => true,


                ]);
            } else {
                $studentGrade = $this->promoteStudent(
                    $request->student_id,
                    $request->current_grade_id
                );
            }


            return response()->json([
                'success' => true,
                'message' => 'Grade attribuée avec succès.',
                'data' => $studentGrade,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'attribution du grade.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show(StudentGrade $studentGrade)
    {
        $studentGrade->load(['student', 'grade']);

        return response()->json([
            'data' => $studentGrade,
        ]);
    }

    private function promoteStudent($studentId, $newGradeId)
    {
        DB::transaction(function () use ($studentId, $newGradeId) {
            // 1. On enlève le badge "current" de tous les anciens grades de l'élève
            StudentGrade::where('student_id', $studentId)
                ->update(['is_current' => false]);

            // 2. On ajoute le nouveau grade comme étant le "current"
            $newGrade = StudentGrade::create([
                'student_id' => $studentId,
                'current_grade_id' => $newGradeId,
                'is_current' => true,
                'awarded_at' => now(),
                'instructor_id' => auth()->id(),
                'club_id' => request()->attributes->get('club_id'),
            ]);

            return $newGrade;
        });
    }
}
