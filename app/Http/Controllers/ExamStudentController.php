<?php

namespace App\Http\Controllers;

use App\Models\ExamenLeague;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddStudentToExamRequest;

class ExamStudentController extends Controller
{
    public function addStudent(AddStudentToExamRequest $request): JsonResponse
    {
        $exam = ExamenLeague::findOrFail($request->exam_id);
        $studentId = $request->student_id;
        $clubId = $request->validated_current_club_id;

        // Vérifie si le student est déjà inscrit
        if ($exam->students()->where('student_id', $studentId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Student déjà inscrit à cet examen'
            ], 422);
        }

        // Ajoute le student
        $exam->students()->attach($studentId, ['club_id' => $clubId]);

        return response()->json([
            'success' => true,
            'message' => 'Student ajouté à l’examen'
        ]);
    }

    public function listStudents(ExamenLeague $exam): JsonResponse
    {
        $students = $exam->students()->with('club')->get();

        return response()->json([
            'success' => true,
            'students' => $students
        ]);
    }
}
