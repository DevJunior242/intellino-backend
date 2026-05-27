<?php

namespace App\Http\Controllers;

use App\Models\Combat;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use App\Http\Controllers\Controller;

class CombatController extends Controller
{
    public function combatEnCours(ConfigNotation $config)
    {
        $combat = Combat::where('config_notation_id', $config->id)
            ->where('status', 1)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.competition.category',
                'inscriptionAo.athlete',
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
                'inscriptionAo.athlete',
            ])
            ->orderBy('ordre')
            ->first();

        return response()->json([
            'combat' => $combat
        ]);
    }

    public function combatSuivant(ConfigNotation $config)
    {
        // Terminer le combat en cours
        $combatActuel = Combat::where('config_notation_id', $config->id)
            ->where('status', 1)
            ->first();

        if ($combatActuel) {
            $combatActuel->update(['status' => 2]); // terminé
        }

        // Lancer le combat suivant
        $suivant = Combat::where('config_notation_id', $config->id)
            ->where('status', 0)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.competition.category',
                'inscriptionAo.athlete',
                'inscriptionAo.competition.category',
            ])
            ->orderBy('ordre')
            ->first();

        if ($suivant) {
            $suivant->update(['status' => 1]);
        }

        broadcast(new TatamiUpdated($config->id));

        return response()->json([
            'success' => true,
            'combat'  => $suivant,
        ]);
    }
    public function bracket(ConfigNotation $config)
    {
        $combats = Combat::where('config_notation_id', $config->id)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAo.athlete',
            ])
            ->orderBy('ordre')
            ->get()
            ->groupBy('etape');

        return response()->json([
            'success' => true,
            'data'    => $combats,
        ]);
    }


    public function lancerSeanceKumite(ConfigNotation $config)
    {
        // Vérifier qu'aucun combat est déjà en cours
        $dejaEnCours = Combat::where('config_notation_id', $config->id)
            ->where('status', 1)
            ->exists();

        if ($dejaEnCours) {
            return response()->json([
                'message' => 'Un combat est déjà en cours'
            ], 422);
        }

        // Lancer le premier combat
        $premier = Combat::where('config_notation_id', $config->id)
            ->where('status', 0)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.competition.category',
                'inscriptionAo.athlete',
                'inscriptionAo.competition.category',
            ])
            ->orderBy('ordre')
            ->first();

        if (!$premier) {
            return response()->json([
                'message' => 'Aucun combat disponible'
            ], 422);
        }

        $premier->update(['status' => 1]);

        broadcast(new TatamiUpdated($config->id));

        return response()->json([
            'success' => true,
            'combat'  => $premier,
        ]);
    }

    public function startChrono(Combat $combat)
    {
        $combat->update([
            'hajime_at' => now(),
            'yame_at'   => null,
        ]);
        broadcast(new TatamiUpdated($combat->config_notation_id));
        return response()->json(['success' => true]);
    }

    public function stopChrono(Combat $combat)
    {
        // Calculer le temps écoulé depuis le dernier hajime
        $tempsEcoule = $combat->temps_ecoule;
        if ($combat->hajime_at) {
            $tempsEcoule += now()->diffInSeconds($combat->hajime_at);
        }

        $combat->update([
            'yame_at'      => now(),
            'temps_ecoule' => $tempsEcoule,
        ]);

        broadcast(new TatamiUpdated($combat->config_notation_id));
        return response()->json(['success' => true]);
    }

    
}
