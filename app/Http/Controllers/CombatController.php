<?php

namespace App\Http\Controllers;

use App\Models\Poule;
use App\Models\Combat;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;
use App\Services\BracketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class CombatController extends Controller
{
    public function combatEnCours(ConfigNotation $config)
    {
        $combat = Combat::where('config_notation_id', $config->id)
            ->whereIn('status', [1, 2, 3])
            // ->where(function ($q) {
            //     $q->whereNotNull('inscription_ao_id')
            //         ->orWhere('type_victoire', '!=', 'kiken');
            // })
            ->orderByRaw("FIELD(status, 1, 3, 2)")
            ->orderByDesc('updated_at')
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.organisateur:id,name',
                'inscriptionAka.competition.category',
                'inscriptionAka.kataTeam:id,inscription_id,nom',
                'inscriptionAo.athlete',
                'inscriptionAo.organisateur:id,name',
                'inscriptionAo.competition.category',
                'inscriptionAo.kataTeam:id,inscription_id,nom',
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
    public function combatSuivant(ConfigNotation $config)
    {
        // Terminer le combat en cours
        $combatActuel = Combat::where('config_notation_id', $config->id)
            ->whereIn('status', [1, 3])
            ->first();
        if (!$combatActuel) {
            $prochainEnAttente = Combat::where('config_notation_id', $config->id)
                ->where('status', 0)
                ->whereNotNull('inscription_aka_id')
                ->whereNotNull('inscription_ao_id')
                ->exists();

            if ($prochainEnAttente) {
                return response()->json(['message' => 'En attente du résultat d\'un autre combat'], 422);
            }

            $combatEnAttente = Combat::where('config_notation_id', $config->id)
                ->whereIn('status', [0, 1])
                ->where('is_bye', false)
                ->exists();

            if ($combatEnAttente) {
                return response()->json(['message' => 'En attente du résultat d\'un autre combat'], 422);
            }

            return response()->json(['message' => 'Compétition terminée'], 422);
        }

        //  ICI seulement si $combatActuel existe
        if (!$combatActuel->vainqueur_id) {
            return response()->json(['message' => 'Désignez un vainqueur avant de continuer'], 422);
        }

        $vainqueur = $this->determinerVainqueur($combatActuel);

        if ($combatActuel->poule_id) {
            $combatActuel->update(['status' => 2, 'vainqueur_id' => $vainqueur]);
            $this->mettreAJourPouleInscription($combatActuel);
        } else {
            $combatActuel->update(['status' => 2, 'vainqueur_id' => $vainqueur]);
            $this->propaguerVainqueur($combatActuel, $vainqueur);
            if (str_contains($combatActuel->etape, 'Demi-finale')) {
                app(BracketService::class)->verifierEtLancerRepechage($config);
            }
        }

        $suivant = Combat::where('config_notation_id', $config->id)
            ->where('status', 0)
            ->whereNotNull('inscription_aka_id')
            ->whereNotNull('inscription_ao_id')
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.competition.category',
                'inscriptionAo.athlete',
                'inscriptionAo.competition.category',
            ])->orderBy('ordre')
            ->first();

        if ($suivant) {
            $suivant->update(['status' => 1]);
        }

        broadcast(new TatamiUpdated($config->id));
        return response()->json(['success' => true, 'combat' => $suivant]);
    }

    private function determinerVainqueur(Combat $combat): ?string
    {
        Log::info('determinerVainqueur', [
            'score_aka'        => $combat->score_final_aka,
            'score_ao'         => $combat->score_final_ao,
            'senshu_id'        => $combat->senshu_id,
            'vainqueur_id'     => $combat->vainqueur_id,
            'poule_id'         => $combat->poule_id,
            'inscription_aka'  => $combat->inscription_aka_id,
            'inscription_ao'   => $combat->inscription_ao_id,
        ]);

        // Score départage tout
        if ($combat->score_final_aka > $combat->score_final_ao) {
            return $combat->inscription_aka_id;
        }
        if ($combat->score_final_ao > $combat->score_final_aka) {
            return $combat->inscription_ao_id;
        }

        // Égalité -> senshu décide (poule ET éliminatoire)
        if ($combat->senshu_id) {
            return $combat->senshu_id;
        }



        // Éliminatoire sans senshu -> hantei superviseur
        return $combat->vainqueur_id;
    }

    private function propaguerVainqueur(Combat $combat, ?string $vainqueurId): void
    {
        if (!$combat->next_combat_id || !$vainqueurId) return;

        $nextCombat = Combat::find($combat->next_combat_id);
        if (!$nextCombat) return;
        Log::info('propaguerVainqueur', [
            'combat_id'            => $combat->id,
            'next_combat_id'       => $nextCombat->id,
            'source_aka_combat_id' => $nextCombat->source_aka_combat_id,
            'source_ao_combat_id'  => $nextCombat->source_ao_combat_id,
            'est_aka'              => $combat->id === $nextCombat->source_aka_combat_id,
            'vainqueur'            => $vainqueurId,
        ]);

        $nextCombat = Combat::find($combat->next_combat_id);
        if (!$nextCombat) return;

        if ($combat->id === $nextCombat->source_aka_combat_id) {
            $nextCombat->update(['inscription_aka_id' => $vainqueurId]);
        } else {
            $nextCombat->update(['inscription_ao_id' => $vainqueurId]);
        }
    }
    private function mettreAJourPouleInscription(Combat $combat): void
    {
        $combat->refresh();
        $scoreAka = (int) $combat->score_final_aka;
        $scoreAo  = (int) $combat->score_final_ao;

        $vainqueur = $this->determinerVainqueur($combat);


        $pointsAka = 0;
        $pointsAo  = 0;

        if ($vainqueur === $combat->inscription_aka_id) {
            $pointsAka = 3;
        } elseif ($vainqueur === $combat->inscription_ao_id) {
            $pointsAo = 3;
        } else {
            // Égalité
            $config = ConfigNotation::find($combat->config_notation_id);
            if (!$config->match_nul_autorise) {
                // Vainqueur obligatoire -> status 3 hantei
                $combat->update(['status' => 3]);
                broadcast(new TatamiUpdated($combat->config_notation_id));
                return;
            }
            // WKF -> 1pt chacun si score > 0
            $pointsAka = $scoreAka > 0 ? 1 : 0;
            $pointsAo  = $scoreAo  > 0 ? 1 : 0;
        }

        DB::transaction(function () use ($combat, $pointsAka, $pointsAo, $scoreAka, $scoreAo) {
            DB::table('poule_inscriptions')
                ->where('poule_id', $combat->poule_id)
                ->where('inscription_id', $combat->inscription_aka_id)
                ->update([
                    'points_victoire'        => DB::raw("points_victoire + $pointsAka"),
                    'total_points_marques'   => DB::raw("total_points_marques + $scoreAka"),
                    'total_points_encaisses' => DB::raw("total_points_encaisses + $scoreAo"),
                ]);

            DB::table('poule_inscriptions')
                ->where('poule_id', $combat->poule_id)
                ->where('inscription_id', $combat->inscription_ao_id)
                ->update([
                    'points_victoire'        => DB::raw("points_victoire + $pointsAo"),
                    'total_points_marques'   => DB::raw("total_points_marques + $scoreAo"),
                    'total_points_encaisses' => DB::raw("total_points_encaisses + $scoreAka"),
                ]);
        });

        $tousFinis = Combat::where('poule_id', $combat->poule_id)
            ->where('id', '!=', $combat->id)
            ->where('status', '!=', 2)
            ->doesntExist();

        if ($tousFinis) {
            Poule::where('id', $combat->poule_id)->update(['status' => 2]);
            $this->calculerRangsPoule($combat->poule_id);
            // Vérifier si TOUTES les poules du config sont terminées
            $toutesLesPoulesFinees = Poule::where('config_notation_id', $combat->config_notation_id)
                ->where('status', '!=', 2)
                ->doesntExist();

            if ($toutesLesPoulesFinees) {
                // Lancer automatiquement la phase éliminatoire uniquement
                // si le format kumite est `poules_eliminatoire`.
                $config = ConfigNotation::find($combat->config_notation_id);

                if (!$config || !$config->kumite_format_id) {
                    Log::warning("No kumite_format set for config {$combat->config_notation_id}; cannot auto-launch eliminatoire");
                } elseif ($config->kumiteFormat->code === 'poules_eliminatoire') {
                    app(BracketService::class)->lancerPhaseEliminatoire($config);
                    broadcast(new TatamiUpdated($combat->config_notation_id));
                }
            }
        }
    }
    private function calculerRangsPoule(string $pouleId): void
    {
        // Récupérer tous les membres triés selon règles WKF
        $membres = DB::table('poule_inscriptions')
            ->where('poule_id', $pouleId)
            ->orderByDesc('points_victoire')           // 1. Points victoire
            ->orderByDesc('total_points_marques')      // 2. Points marqués
            ->orderBy('total_points_encaisses')        // 3. Moins de points encaissés
            ->get();

        // Assigner le rang
        foreach ($membres as $index => $membre) {
            DB::table('poule_inscriptions')
                ->where('poule_id', $pouleId)
                ->where('inscription_id', $membre->inscription_id)
                ->update(['rang' => $index + 1]);
        }
    }
    public function bracket(ConfigNotation $config)
    {
        $combats = Combat::where('config_notation_id', $config->id)
            ->with([
                'inscriptionAka.athlete',
                'inscriptionAka.kataTeam:id,inscription_id,nom',
                'inscriptionAo.athlete',
                'inscriptionAo.kataTeam:id,inscription_id,nom',
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
        return response()->json(['success' => true, 'message' => 'chrono demaré avec succés']);
    }

    public function stopChrono(Combat $combat)
    {
        if (!$combat->hajime_at) {
            return response()->json(['success' => false, 'message' => 'Aucun chrono en cours']);
        }

        $secondesDepuisHajime = \Carbon\Carbon::parse($combat->hajime_at)->diffInSeconds(now());
        $tempsEcoule = $combat->temps_ecoule + $secondesDepuisHajime;

        $combat->update([
            'yame_at'      => now(),
            'temps_ecoule' => $tempsEcoule,
        ]);

        $combat->load('configNotation');
        $duree = $combat->configNotation->duration * 60;

        if ($tempsEcoule >= $duree) {
            $vainqueur = $this->determinerVainqueur($combat);

            if ($vainqueur) {
                // Vainqueur trouvé  status 2
                $combat->update([
                    'vainqueur_id' => $vainqueur,
                ]);
            } else {
                // Pas de vainqueur -> status 3 (hantei requis)
                // Ce cas arrive seulement en éliminatoire sans senshu
                $combat->update(['status' => 3]);
            }
        }

        broadcast(new TatamiUpdated($combat->config_notation_id));
        return response()->json(['success' => true, 'message' => 'Chrono arrêté avec succès']);
    }
}
