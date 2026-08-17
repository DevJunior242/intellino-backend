<?php

namespace App\Http\Controllers;

use App\Models\Combat;
use Illuminate\Http\Request;
use App\Models\ConfigNotation;
use App\Services\KataNotationService;

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

        // Détail des notes des deux camps par juge (Kata uniquement) — pour
        // l'écran public, une seule grille AKA/AO au lieu de les afficher
        // séparément puis un total sans lien visible entre les deux.
        $votesJuges = ($combat && $combat->configNotation->estKata())
            ? app(KataNotationService::class)->detailVotesJuges($combat)
            : [];

        return response()->json([
            'combat'      => $combat,
            'votes_juges' => $votesJuges,
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
