<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Examen;
use App\Models\SessionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProgrammeController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->attributes->get('organisateur_id');
        $range = $request->get('range', 'today');

        if ($range === 'week') {
            $start = now()->startOfDay();
            $end = now()->addDays(7)->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $sessions = SessionModel::whereHas('course', function ($q) use ($orgId) {
            $q->where('club_id', $orgId);
        })
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', SessionModel::STATUS_CANCELLED)
            ->get();

        $examens = Examen::with('currentGrade')
            ->where('organisateur_id', $orgId)
            ->whereBetween('start_date', [$start, $end])
            ->where('status', '!=', Examen::STATUS_CANCELLED)
            ->get();

        $programmes = collect();

        foreach ($sessions as $s) {
            $programmes->push([
                'id' => $s->id,
                'type' => 'cours',
                'title' => $s->title,
                'datetime' => $s->session_date . ' ' . ($s->start_time ?? $s->replacement_start_time),
                'end_datetime' => $s->session_date . ' ' . ($s->end_time ?? $s->replacement_end_time),
                'status' => $s->status,
            ]);
        }

        foreach ($examens as $e) {
            $programmes->push([
                'id' => $e->id,
                'type' => 'examen',
                'title' => 'Examen grade',
                'grade' => $e->currentGrade ? $e->currentGrade->name : null,
                'datetime' => $e->start_date . ' ' . ($e->start_time ?? $e->replacement_start_time),
                'end_datetime' => $e->end_date . ' ' . ($e->end_time ?? $e->replacement_end_time),
                'status' => $e->status,
            ]);
        }

        $programmes = $programmes
            ->sortBy('datetime')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $programmes
        ]);
    }
}
