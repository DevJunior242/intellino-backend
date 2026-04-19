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
        $clubId = $request->attributes->get('club_id');
        $range = $request->get('range', 'today');

        if ($range === 'week') {
            $start = now()->startOfDay();
            $end = now()->addDays(7)->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $sessions = SessionModel::whereHas('course', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->get();

        $examens = Examen::with('currentGrade')
            ->where('club_id', $clubId)
            ->whereBetween('start_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->get();
        Log::info('examens', ['examens' => $examens]);

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
            Log::info('examen', ['examen' => $e->currentGrade]);
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
