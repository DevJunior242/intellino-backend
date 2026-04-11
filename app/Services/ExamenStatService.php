<?php

namespace App\Services;

use App\Models\Examen;

class ExamenStatService
{
    public function getExamenStats($clubId)
    {
        $query = Examen::where('club_id', $clubId);
        $total = $query->count();

        $scheduled = $query->where('status', 'scheduled')->count();
        $ongoing = $query->where('status', 'ongoing')->count();
        $completed = $query->where('status', 'completed')->count();
        $cancelled = $query->where('status', 'cancelled')->count();
        $postponed = $query->where('status', 'postponed')->count();

        $effectiveExamens = $query->whereIn('status', [
            'ongoing',
            'completed'
        ])->count();

        $reliabilityRate = $total > 0
            ? round(($effectiveExamens / $total) * 100, 2)
            : 0;

        $cancelRate = $total > 0
            ? round(($cancelled / $total) * 100, 2)
            : 0;

        // examens en retard
        $lateExamens = $query->whereNotNull('actual_start_time')
            ->whereColumn('actual_start_time', '>', 'start_time')
            ->count();


        // durée moyenne réelle (minutes)
        $avgDuration = $query->whereNotNull('actual_start_time')
            ->whereNotNull('actual_end_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time)) as avg')
            ->value('avg');


        // taux remplacement instructeur
        // $replacementRate = $total > 0
        //     ? round(($query->whereNotNull('replacement_instructor_id')->count() / $total) * 100, 2)
        //     : 0;


        return [
            'total' => $total,
            'scheduled' => $scheduled,
            'ongoing' => $ongoing,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'postponed' => $postponed,
            'effective_sessions' => $effectiveExamens,
            'reliability_rate' => $reliabilityRate,
            'cancel_rate' => $cancelRate,
            'late_sessions' => $lateExamens,
            'avg_duration_minutes' => $avgDuration,
            // 'replacement_rate' => $replacementRate,
        ];
    }
}
