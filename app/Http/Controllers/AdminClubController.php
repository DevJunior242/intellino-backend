<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AdminClubController extends Controller
{
    public function presence(Request $request)
    {
        $user = auth()->user();
        $attendances = Attendance::with(['student.club:id,name,logo', 'session'])
            ->whereHas('student', function ($q) use ($request) {
                $q->where('club_id', $request->validated_club_id);
            })
            ->get();
        $chartData = $attendances->groupBy('session.session_date')->map(function ($items, $sessionDate) {
            return [
                'session' => $sessionDate,
                'present' => $items->where('status', 'present')->count(),
                'absent' => $items->where('status', 'absent')->count(),
            ];
        })->values();
        return response()->json([
            'success' => true,
            'message' => 'presence list',
            'presences' => $attendances,
            'chartData' => $chartData
        ]);
    }
}
