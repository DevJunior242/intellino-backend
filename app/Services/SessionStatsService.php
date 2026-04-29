<?php

namespace App\Services;

use App\Models\SessionModel;

class SessionStatsService
{

    private function clubSessionQuery($activeId)
    {
        return SessionModel::whereHas('course', function ($query) use ($activeId) {
            $query->where('club_id', $activeId);
        });
    }
    public function getStats($activeId)
    {
        $total = $this->clubSessionQuery($activeId)->count();

        $scheduled = $this->clubSessionQuery($activeId)->where('status', SessionModel::STATUS_SCHEDULED)->count();
        $ongoing = $this->clubSessionQuery($activeId)->where('status', SessionModel::STATUS_ONGOING)->count();
        $completed = $this->clubSessionQuery($activeId)->where('status', SessionModel::STATUS_COMPLETED)->count();
        $cancelled = $this->clubSessionQuery($activeId)->where('status', SessionModel::STATUS_CANCELLED)->count();
        $postponed = $this->clubSessionQuery($activeId)->where('status', SessionModel::STATUS_POSTPONED)->count();

        $effectiveSessions = $this->clubSessionQuery($activeId)->clone()->whereIn('status', [
            SessionModel::STATUS_ONGOING,
            SessionModel::STATUS_COMPLETED,
        ])->count();

        $reliabilityRate = $total > 0
            ? round(($effectiveSessions / $total) * 100, 2)
            : 0;

        $cancelRate = $total > 0
            ? round(($cancelled / $total) * 100, 2)
            : 0;

        // sessions en retard
        $lateSessions = $this->clubSessionQuery($activeId)->whereNotNull('actual_start_time')
            ->whereColumn('actual_start_time', '>', 'start_time')
            ->count();


        // durée moyenne réelle (minutes)
        $avgDuration = $this->clubSessionQuery($activeId)->whereNotNull('actual_start_time')
            ->whereNotNull('actual_end_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time)) as avg')
            ->value('avg');


        // taux remplacement instructeur
        $replacementRate = $total > 0
            ? round(($this->clubSessionQuery($activeId)->whereNotNull('replacement_instructor_id')->count() / $total) * 100, 2)
            : 0;


        return [
            'total' => $total,
            'scheduled' => $scheduled,
            'ongoing' => $ongoing,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'postponed' => $postponed,
            'effective_sessions' => $effectiveSessions,
            'reliability_rate' => $reliabilityRate,
            'cancel_rate' => $cancelRate,
            'late_sessions' => $lateSessions,
            'avg_duration_minutes' => $avgDuration,
            'replacement_rate' => $replacementRate,
        ];
    }
}
