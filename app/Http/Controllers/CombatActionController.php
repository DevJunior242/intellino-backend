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
            'type' => 'required|in:yuko,waza_ari,ippon,chukoku,keikoku,hansoku_chui,hansoku',
            'combattant'       => 'required|in:aka,ao',
            'valeur'           => 'required|integer|min:0|max:3',
            'client_timestamp' => 'nullable'
        ]);

        $combat = Combat::where('id', $data['combat_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // 2. Le combat doit être actif
        if ($combat->status !== 1) {
            return response()->json(['message' => 'Le combat n\'est pas actif.'], 422);
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
                $penaliteTypes = ['chukoku', 'keikoku', 'hansoku_chui', 'hansoku'];
                if (in_array($data['type'], $penaliteTypes)) {
                    $this->traiterPenaliteAutomatique($combat, $data, $rotation);
                } else {
                    $this->validerPointOfficiel($combat, $data, $rotation);
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

    private function validerPointOfficiel($combat, $data, $rotation)
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
            'temps_match'         => $combat->temps_ecoule, // On fige la seconde du chrono
        ]);

        // Incrémenter le score du combattant
        $champ = $data['combattant'] === 'aka' ? 'score_final_aka' : 'score_final_ao';
        $combat->increment($champ, $data['valeur']);
        // GESTION DU SENSHU (Avantage du premier point)
        if (is_null($combat->senshu_id)) {

            // Sécurité Anti-Simultané : On vérifie si l'ADVERSAIRE a aussi un vote validé 
            // à la même seconde exacte dans l'historique de ce combat
            $combattantAdverse = $data['combattant'] === 'aka' ? 'ao' : 'aka';

            $aMarqueEnMemeTemps = CombatAction::where('combat_id', $combat->id)
                ->where('combattant', $combattantAdverse)
                ->where('temps_match', $combat->temps_ecoule)
                ->exists();

            // Si l'adversaire n'a pas marqué à la même seconde, on attribue le Senshu définitivement
            if (!$aMarqueEnMemeTemps) {
                $combat->update([
                    'senshu_id' => $data['combattant'] === 'aka'
                        ? $combat->inscription_aka_id
                        : $combat->inscription_ao_id
                ]);
            }
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
                'status'         => 2, // 2 = Terminé
                'vainqueur_id'   => $vainqueurId,
                'type_victoire'  => 'Points', // Victoire par supériorité de points (Écart de 8)
                'yame_at'        => now(),    // On enregistre le stop final du chrono
            ]);
        }
    }

    private function traiterPenaliteAutomatique($combat, $data, $rotation)
    {
        // Compter les pénalités existantes dans combat_actions
        $nb = CombatAction::where('combat_id', $combat->id)
            ->where('combattant', $data['combattant'])
            ->whereIn('type', ['chukoku', 'keikoku', 'hansoku_chui', 'hansoku'])
            ->count();

        $niveaux = ['chukoku', 'keikoku', 'hansoku_chui', 'hansoku'];
        $typePenalite = $niveaux[$nb] ?? 'hansoku';

        // Créer la pénalité officielle
        CombatAction::create([
            'id'                  => \Illuminate\Support\Str::uuid(),
            'combat_id'           => $combat->id,
            'rotation_arbitre_id' => $rotation->id,
            'combattant'          => $data['combattant'],
            'type'                => $typePenalite,
            'valeur'              => 0,
            'signale_a'           => now(),
            'temps_match'         => $combat->temps_ecoule,
        ]);

        // Si le combattant atteint le Hansoku -> Disqualification et victoire de l'autre
        if ($typePenalite === 'hansoku') {
            $vainqueur = $data['combattant'] === 'aka'
                ? $combat->inscription_ao_id
                : $combat->inscription_aka_id;

            $combat->update([
                'vainqueur_id'  => $vainqueur,
                'type_victoire' => 'hansoku',
                'status'        => 2, // Terminé
            ]);
        }
    }
}
