<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Services\ClubAlertService;
use Illuminate\Support\Facades\Log;

class AdminClubController extends Controller
{
    public function presence(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $user = auth()->user();
        $attendances = Attendance::with(['student.clubs:id,name,logo', 'session'])
            ->where('club_id', $activeId)
            ->get();

        // Student::clubs() est un many-to-many (pivot club_students) ;
        // le frontend attend un `club` singulier (le premier club de l'élève)
        $attendances->each(function ($attendance) {
            if ($attendance->student) {
                $attendance->student->setRelation('club', $attendance->student->clubs->first());
            }
        });
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

    public function alerts(Request $request, ClubAlertService $alertService)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        return response()->json([
            'success' => true,
            'alerts' => $alertService->getAlerts($activeId, $activeType),
        ]);
    }

    public function getRoles(Request $request)
    {
        $roleInclus = ['admin', 'instructeur', 'secretaire', 'karateka'];

        $roles = Role::whereIn('name', $roleInclus)->get();
        Log::info('roles', ['roles' => $roles]);
        return response()->json(['success' => true, 'roles' => $roles]);
    }
}
