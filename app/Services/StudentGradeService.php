<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\DB;

class StudentGradeService
{

    public function getGlobalStats($activeId)
    {
        return [
            'summary' => $this->getSummary($activeId),
            'distribution' => $this->getGradeDistribution($activeId),
            'history' => $this->getProgressionHistory($activeId),
        ];
    }

    private function getSummary($activeId)
    {
        $totalStudents = Student::whereHas('clubs', function ($q) use ($activeId) {
            $q->where('club_id', $activeId);
        })->count();
        $totalAwards = StudentGrade::where('club_id', $activeId)->count();

        // Calcul du taux de passage (exemple: élèves ayant eu un grade cette année)
        $thisYearAwards = StudentGrade::where('club_id', $activeId)
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
    public function getGradeDistribution($activeId)
    {
        return DB::table('student_grades')
            ->join('grades', 'student_grades.current_grade_id', '=', 'grades.id')
            ->where('student_grades.club_id', $activeId)
            ->where('student_grades.is_current', true)
            ->select(
                'grades.name',
                DB::raw('count(student_grades.student_id) as value')

            )
            ->groupBy('grades.id', 'grades.name')
            ->get();
    }

    private function getProgressionHistory($activeId)
    {
        // Nombre de grades décernés par mois sur les 5 derniers mois
        return StudentGrade::where('club_id', $activeId)
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
