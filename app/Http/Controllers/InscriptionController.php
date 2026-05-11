<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Evenement;
use App\Models\Competition;

use App\Models\Inscription;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInscriptionReq;

class InscriptionController extends Controller
{

    public function index(Request $request)
    {
        $request->validate([
            'competition_id' => 'required|exists:competitions,id'
        ]);

        // On récupère TOUTES les inscriptions pour CETTE compétition
        // On charge les relations pour avoir le nom du club et de l'athlète
        $inscriptions = Inscription::where('competition_id', $request->competition_id)
            ->with([
                'athlete:id,fullname,sex',
                'organisateur',
                'competition.category:id,nom,sexe',
                'competition.discipline:id,nom'
            ])
            ->orderBy('organisateur_id')
            ->get();

        return response()->json($inscriptions);
    }


    public function getEvenementsOuverts(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');

        $clubId = $activeId;

        $leagueId = Club::where('id', $clubId)->value('league_id');
        $evenements = Evenement::where(function ($q) use ($clubId, $leagueId) {
            $q->where(function ($q2) use ($clubId) {
                $q2->where('organisateur_type', 'Club')
                    ->where('organisateur_id', $clubId);
            })
                ->orWhere(function ($q2) use ($leagueId) {
                    $q2->where('organisateur_type', 'Ligue')
                        ->where('organisateur_id', $leagueId);
                });
        })
            ->with([
                'competitions' => function ($q) {
                    $q->with(['category:id,nom,sexe', 'discipline:id,nom', 'niveau:id,nom'])
                        ->withCount('inscriptions');
                }
            ])
            ->orderByDesc('created_at')->get();
        return response()->json([
            'success'    => true,
            'evenements' => $evenements,
        ]);
    }

    // Récupérer les inscriptions d'une épreuve pour un club
    public function parEpreuve(Competition $competition, Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $inscriptions = Inscription::where('competition_id', $competition->id)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->with(['athlete'])
            ->get();

        return response()->json([
            'success'      => true,
            'inscriptions' => $inscriptions,
            'epreuve'      => $competition->load(['category:id,nom,sexe', 'discipline:id,nom', 'niveau:id,nom']),
        ]);
    }

    // Inscrire un athlète à une épreuve
    public function store(StoreInscriptionReq $request)
    {
        $validated = $request->validated();

        // Vérifier doublon
        $existe = Inscription::where('competition_id', $validated['competition_id'])
            ->where('athlete_id', $validated['athlete_id'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Cet athlète est déjà inscrit à cette épreuve',
            ], 422);
        }

        // Ordre de passage automatique
        $dernierOrdre = Inscription::where('competition_id', $validated['competition_id'])
            ->lockForUpdate()
            ->max('ordre_passage') ?? 0;

        $inscription = Inscription::create([
            ...$validated,
            'ordre_passage'   => $dernierOrdre + 1,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Athlète inscrit avec succès',
            'inscription' => $inscription->load(['athlete']),
        ], 201);
    }

    // Désinscrire un athlète
    public function destroy(Inscription $inscription)
    {
        $inscription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscription annulée',
        ]);
    }
}
