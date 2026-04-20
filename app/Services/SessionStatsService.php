<?php

namespace App\Services;

use App\Models\SessionModel;

class SessionStatsService
{

    private function clubSessionQuery($clubId)
    {
        return SessionModel::whereHas('course', function ($query) use ($clubId) {
            $query->where('club_id', $clubId);
        });
    }
    public function getStats($clubId)
    {
        $total = $this->clubSessionQuery($clubId)->count();

        $scheduled = $this->clubSessionQuery($clubId)->where('status', SessionModel::STATUS_SCHEDULED)->count();
        $ongoing = $this->clubSessionQuery($clubId)->where('status', SessionModel::STATUS_ONGOING)->count();
        $completed = $this->clubSessionQuery($clubId)->where('status', SessionModel::STATUS_COMPLETED)->count();
        $cancelled = $this->clubSessionQuery($clubId)->where('status', SessionModel::STATUS_CANCELLED)->count();
        $postponed = $this->clubSessionQuery($clubId)->where('status', SessionModel::STATUS_POSTPONED)->count();

        $effectiveSessions = $this->clubSessionQuery($clubId)->clone()->whereIn('status', [
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
        $lateSessions = $this->clubSessionQuery($clubId)->whereNotNull('actual_start_time')
            ->whereColumn('actual_start_time', '>', 'start_time')
            ->count();


        // durée moyenne réelle (minutes)
        $avgDuration = $this->clubSessionQuery($clubId)->whereNotNull('actual_start_time')
            ->whereNotNull('actual_end_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time)) as avg')
            ->value('avg');


        // taux remplacement instructeur
        $replacementRate = $total > 0
            ? round(($this->clubSessionQuery($clubId)->whereNotNull('replacement_instructor_id')->count() / $total) * 100, 2)
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
