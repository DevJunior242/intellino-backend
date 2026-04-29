<?php

namespace App\Http\Controllers;


use App\Models\Activity;
use Illuminate\Http\Request;
use App\Services\ClubDashService;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    protected $statsService;

    public function __construct(ClubDashService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function stats(Request $request)
    {
        $data = $this->statsService->getStatsByUserRole(
            $request->user(),
            $request->attributes->get('organisateur_id'),
            $request->attributes->get('organisateur_type'),
            $request->attributes->get('role')
        );

        if (isset($data['message'])) {
            return response()->json($data, 403);
        }

        return response()->json($data);
    }

    public function getDashboardActivity(Request $request)
    {
        $activeOrgId = $request->attributes->get('organisateur_id');
        $activeOrgType = $request->attributes->get('organisateur_type');

        if (!$activeOrgId) {
            return response()->json(['message' => 'Aucune organisation active trouvée'], 404);
        }

        $activities = Activity::with('user:id,fullname')
            ->select('*', DB::raw("DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as formatted_date"))
            ->where('organisateur_id', $activeOrgId)
            ->where('organisateur_type', $activeOrgType)->latest()
            ->take(6)
            ->get();

        return response()->json($activities);
    }
}
