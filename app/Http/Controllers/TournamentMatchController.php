<?php

namespace App\Http\Controllers;

use App\Models\ParentModel;
use Illuminate\Http\Request;
use App\Models\TournamentMatch;
use App\Models\Tournament_result;

class TournamentMatchController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $matches = TournamentMatch::with([
            'student:id,fullname',
            'tournament:id,name,club_id'
        ])->select(
            'id',
            'student_id',
            'tournament_id',
            'category',
            'round',
            'opponent',
            'result'
        )
            ->whereHas('tournament', function ($q) use ($user) {
                $q->where('club_id', $user->club_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'success' => true,
            'message' => 'matchs list',
            'matches' => $matches
        ]);
    }

    public function parentMatches()
    {
        $user = auth()->user();

        $parent = ParentModel::where('user_id', $user->id)->first();
        $studentIds = $parent->students()->pluck('students.id');
        $matches = TournamentMatch::with(['student:id,fullname', 'tournament:id,name'])
            ->whereIn('student_id', $studentIds)
            ->orderBy('created_at', 'desc')
            ->get();


        return response()->json([
            'success' => true,
            'message' => 'matchs list for parent',
            'matches' => $matches
        ]);
    }
    // Matchs par tournoi
    public function byTournament($id)
    {
        $matches = TournamentMatch::where('tournament_id', $id)
            ->select(
                'id',
                'student_id',
                'category',
                'round',
                'opponent',
                'result'
            )
            ->orderBy('round', 'asc')
            ->get();

        return response()->json($matches);
    }

    public function tournamenrResult()
    {
        $results = Tournament_result::with('tournament:id,name', 'student:id,fullname', 'medal:id,name')
            ->select(
                'tournament_id',
                'student_id',
                'medal_id',
                'score'
            )
            ->get();
    }
}
