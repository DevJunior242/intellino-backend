<?php

namespace App\Services;

use App\Models\Examen;

class ExamenStatService
{
    public function getExamenStats($activeId, $activeType)
    {
        $query = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType);
        $total = $query->count();

        $scheduled = $query->clone()->where('status', Examen::STATUS_SCHEDULED)->count();
        $ongoing   = $query->clone()->where('status', Examen::STATUS_ONGOING)->count();
        $completed = $query->clone()->where('status', Examen::STATUS_COMPLETED)->count();
        $cancelled = $query->clone()->where('status', Examen::STATUS_CANCELLED)->count();
        $postponed = $query->clone()->where('status', Examen::STATUS_POSTPONED)->count();

        $effectiveExamens = $query->clone()->whereIn('status', [
            Examen::STATUS_ONGOING,
            Examen::STATUS_COMPLETED
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
