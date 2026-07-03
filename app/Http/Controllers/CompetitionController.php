<?php

namespace App\Http\Controllers;


use App\Models\Competition;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompetitionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CompetitionController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        try {
            $epreuves = Competition::with(['discipline:id,nom', 'evenement', 'category:id,nom,sexe', 'niveau:id,nom'])
                ->whereHas('evenement', function ($query) use ($activeId, $activeType) {
                    $query->where('organisateur_id', $activeId)
                        ->where('organisateur_type', $activeType);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $epreuves
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des épreuves',
                'data' => null
            ], 422);
        }
    }





    public function store(CompetitionRequest $request)
    {

        $this->authorize('create', Competition::class);
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
            ->where('status', Competition::STATUT_EN_COURS)
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
            ->where('status', Competition::STATUT_EN_COURS)
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



    // Ouvrir les inscriptions
    public function ouvrir(Competition $competition)
    {
        $this->authorize('open', $competition);
        $competition->update([
            'status' => 1,

        ]);

        return response()->json([
            'event' => $competition,
            'message' => 'Les inscriptions sont maintenant ouvertes !'
        ]);
    }

    // Clôturer manuellement
    public function cloturer(Competition $competition)
    {
        $this->authorize('close', $competition);
        $competition->update(['status' => 2]);

        return response()->json(['message' => 'Les inscriptions ont été clôturées.']);
    }
}
