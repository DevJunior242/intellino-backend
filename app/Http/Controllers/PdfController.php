<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ExamenEvaluation;
use Illuminate\Support\Facades\Log;

class PdfController extends Controller
{


    public function resultsPdf(Examen $examen, Request $request)
    {
        $studentId = $request->query('student_id');
        Log::info('student_id reçu', ['studentId' => $studentId]);
        $data = $this->getExamResultsData($examen, $studentId);

        $pdf = Pdf::loadView('pdf.examen-result', [
            'exam' => $data['exam'],
            'students' => $data['students']
        ])->setPaper('A4', 'portrait');

        return $pdf->download('resultats_examen_' . $data['exam']['grade'] . '.pdf');
    }

    private function getExamResultsData(Examen $examen, $studentId = null)
    {
        $query = ExamenEvaluation::with([
            'student:id,fullname,birthdate',
            'enchainement:id,name,order',
            'examen:id,current_grade_id,next_grade_id,start_date,end_date',
            'examen.currentGrade:id,name'
        ])
            ->where('examen_id', $examen->id);


        if ($studentId) {
            $query->where('student_id', $studentId);
        }
        $evaluations = $query->get();
        Log::info('evaluations', ['evaluations' => $evaluations->pluck('student_id')]);

        $exam = $evaluations->first()?->examen;

        $students = $evaluations->groupBy('student_id')->map(function ($items) {
            $student = $items->first()?->student;
            $notesObj = [];
            $totalScore = 0;

            foreach ($items as $note) {
                $label = $note->enchainement?->name;
                $score = $note->score;

                $notesObj[$label] = $score;
                $totalScore += $score;
            }

            $moyenne = round($totalScore, 2);
            $passage = $moyenne >= 50 ? 'Passable' : 'Redoublable';

            return [
                'id' => $student->id,
                'fullname' => $student?->fullname,
                'birthdate' => $student?->birthdate,
                ...$notesObj,
                'moyenne' => $moyenne,
                'passage' => $passage,
            ];
        })->values();

        // Classement
        $students = $students->sortByDesc('moyenne')->values();
        foreach ($students as $index => &$student) {
            $student['rang'] = $index + 1;
            $student['moyenne'] = $student['moyenne'] ?? 0;
            $student['passage'] = $student['passage'] ?? 'N/A';
            $students[$index] = $student;
        }

        return [
            'exam' => [
                'id' => $exam?->id,
                'start_date' => $exam?->start_date,
                'end_date' => $exam?->end_date,
                'grade' => $exam?->currentGrade?->name,
            ],
            'students' => $students
        ];
    }
}
