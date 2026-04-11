<?php

namespace App\Services;

use App\Models\Poule;
use App\Models\Combat;
use App\Models\Inscription;
use Illuminate\Support\Str;
use App\Models\ConfigNotation;
use Illuminate\Support\Facades\DB;

class BracketService
{
    /**
     * Point d'entrée principal
     * Génère le tableau selon le format du tournoi
     */
    public function generer(ConfigNotation $config): void
    {
        $format = $config->kumiteFormat->code;

        DB::transaction(function () use ($format, $config) {
            match ($format) {
                'eliminatoire'        => $this->genererEliminatoire($config),
                'poules'              => $this->genererPoules($config),
                'poules_eliminatoire' => $this->genererPoulesEliminatoire($config),
                default               => throw new \Exception("Format non reconnu"),
            };
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT ÉLIMINATOIRE DIRECT
    // ─────────────────────────────────────────────────────────────────────────
    public function genererEliminatoire(ConfigNotation $config): void
    {
        $inscriptions = Inscription::where('config_notation_id', $config->id)->get()->shuffle();

        $nb = $inscriptions->count();

        if ($nb < 2) {
            throw new \Exception("Pas assez de combattants — minimum 2");
        }

        // Compléter à la puissance de 2 supérieure (2, 4, 8, 16...)
        $taille = $this->prochainesPuissanceDeDeux($nb);
        $byes   = $taille - $nb; // combattants exemptés au 1er tour

        // Construire le bracket de l'intérieur vers l'extérieur
        // On crée d'abord la finale, puis les demi-finales, etc.
        $combats = $this->construireBracket($config, $taille, $inscriptions->toArray(), $byes);
    }

    /**
     * Construire le bracket récursivement
     * Crée les combats de la finale vers le 1er tour
     */
    private function construireBracket(
        ConfigNotation $config,
        int $taille,
        array $inscriptions,
        int $byes
    ): array {
        $combatsParRound = [];
        $ordre           = 1;

        // Générer tous les rounds
        // taille=8 → rounds: [4 combats quart, 2 combats demi, 1 finale]
        $rounds = log($taille, 2); // ex: log(8,2) = 3

        // Créer les combats vides par round (finale en dernier)
        for ($round = 1; $round <= $rounds; $round++) {
            $nbCombats   = $taille / pow(2, $round);
            $etape       = $this->getEtape($round, $rounds);
            $roundCombats = [];

            for ($i = 0; $i < $nbCombats; $i++) {
                $combat = Combat::create([
                    'id'                  => Str::uuid(),
                    'competition_id'      => $config->competition_id,
                    'config_notation_id'  => $config->id,
                    'etape'               => $etape,
                    'ordre'               => $ordre++,
                    'statut'              => 0,
                    'score_final_aka'     => 0,
                    'score_final_ao'      => 0,
                ]);
                $roundCombats[] = $combat;
            }
            $combatsParRound[$round] = $roundCombats;
        }

        // Lier les combats entre rounds
        // Combat du round N → next_combat = combat du round N+1
        for ($round = 1; $round < $rounds; $round++) {
            $combatsActuels  = $combatsParRound[$round];
            $combatsSuivants = $combatsParRound[$round + 1];

            foreach ($combatsActuels as $index => $combat) {
                // Chaque paire de combats pointe vers le même combat suivant
                $indexSuivant = intdiv($index, 2);
                $combatSuivant = $combatsSuivants[$indexSuivant];

                // Position dans le combat suivant (aka ou ao)
                if ($index % 2 === 0) {
                    // Pair → source_aka du combat suivant
                    $combat->update([
                        'next_combat_id'      => $combatSuivant->id,
                    ]);
                    $combatSuivant->update([
                        'source_aka_combat_id' => $combat->id,
                    ]);
                } else {
                    // Impair → source_ao du combat suivant
                    $combat->update([
                        'next_combat_id'      => $combatSuivant->id,
                    ]);
                    $combatSuivant->update([
                        'source_ao_combat_id' => $combat->id,
                    ]);
                }
            }
        }

        // Assigner les combattants au 1er round
        $combatsPremierRound = $combatsParRound[1];
        $this->assignerCombattants($combatsPremierRound, $inscriptions, $byes);

        return $combatsParRound;
    }

    /**
     * Assigner les combattants au 1er round
     * Gérer les byes (exemptions) pour les combattants sans adversaire
     */
    private function assignerCombattants(
        array $combats,
        array $inscriptions,
        int $byes
    ): void {
        $index = 0;
        foreach ($combats as $combat) {
            $aka = $inscriptions[$index] ?? null;
            $ao  = $inscriptions[$index + 1] ?? null;
            $index += 2;

            if ($aka && $ao) {
                // Combat normal
                $combat->update([
                    'inscription_aka_id' => $aka['id'],
                    'inscription_ao_id'  => $ao['id'],
                    'statut'             => 0, // en attente
                ]);
            } elseif ($aka && !$ao) {
                // Bye — aka passe directement au round suivant
                $combat->update([
                    'inscription_aka_id' => $aka['id'],
                    'vainqueur_id'       => $aka['id'],
                    'statut'             => 2, // terminé automatiquement
                    'type_victoire'      => 'kiken',
                ]);

                // Placer directement dans le combat suivant
                $this->propaguerVainqueur($combat, $aka['id']);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT POULES
    // ─────────────────────────────────────────────────────────────────────────
    public function genererPoules(ConfigNotation $config): void
    {
        $inscriptions = Inscription::where('config_notation_id', $config->id)->get();
        $nb = $inscriptions->count();

        if ($nb < 3) throw new \Exception("Minimum 3 combattants pour des poules");

        // Division équilibrée (ex: 8 athlètes -> 2 poules de 4)
        $nbPoules = $nb <= 5 ? 1 : max(2, intdiv($nb, 4));
        $groupes  = $inscriptions->shuffle()->chunk(ceil($nb / $nbPoules));
        $ordre    = 1;

        foreach ($groupes as $groupIndex => $groupe) {
            $poule = Poule::create([
                'id'                 => Str::uuid(),
                'config_notation_id' => $config->id,
                'nom'                => 'Groupe ' . chr(65 + $groupIndex),
                'statut'             => 0, // En attente
                'etape'              => 'qualification'
            ]);

            // Utilisation de la table Pivot avec initialisation des compteurs
            foreach ($groupe as $ins) {
                $poule->inscriptions()->attach($ins->id, [
                    'id'                     => Str::uuid(),
                    'points_victoire'        => 0,
                    'total_points_marques'   => 0,
                    'total_points_encaisses' => 0,
                ]);
            }

            // Génération des combats (Chacun contre chacun)
            $membres = $groupe->values();
            for ($i = 0; $i < $membres->count(); $i++) {
                for ($j = $i + 1; $j < $membres->count(); $j++) {
                    Combat::create([
                        'id'                 => Str::uuid(),
                        'competition_id'     => $config->competition_id,
                        'config_notation_id' => $config->id,
                        'poule_id'           => $poule->id,
                        'inscription_aka_id' => $membres[$i]->id,
                        'inscription_ao_id'  => $membres[$j]->id,
                        'etape'              => 'poule',
                        'ordre'              => $ordre++,
                        'statut'             => 0,
                    ]);
                }
            }
        }
    }
    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT POULES + ÉLIMINATOIRE
    // ─────────────────────────────────────────────────────────────────────────
    public function genererPoulesEliminatoire(ConfigNotation $config): void
    {
        // Phase 1 — générer les poules
        $this->genererPoules($config);

        // Phase 2 — le bracket éliminatoire sera généré APRÈS les poules
        // quand on connaît les qualifiés
        // → déclenché par finPoules()
    }

    /**
     * Appelé quand toutes les poules sont terminées
     * Génère le bracket éliminatoire avec les qualifiés
     */
    public function lancerPhaseEliminatoire(ConfigNotation $config): void
    {
        $poules = Poule::where('config_notation_id', $config->id)->with('inscriptions')->get();

        // Prendre les 2 premiers de chaque poule
        $qualifies = [];
        foreach ($poules as $poule) {
            $classement = $this->classementPoule($poule);
            $qualifies   = array_merge($qualifies, array_slice($classement, 0, 2));
        }

        // Générer le bracket avec les qualifiés
        $taille = $this->prochainesPuissanceDeDeux(count($qualifies));
        $byes   = $taille - count($qualifies);

        $this->construireBracket($config, $taille, $qualifies, $byes);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROPAGATION DU VAINQUEUR
    // ─────────────────────────────────────────────────────────────────────────
    public function propaguerVainqueur(Combat $combat, string $vainqueurId): void
    {
        if (!$combat->next_combat_id) return; // finale terminée

        $combatSuivant = Combat::find($combat->next_combat_id);
        if (!$combatSuivant) return;

        // Placer le vainqueur à la bonne position
        if ($combatSuivant->source_aka_combat_id === $combat->id) {
            $combatSuivant->update(['inscription_aka_id' => $vainqueurId]);
        } elseif ($combatSuivant->source_ao_combat_id === $combat->id) {
            $combatSuivant->update(['inscription_ao_id' => $vainqueurId]);
        }

        // Si les deux adversaires sont connus → combat prêt
        if ($combatSuivant->inscription_aka_id && $combatSuivant->inscription_ao_id) {
            $combatSuivant->update(['statut' => 0]); // en attente
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLASSEMENT POULE
    // ─────────────────────────────────────────────────────────────────────────
    public function classementPoule(Poule $poule): array
    {
        $stats = [];
        foreach ($poule->inscriptions as $ins) {
            $stats[$ins->id] = [
                'id'         => $ins->id,
                'victoires'  => 0,
                'points_for' => 0,
                'points_ag'  => 0,
            ];
        }

        $combats = Combat::where('poule_id', $poule->id)->where('statut', 2)->get();

        foreach ($combats as $c) {
            if (!$c->vainqueur_id) continue;

            $stats[$c->inscription_aka_id]['points_for'] += $c->score_final_aka;
            $stats[$c->inscription_aka_id]['points_ag']  += $c->score_final_ao;
            $stats[$c->inscription_ao_id]['points_for']  += $c->score_final_ao;
            $stats[$c->inscription_ao_id]['points_ag']   += $c->score_final_aka;
            $stats[$c->vainqueur_id]['victoires']++;
        }

        usort($stats, function ($a, $b) use ($poule) {
            // 1. Nombre de victoires
            if ($a['victoires'] !== $b['victoires']) return $b['victoires'] - $a['victoires'];

            // 2. Confrontation directe (Indispensable en Karaté)
            $direct = Combat::where('poule_id', $poule->id)
                ->where(function ($q) use ($a, $b) {
                    $q->where('inscription_aka_id', $a['id'])->where('inscription_ao_id', $b['id']);
                })->orWhere(function ($q) use ($a, $b) {
                    $q->where('inscription_aka_id', $b['id'])->where('inscription_ao_id', $a['id']);
                })->first();

            if ($direct && $direct->vainqueur_id) {
                return $direct->vainqueur_id === $a['id'] ? -1 : 1;
            }

            // 3. Différence de points techniques
            return ($b['points_for'] - $b['points_ag']) - ($a['points_for'] - $a['points_ag']);
        });

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function prochainesPuissanceDeDeux(int $n): int
    {
        $puissance = 1;
        while ($puissance < $n) {
            $puissance *= 2;
        }
        return $puissance;
    }

    private function getEtape(int $round, int $totalRounds): string
    {
        $roundDepuisFin = $totalRounds - $round + 1;
        return match ($roundDepuisFin) {
            1 => 'finale',
            2 => 'demi',
            3 => 'quart',
            default => 'tour_' . $round,
        };
    }
}
