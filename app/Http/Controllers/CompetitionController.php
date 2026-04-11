<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use App\Models\Competition;
use Illuminate\Http\Request;
use App\Models\ArbitreCompetition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CompetitionRequest;
use App\Services\ArbitreVerificationService;

class CompetitionController extends Controller
{
    public function index()
    {
        try {
            $epreuves = Competition::with(['discipline', 'evenement', 'category', 'niveau'])
                // ->whereHas('evenement', function ($query) {
                //     $query->where('statut', 'actif');
                // })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $epreuves
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des épreuves : ' . $e->getMessage()
            ], 500);
        }
    }





    public function store(CompetitionRequest $request)
    {


        $competition = Competition::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Compétition créée avec succès',
            'data' => $competition
        ], 201);
    }

    public function getCompetitions()
    {
        $competitions = Competition::with([
            'niveau',
            'category',
            'discipline',
            'evenement' => function ($query) {
                $query->select('id', 'nom');
            }
        ])
            ->where('statut', 'ouverte')
            ->get();

        return response()->json($competitions);
    }

    public function getActiveCompetition(Request $request)
    {
        $user = auth()->user();

        // On récupère la compétition 'ouverte' la plus récente pour l'organisation du user
        $competition = Competition::with(['niveau', 'category'])
            ->withCount('inscriptions')
            ->where('organisateur_id', $user->current_league_id)
            ->where('organisateur_type', 'Ligue')
            ->where('statut', 'ouverte')
            ->latest()
            ->first();

        if (!$competition) {
            return response()->json([
                'message' => 'Aucune compétition ouverte actuellement',
                'data' => null
            ], 200);
        }

        return response()->json($competition);
    }
}
