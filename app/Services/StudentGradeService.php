<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class StudentGradeService
{

    public function getGlobalStats($clubId)
    {
        return [
            'summary' => $this->getSummary($clubId),
            'distribution' => $this->getGradeDistribution($clubId),
            'history' => $this->getProgressionHistory($clubId),
        ];
    }

    private function getSummary($clubId)
    {
        $totalStudents = Student::where('club_id', $clubId)->count();
        $totalAwards = StudentGrade::where('club_id', $clubId)->count();

        // Calcul du taux de passage (exemple: élèves ayant eu un grade cette année)
        $thisYearAwards = StudentGrade::where('club_id', $clubId)
            ->whereYear('awarded_at', Carbon::now()->year)
            ->distinct('student_id')
            ->count();

        $rate = $totalStudents > 0 ? round(($thisYearAwards / $totalStudents) * 100) : 0;

        return [
            ['title' => 'Total Élèves', 'value' => (string)$totalStudents, 'trend' => 'Inscrits actifs'],
            ['title' => 'Grades Décernés', 'value' => (string)$totalAwards, 'trend' => 'Total historique'],
            ['title' => 'Taux de Passage', 'value' => $rate . '%', 'trend' => 'Cette année'],
            ['title' => 'Dernière Promo', 'value' => 'Récent', 'trend' => Carbon::now()->format('d M')]
        ];
    }
    public function getGradeDistribution($clubId)
    {
        return DB::table('student_grades')
            ->join('grades', 'student_grades.current_grade_id', '=', 'grades.id')
            ->where('student_grades.club_id', $clubId)
            ->where('student_grades.is_current', true)
            ->select(
                'grades.name',
                DB::raw('count(student_grades.student_id) as value')

            )
            ->groupBy('grades.id', 'grades.name')
            ->get();
    }

    private function getProgressionHistory($clubId)
    {
        // Nombre de grades décernés par mois sur les 5 derniers mois
        return StudentGrade::where('club_id', $clubId)
            ->where('awarded_at', '>=', Carbon::now()->subMonths(5))
            ->select(
                DB::raw("DATE_FORMAT(awarded_at, '%b') as month"),
                DB::raw('count(*) as count'),
                DB::raw('MIN(awarded_at) as sort_date')
            )
            ->groupBy('month')
            ->orderBy('sort_date')
            ->get()
            ->map(fn($item) => ['month' => $item->month, 'count' => $item->count]);
    }
}
