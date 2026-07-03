<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Combat;
use App\Models\JudgeVote;
use App\Models\CombatAction;
use Illuminate\Http\Request;
use App\Events\TatamiUpdated;
use App\Models\RotationArbitre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class CombatActionController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validation
        $data = $request->validate([
            'combat_id'        => 'required|uuid|exists:combats,id',
            'juge_numero'      => 'required|integer|between:1,4',
            'type' => 'required|in:yuko,waza_ari,ippon,penalite,hansoku,hantei',
            'combattant'       => 'required|in:aka,ao',
            'valeur'           => 'required|integer|min:0|max:3',
            'client_timestamp' => 'nullable|string'
        ]);

        $combat = Combat::where('id', $data['combat_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // 2. Le combat doit être actif
        if (!in_array($combat->status, [1, 3])) {
            return response()->json(['message' => 'Le combat n\'est pas actif.'], 422);
        }

        //temps écoulé
        if ($combat->yame_at) {
            $combat->load('configNotation');
            $duree = $combat->configNotation->duration * 60;

            if ($combat->temps_ecoule >= $duree && !in_array($data['type'], ['hantei', 'hansoku'])) {
                return response()->json(['message' => 'Temps écoulé, votes non autorisés'], 422);
            }
        }
        // 3. Vérifier la rotation active de l'arbitre connecté
        $rotation = RotationArbitre::where('config_notation_id', $combat->config_notation_id)
            ->whereHas('arbitreCompetition', fn($q) => $q->where('user_id', auth()->id()))
            ->where('actif', true)
            ->first();

        if (!$rotation) {
            return response()->json(['message' => 'Non autorisé ou aucune rotation active'], 422);
        }

        $serverTime = now();
        $clientTime = Carbon::parse($data['client_timestamp']);
        $decalage   = abs($serverTime->diffInSeconds($clientTime));

        // 1. Temps de référence intelligent
        $clickTime = $decalage <= 10 ? $clientTime : $serverTime;

        // 2. Fenêtre adaptative selon le décalage détecté
        $windowBase    = config('kumite.window_seconds', 2);
        $windowSeconds = $windowBase + min($decalage, 5); // max +5s de bonus

        //hantei

        if ($data['type'] === 'hantei') {
            $estSuperviseur = RotationArbitre::where('config_notation_id', $combat->config_notation_id)
                ->whereHas('arbitreCompetition', fn($q) => $q->where('user_id', auth()->id()))
                ->where('est_superviseur', true)
                ->exists();

            if (!$estSuperviseur) {
                return response()->json(['message' => 'Seul le superviseur peut donner un hansoku direct'], 403);
            }

            // Validation directe sans vote
            $this->ValiderHantei($combat, $data['combattant']);

            broadcast(new TatamiUpdated($combat->config_notation_id));
            return response()->json(['success' => true, 'action_validated' => true]);
        }

        if ($data['type'] === 'hansoku') {
            $estSuperviseur = RotationArbitre::where('config_notation_id', $combat->config_notation_id)
                ->whereHas('arbitreCompetition', fn($q) => $q->where('user_id', auth()->id()))
                ->where('est_superviseur', true)
                ->exists();

            if (!$estSuperviseur) {
                return response()->json(['message' => 'Seul le superviseur peut donner un hansoku direct'], 403);
            }

            // Validation directe sans vote
            $this->appliquerHansoku($combat, $data['combattant']);

            broadcast(new TatamiUpdated($combat->config_notation_id));
            return response()->json(['success' => true, 'action_validated' => true]);
        }
        return DB::transaction(function () use ($combat, $data, $rotation, $clickTime, $windowSeconds) {

            // 4. Vérifier si cette action n'a pas déjà été validée récemment (anti-doublon)
            $alreadyProcessed = CombatAction::where('combat_id', $combat->id)
                ->where('combattant', $data['combattant'])
                ->where('type', $data['type'])
                ->where('signale_a', '>=', $clickTime->copy()->subSeconds($windowSeconds))
                ->exists();

            if ($alreadyProcessed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action déjà enregistrée, doublon ignoré.'
                ], 422);
            }

            // 5. Vérifier que ce juge n'a pas déjà voté pour cette action dans la fenêtre
            $jugeDejaVote = JudgeVote::where('combat_id', $combat->id)
                ->where('juge_numero', $data['juge_numero'])
                ->where('combattant', $data['combattant'])
                ->where('type', $data['type'])
                ->whereBetween('clicked_at', [
                    $clickTime->copy()->subSeconds($windowSeconds),
                    $clickTime->copy()->addSeconds($windowSeconds)
                ])
                ->exists();

            if ($jugeDejaVote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vote déjà enregistré pour ce juge.'
                ], 422);
            }

            // 6. Enregistrer le vote du juge
            $currentVote = JudgeVote::create([
                'combat_id'   => $combat->id,
                'juge_numero' => $data['juge_numero'],
                'combattant'  => $data['combattant'],
                'type'        => $data['type'],
                'clicked_at'  => $clickTime,
            ]);

            // 7. Chercher un accord avec UN AUTRE juge (filtre juge_numero != ) 
            $matchingVote = JudgeVote::where('combat_id', $combat->id)
                ->where('juge_numero', '!=', $data['juge_numero'])
                ->where('combattant', $data['combattant'])
                ->where('type', $data['type'])
                ->whereBetween('clicked_at', [
                    $clickTime->copy()->subSeconds($windowSeconds),
                    $clickTime->copy()->addSeconds($windowSeconds)
                ])
                ->first();


            if ($matchingVote) {
                // ACCORD TROUVÉ : supprimer les deux votes et valider l'action
                JudgeVote::whereIn('id', [$currentVote->id, $matchingVote->id])->delete();

                //  Nettoyer aussi les vieux votes expirés pour ce combat
                JudgeVote::where('combat_id', $combat->id)
                    ->where('clicked_at', '<', $clickTime->copy()->subSeconds($windowSeconds))
                    ->delete();
                if ($data['type'] === 'penalite') {
                    $this->traiterPenaliteAutomatique($combat, $data, $rotation);
                } else {
                    $this->validerPointOfficiel($combat, $data, $rotation, $clickTime, $windowSeconds);
                }

                $combat->refresh();
                broadcast(new TatamiUpdated($combat->config_notation_id));

                return response()->json(['success' => true, 'action_validated' => true]);
            }

            // Pas encore de deuxième juge, on attend
            return response()->json([
                'success'          => true,
                'action_validated' => false,
                'message'          => 'En attente du second juge...'
            ]);
        });
    }

    private function validerPointOfficiel($combat, $data, $rotation, $clickTime, $windowSeconds)
    {
        // Créer l'action définitive et propre (Directement validée !)
        CombatAction::create([
            'id'                  => \Illuminate\Support\Str::uuid(),
            'combat_id'           => $combat->id,
            'rotation_arbitre_id' => $rotation->id,
            'combattant'          => $data['combattant'],
            'type'                => $data['type'], // yuko, waza_ari, ippon
            'valeur'              => $data['valeur'],
            'signale_a'           => now(),
            'temps_match'         => $combat->temps_ecoule,
        ]);

        // Incrémenter le score du combattant
        $champ = $data['combattant'] === 'aka' ? 'score_final_aka' : 'score_final_ao';
        $combat->increment($champ, $data['valeur']);
        // GESTION DU SENSHU (Avantage du premier point) 
        $combattantAdverse = $data['combattant'] === 'aka' ? 'ao' : 'aka';

        // Vérifier si l'adversaire a marqué dans la même fenêtre de 2 secondes
        $aMarqueEnMemeTemps = CombatAction::where('combat_id', $combat->id)
            ->where('combattant', $combattantAdverse)
            ->whereBetween('signale_a', [
                $clickTime->copy()->subSeconds($windowSeconds),
                $clickTime->copy()->addSeconds($windowSeconds)
            ])
            ->exists();

        if (!$aMarqueEnMemeTemps && !$combat->senshu_id) {
            $combat->update([
                'senshu_id' => $data['combattant'] === 'aka'
                    ? $combat->inscription_aka_id
                    : $combat->inscription_ao_id
            ]);
        }

        // 4. SÉCURITÉ : RÈGLE DES 8 POINTS D'ÉCART (Victoire Directe)
        $scoreAka = $combat->score_final_aka;
        $scoreAo  = $combat->score_final_ao;
        $ecart    = abs($scoreAka - $scoreAo);

        if ($ecart >= 8) {
            // Trouver le UUID du vainqueur réel
            $vainqueurId = $scoreAka > $scoreAo
                ? $combat->inscription_aka_id
                : $combat->inscription_ao_id;

            // On arrête le combat immédiatement
            $combat->update([
                'vainqueur_id'   => $vainqueurId,
                'type_victoire'  => 'Points',
                'yame_at'        => now(),
            ]);
        }
    }

    private function traiterPenaliteAutomatique($combat, $data, $rotation)
    {
        // Compter les pénalités existantes dans combat_actions
        $nb = CombatAction::where('combat_id', $combat->id)
            ->where('combattant', $data['combattant'])
            ->where('type', 'penalite')
            ->count();

        // 4ème penalite = hansoku  élimination
        if ($nb >= 3) {
            $this->appliquerHansoku($combat, $data['combattant']);
            return;
        }

        // Créer la pénalité officielle
        CombatAction::create([
            'id'                  => \Illuminate\Support\Str::uuid(),
            'combat_id'           => $combat->id,
            'rotation_arbitre_id' => $rotation->id,
            'combattant'          => $data['combattant'],
            'type'                => $data['type'],
            'valeur'              => 0,
            'signale_a'           => now(),
            'temps_match'         => $combat->temps_ecoule,
        ]);
    }

    private function appliquerHansoku($combat, $combattant)
    {
        $scoreAka = $combattant === 'aka' ? 0 : $combat->score_final_aka;
        $scoreAo  = $combattant === 'ao'  ? 0 : $combat->score_final_ao;

        if ($combattant === 'aka') {
            $scoreAo = max(4, $combat->score_final_ao);
        } else {
            $scoreAka = max(4, $combat->score_final_aka);
        }

        $combat->update([
            'vainqueur_id'    => $combattant === 'aka'
                ? $combat->inscription_ao_id
                : $combat->inscription_aka_id,
            'type_victoire'   => 'hansoku',
            'score_final_aka' => $scoreAka,
            'score_final_ao'  => $scoreAo,
        ]);

        broadcast(new TatamiUpdated($combat->config_notation_id));
    }
    private function ValiderHantei(Combat $combat, string $combattant): void
    {
        $vainqueurId = $combattant === 'aka'
            ? $combat->inscription_aka_id
            : $combat->inscription_ao_id;

        $combat->update([
            'vainqueur_id'  => $vainqueurId,
            'type_victoire' => 'hantei',
        ]);

        broadcast(new TatamiUpdated($combat->config_notation_id));
    }
}
