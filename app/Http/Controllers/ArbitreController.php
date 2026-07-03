<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use App\Models\RotationArbitre;
use App\Models\ArbitreCompetition;

class ArbitreController extends Controller
{
    public function sInscrire(Competition $competition, ConfigNotation $config)
    {
        $estArbitre = $competition->league->arbitres()
            ->where('user_id', auth()->id())
            ->exists();

        if (!$estArbitre) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas arbitre dans cette ligue',
            ], 422);
        }
        $dejaInscrit = ArbitreCompetition::where('competition_id', $competition->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($dejaInscrit) {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà arbitre dans cette compétition',
            ], 422);
        }

        $arbitre = ArbitreCompetition::firstOrCreate(
            [
                'user_id'        => auth()->id(),
                'competition_id' => $competition->id,
            ],

        );
        broadcast(new TatamiUpdated($config->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Inscription confirmée',
            'data'    => $arbitre,
        ], 201);
    }

    // Récupérer les arbitres de l'événement qui ne sont pas encore sur ce plateau
    public function getDisponibles($evenementId)
    {
        $idsExclus = RotationArbitre::whereHas('arbitreCompetition', function ($q) use ($evenementId) {
            $q->where('evenement_id', $evenementId);
        })
            ->where(function ($q) {
                $q->whereNotNull('poste')
                    ->orWhere('est_superviseur', true);
            })
            ->pluck('arbitre_competition_id')
            ->unique()
            ->toArray();

        $arbitres = ArbitreCompetition::with('user')
            ->where('evenement_id', $evenementId)
            ->whereNotIn('id', $idsExclus)
            ->get();

        return response()->json($arbitres);
    }
}
