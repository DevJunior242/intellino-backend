<?php

namespace App\Http\Controllers;

use App\Models\TatamiJudges;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class JudgeSessionController extends Controller
{
    public function getJudgeNumber(Request $request, ConfigNotation $config)
    {
        // 1. On récupère le token unique envoyé par le Front
        $judgeToken = $request->input('judge_token');

        if (!$judgeToken) {
            return response()->json(['message' => 'Token d\'identification manquant.'], 400);
        }

        $timeout = now()->subSeconds(30); // Un juge est déconnecté après 30s d'inactivité

        return DB::transaction(function () use ($judgeToken, $config, $timeout, $request) {

            // 2. NETTOYAGE : Supprimer les juges inactifs (sauf nous-mêmes)
            TatamiJudges::where('config_notation_id', $config->id)
                ->where('last_seen_at', '<', $timeout)
                ->where('judge_token', '!=', $judgeToken)
                ->delete();

            // 3. VÉRIFIER SI CE TOKEN A DÉJÀ UN NUMÉRO
            $me = TatamiJudges::where('config_notation_id', $config->id)
                ->where('judge_token', $judgeToken)
                ->first();

            if ($me) {
                // Toujours en vie ! On met à jour son heure de passage
                $me->update(['last_seen_at' => now()]);
                return response()->json(['juge_numero' => $me->juge_numero]);
            }

            // 4. TROUVER LES NUMÉROS OCCUPÉS (avec Lock pour éviter les doublons simultanés)
            $occupiedNumbers = TatamiJudges::where('config_notation_id', $config->id)
                ->lockForUpdate()
                ->pluck('juge_numero')
                ->toArray();

            // 5. ATTRIBUER LE PREMIER NUMÉRO LIBRE (De 1 à 4)
            $assignedNumber = null;
            for ($i = 1; $i <= 4; $i++) {
                if (!in_array($i, $occupiedNumbers)) {
                    $assignedNumber = $i;
                    break;
                }
            }

            // 6. SI PLUS DE PLACE
            if (is_null($assignedNumber)) {
                return response()->json([
                    'message' => 'Le tatami est complet (4 juges actifs).'
                ], 423);
            }

            // 7. ENREGISTRER LE NOUVEAU JUGE AVEC SON TOKEN
            TatamiJudges::create([
                'config_notation_id' => $config->id,
                'judge_token'        => $judgeToken,
                'ip_address'         => $request->ip(),
                'juge_numero'        => $assignedNumber,
                'last_seen_at'       => now(),
            ]);

            // 8. Notification temps réel
            broadcast(new TatamiUpdated($config->id));

            return response()->json(['juge_numero' => $assignedNumber]);
        });
    }
    public function getJudges(ConfigNotation $config)
    {
        $judges = TatamiJudges::where('config_notation_id', $config->id)
            ->get();
        return response()->json($judges);
    }

    public function resetSpecificJudge(ConfigNotation $config, $juge_numero)
    {
        $deleted = TatamiJudges::where('config_notation_id', $config->id)
            ->where('juge_numero', $juge_numero)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => "Le Juge $juge_numero n'était pas connecté."], 442);
        }

        broadcast(new TatamiUpdated($config->id));

        return response()->json([
            'success' => true,
            'message' => "La chaise du Juge $juge_numero a été libérée. Une nouvelle tablette peut s'y connecter."
        ]);
    }

    public function resetAllJudges(ConfigNotation $config)
    {
        TatamiJudges::where('config_notation_id', $config->id)->delete();

        broadcast(new TatamiUpdated($config->id));

        return response()->json([
            'success' => true,
            'message' => "Le tatami a été entièrement réinitialisé. Les 4 chaises de juges sont vides."
        ]);
    }
}
