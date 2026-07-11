<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Saison;
use App\Models\Examen;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\ClubStudent;
use App\Models\StudentPayment;

class ClubAlertService
{
    public function getAlerts($activeId, $activeType)
    {
        return [
            $this->abonnementsExpirants($activeId),
            $this->evolutionEffectif($activeId),
            $this->presenceRisque($activeId),
            $this->prochainExamen($activeId, $activeType),
        ];
    }

    private function abonnementsExpirants($activeId)
    {
        $now = now();
        $limite = now()->addDays(15);

        $latestEndsAt = StudentPayment::where('club_id', $activeId)
            ->whereNotNull('ends_at')
            ->selectRaw('student_id, MAX(ends_at) as last_ends_at')
            ->groupBy('student_id')
            ->get()
            ->filter(function ($row) use ($now, $limite) {
                return $row->last_ends_at
                    && Carbon::parse($row->last_ends_at)->between($now, $limite);
            });

        $studentIds = $latestEndsAt->pluck('student_id');

        return [
            'type' => 'abonnements_expirants',
            'count' => $studentIds->count(),
            'students' => Student::whereIn('id', $studentIds)->take(3)->pluck('fullname')->values(),
        ];
    }

    private function evolutionEffectif($activeId)
    {
        $saisonActive = Saison::where('active', true)->first();

        $query = ClubStudent::where('club_id', $activeId)
            ->when($saisonActive, fn($q) => $q->where('saison_id', $saisonActive->id));

        $nouveaux = (clone $query)
            ->where('is_active', true)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $decrocheurs = (clone $query)
            ->where('is_active', false)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            'type' => 'evolution_effectif',
            'count' => $nouveaux,
            'nouveaux' => $nouveaux,
            'decrocheurs' => $decrocheurs,
        ];
    }

    private function presenceRisque($activeId)
    {
        $depuis = now()->subDays(30);

        $attendances = Attendance::where('club_id', $activeId)
            ->whereHas('session', fn($q) => $q->where('session_date', '>=', $depuis))
            ->get(['student_id', 'status']);

        $total = $attendances->count();
        $presents = $attendances->where('status', 'present')->count();
        $taux = $total > 0 ? round(($presents / $total) * 100, 1) : 0;

        $atRiskIds = $attendances->groupBy('student_id')
            ->filter(fn($items) => $items->where('status', 'present')->isEmpty())
            ->keys();

        return [
            'type' => 'presence_risque',
            'count' => $atRiskIds->count(),
            'rate' => $taux,
            'students' => Student::whereIn('id', $atRiskIds)->take(3)->pluck('fullname')->values(),
        ];
    }

    private function prochainExamen($activeId, $activeType)
    {
        $examen = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->whereBetween('start_date', [now(), now()->addDays(30)])
            ->withCount('candidates')
            ->orderBy('start_date')
            ->first();

        return [
            'type' => 'examen_grade',
            'count' => $examen?->candidates_count ?? 0,
            'date' => $examen?->start_date,
        ];
    }
}
