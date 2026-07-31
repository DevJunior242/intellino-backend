<?php

namespace App\Http\Controllers;

use App\Models\Combat;
use Illuminate\Http\Request;
use App\Models\ConfigNotation;

class VuePubController extends Controller
{
    public function combatEnCours(ConfigNotation $config)
    {
        $combat = Combat::where('config_notation_id', $config->id)
            ->whereIn('status', [1, 2, 3])
            ->orderByRaw("FIELD(status, 1, 3, 2)")
            ->orderByDesc('updated_at')
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.organisateur:id,name',
                'inscriptionAka.kataTeam:id,inscription_id,nom',

                'inscriptionAka.competition.category',
                'inscriptionAo.athlete',
                'inscriptionAo.organisateur:id,name',
                'inscriptionAo.kataTeam:id,inscription_id,nom',
                'inscriptionAo.competition.category',
                'configNotation',
                'actions',
            ])
            ->first();

        return response()->json([
            'combat' => $combat
        ]);
    }

    public function nextCombat(ConfigNotation $config)
    {
        $combat = Combat::where('config_notation_id', $config->id)
            ->where('status', 0) // en attente
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.kataTeam:id,inscription_id,nom',
                'inscriptionAo.athlete',
                'inscriptionAo.kataTeam:id,inscription_id,nom',
            ])
            ->orderBy('ordre')
            ->first();

        return response()->json([
            'combat' => $combat
        ]);
    }
}
