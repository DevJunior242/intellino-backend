<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\storeMatchReq;
use App\Http\Requests\storeTournRequest;


class TournamentController extends Controller
{

    public function index()
    {
        //retourner les tournois par club avec pagination
        $user = auth()->user();
        $tournaments = Tournament::where('club_id', $user->club_id)
            ->orderBy('created_at', 'desc')
            ->latest()
            ->paginate(8);

        $tournaments->map(function ($t) {
            $today = now()->toDateString();

            if ($today < $t->start_date) {
                $t->status = 'upcoming';
            } elseif ($today >= $t->start_date && $today <= $t->end_date) {
                $t->status = 'ongoing';
            } else {
                $t->status = 'finished';
            }

            return $t;
        });

        return response()->json(
            [
                'success' => true,
                'message' => 'Tournaments list',
                'tournaments' => $tournaments
            ],
            200
        );
    }

    public function show(Tournament $tournament)
    {
        return response()->json([
            'tournament' => $tournament
        ]);
    }



    public function storeTournament(storeTournRequest $request)
    {
        $user = auth()->user();
        Log::info('club', [
            'club_id' => $user->club_id,
        ]);
        $tournament = Tournament::create([
            'club_id' => $user->club_id,
            ...$request->validated(),
        ]);

        return response()->json(
            [

                'success' => true,
                'message' => 'Tournament created successfully',
                'data' => $tournament
            ],
            201
        );
    }


    public function storeTournamentMatch(storeMatchReq $request)
    {
        $tournamentMatch = TournamentMatch::create($request->validated());
        return response()->json(
            [
                'success' => true,
                'message' => 'match du tournoi a été créé avec succès',
                'tournamentmatch' => $tournamentMatch
            ],
            201
        );
    }
}
