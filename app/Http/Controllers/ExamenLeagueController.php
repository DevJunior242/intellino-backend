<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ExamenLeague;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamenLeagueReq;

class ExamenLeagueController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $leagueID = $user->current_league_id;
        $examenLeagues = ExamenLeague::withCount('students')
            ->where('league_id', $leagueID)->get();
        Log::info('examenLeagues', [$examenLeagues]);
        return response()->json($examenLeagues);
    }

    public function store(StoreExamenLeagueReq $request)
    {
        $validated = $request->validated();
        $leagueID = auth()->user()->current_league_id;

        $examenLeague = ExamenLeague::create($validated + [
            'league_id' => $leagueID,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'examen league created',
            'examenLeague' => $examenLeague,
        ]);
    }

    public function getClubExams(Request $request)
    {
        $club = Club::findOrFail($request->validated_club_id);
        Log::info('club', [$club]);
        $examens = ExamenLeague::where('league_id', $club->league_id)->get();
        return response()->json($examens);
    }
}
