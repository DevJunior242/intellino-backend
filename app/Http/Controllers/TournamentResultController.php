<?php

namespace App\Http\Controllers;

use App\Models\ParentModel;
use Illuminate\Http\Request;
use App\Models\Tournament_result;
use App\Http\Requests\StoreResultRequest;

class TournamentResultController extends Controller
{

    public function index()
    {
        $user = auth()->user();
        $results = Tournament_result::with([
            'tournament:id,name',
            'student:id,fullname',
            'medal:id,name'
        ])
            ->select(
                'id',
                'tournament_id',
                'student_id',
                'medal_id',
                'score'
            )
            ->whereHas('tournament', function ($q) use ($user) {
                $q->where('club_id', $user->club_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json([
            'success' => true,
            'message' => 'result list',
            'results' => $results
        ]);
    }

    public function parentTournamenrResult()
    {
        $user = auth()->user();

        $parent = ParentModel::where('user_id', $user->id)->first();
        $studentIds = $parent->students()->pluck('students.id');
        $results = Tournament_result::with([
            'tournament:id,name',
            'student:id,fullname',
            'medal:id,name'
        ])
            ->select(
                'tournament_id',
                'student_id',
                'medal_id',
                'score'
            )

            ->whereIn('student_id', $studentIds)
            ->get();
        return response()->json([
            'success' => true,
            'message' => 'result list for parent',
            'results' => $results
        ]);
    }
    // Stocker le résultat du tournoi
    public function storeTournamentResult(StoreResultRequest $request)
    {
        $results = Tournament_result::create($request->validated());
        return response()->json(
            [
                'success' => true,
                'message' => 'result du tournoi a été créé avec succès',
                'result' => $results
            ],
            201
        );
    }
}
