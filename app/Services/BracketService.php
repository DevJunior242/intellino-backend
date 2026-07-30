<?php

namespace App\Services;

use DB;
use Exception;
use App\Models\Poule;
use App\Models\Combat;

use App\Models\Inscription;
use Illuminate\Support\Str;
use App\Models\OrdrePassage;
use App\Events\TatamiUpdated;
use App\Models\ConfigNotation;

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
                default               => throw new Exception("Format non reconnu"),
            };
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT ÉLIMINATOIRE DIRECT
    // ─────────────────────────────────────────────────────────────────────────
    public function genererEliminatoire(ConfigNotation $config): void
    {
        $combattants = $this->chargerCombattants($config);
        $nb = count($combattants);

        if ($nb < 2) {
            throw new Exception("Pas assez de combattants — minimum 2");
        }

        // Compléter à la puissance de 2 supérieure (2, 4, 8, 16...)
        $taille = $this->prochainesPuissanceDeDeux($nb);
        $byes   = $taille - $nb; // combattants exemptés au 1er tour

        // Place les têtes de série (bye en priorité) puis limite les
        // rencontres intra-club/ligue au 1er tour quand c'est possible
        $slots = $this->placerCombattants($combattants, $taille);

        // Construire le bracket de l'intérieur vers l'extérieur
        // On crée d'abord la finale, puis les demi-finales, etc.
        $combats = $this->construireBracket($config, $taille, $slots, $byes);
    }

    /**
     * Charge les combattants d'une config avec l'identité de leur
     * club/ligue (pour l'anti-collision)
     */
    private function chargerCombattants(ConfigNotation $config): array
    {
        return OrdrePassage::where('config_notation_id', $config->id)
            ->with('inscription:id,organisateur_type,organisateur_id')
            ->get()
            ->map(fn($o) => [
                'inscription_id' => $o->inscription_id,
                'club_key'       => $o->inscription->organisateur_type . ':' . $o->inscription->organisateur_id,
            ])
            ->all();
    }

    /**
     * Génère l'ordre des rangs dans un tableau de $n places (algorithme
     * standard de seeding : seed 1 et 2 ne peuvent se croiser qu'en finale)
     */
    private function genererOrdreSeed(int $n): array
    {
        if ($n === 1) return [1];

        $precedent = $this->genererOrdreSeed($n / 2);
        $ordre = [];
        foreach ($precedent as $rang) {
            $ordre[] = $rang;
            $ordre[] = $n + 1 - $rang;
        }

        return $ordre;
    }

    /**
     * Place les combattants au hasard dans les positions du tableau,
     * puis tente de limiter les rencontres intra-club au 1er tour.
     */
    private function placerCombattants(array $combattants, int $taille): array
    {
        $classes = collect($combattants)->shuffle()->values(); // rang 1..nb

        $ordreSeed = $this->genererOrdreSeed($taille);
        $slots = array_fill(0, $taille, null);

        foreach ($ordreSeed as $position => $rang) {
            if ($rang > $classes->count()) continue; // place vide -> bye

            $c = $classes[$rang - 1];
            $slots[$position] = [
                'inscription_id' => $c['inscription_id'],
                'club_key'       => $c['club_key'],
                'verrouille'     => false,
            ];
        }

        return $this->resoudreCollisionsClub($slots);
    }

    /**
     * Parcourt les paires du 1er tour (0-1, 2-3, ...) et tente d'échanger
     * un combattant non verrouillé (non-premier de poule) pour éviter deux
     * combattants du même club/ligue. Best effort : si aucun échange ne
     * résout la collision, on la laisse telle quelle.
     */
    private function resoudreCollisionsClub(array $slots): array
    {
        $n = count($slots);

        for ($i = 0; $i < $n; $i += 2) {
            $a = $slots[$i] ?? null;
            $b = $slots[$i + 1] ?? null;

            if (!$a || !$b || $a['club_key'] !== $b['club_key']) continue;
            if ($b['verrouille']) continue; // impossible de déplacer ce côté

            for ($j = 0; $j < $n; $j++) {
                if ($j === $i || $j === $i + 1) continue;

                $candidat = $slots[$j] ?? null;
                if (!$candidat || $candidat['verrouille']) continue;

                $partenaireCandidat = $slots[$j % 2 === 0 ? $j + 1 : $j - 1] ?? null;

                $okIci   = !$partenaireCandidat || $partenaireCandidat['club_key'] !== $b['club_key'];
                $okLaBas = $candidat['club_key'] !== $a['club_key'];

                if ($okIci && $okLaBas) {
                    [$slots[$i + 1], $slots[$j]] = [$candidat, $b];
                    break;
                }
            }
        }

        return $slots;
    }

    /**
     * Construire le bracket récursivement
     * Crée les combats de la finale vers le 1er tour
     */
    private function construireBracket(
        ConfigNotation $config,
        int $taille,
        array $ordres,
        int $byes
    ): array {
        $combatsParRound = [];
        $ordre           = 1;

        // Générer tous les rounds
        // taille=8 -> rounds: [4 combats quart, 2 combats demi, 1 finale]
        $rounds = log($taille, 2); // ex: log(8,2) = 3

        // Créer les combats vides par round (finale en dernier)
        for ($round = 1; $round <= $rounds; $round++) {
            $nbCombats   = $taille / pow(2, $round);
            $etape       = $this->getEtape($round, $rounds);
            $roundCombats = [];

            for ($i = 0; $i < $nbCombats; $i++) {

                $combat = Combat::create([
                    'id'                  => Str::uuid(),
                    'config_notation_id'  => $config->id,

                    'etape'               => $etape,
                    'ordre'               => $ordre++,
                    'status'              => 0,
                    'score_final_aka'     => 0,
                    'score_final_ao'      => 0,
                ]);
                $roundCombats[] = $combat;
            }
            $combatsParRound[$round] = $roundCombats;
        }

        // Lier les combats entre rounds
        // Combat du round N -> next_combat = combat du round N+1
        for ($round = 1; $round < $rounds; $round++) {
            $combatsActuels  = $combatsParRound[$round];
            $combatsSuivants = $combatsParRound[$round + 1];

            foreach ($combatsActuels as $index => $combat) {
                // Chaque paire de combats pointe vers le même combat suivant
                $indexSuivant = intdiv($index, 2);
                $combatSuivant = $combatsSuivants[$indexSuivant];

                // Position dans le combat suivant (aka ou ao)
                if ($index % 2 === 0) {
                    // Pair -> source_aka du combat suivant
                    $combat->update([
                        'next_combat_id'      => $combatSuivant->id,
                    ]);
                    $combatSuivant->update([
                        'source_aka_combat_id' => $combat->id,
                    ]);
                } else {
                    // Impair -> source_ao du combat suivant
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
        $this->assignerCombattants($config, $combatsPremierRound, $ordres, $byes);

        return $combatsParRound;
    }

    /**
     * Assigner les combattants au 1er round
     * Gérer les byes (exemptions) pour les combattants sans adversaire
     */
    private function assignerCombattants(
        ConfigNotation $config,
        array $combats,
        array $ordres,
        int $byes
    ): void {
        $index = 0;
        foreach ($combats as $combat) {
            $aka = $ordres[$index] ?? null;
            $ao  = $ordres[$index + 1] ?? null;
            $index += 2;

            if ($aka && $ao) {
                // Combat normal
                $combat->update([
                    'inscription_aka_id' => $aka['inscription_id'],
                    'inscription_ao_id'  => $ao['inscription_id'],
                    'status'             => 0, // en attente
                ]);

                $this->creerPassagesKataSiBesoin($config, $combat);
            } elseif ($aka && !$ao) {
                // Bye — aka passe directement au round suivant
                $combat->update([
                    'inscription_aka_id' => $aka['inscription_id'],
                    'vainqueur_id'       => $aka['inscription_id'],
                    'inscription_ao_id'  => null,
                    'status'             => 1,
                    'type_victoire'      => 'kiken',
                    'is_bye'       => true,
                ]);

                // Placer directement dans le combat suivant
                $this->propaguerVainqueur($combat, $aka['inscription_id']);
            }
        }
    }

    /**
     * En Kata, chaque combat est un duel Aka/Ao noté par les juges : on crée
     * les deux OrdrePassage (Aka d'abord, puis Ao — Art. 6.9 WKF Kata
     * Competition Rules) reliés à ce combat, pour que KataNotationService
     * puisse ensuite calculer le vote majoritaire des juges. Sans effet en
     * Kumite ou sur un bye (pas d'adversaire à noter).
     */
    private function creerPassagesKataSiBesoin(ConfigNotation $config, Combat $combat): void
    {
        if (!$config->estKata()) return;
        if (!$combat->inscription_aka_id || !$combat->inscription_ao_id) return;

        $ordre = OrdrePassage::where('config_notation_id', $config->id)->max('ordre') ?? 0;

        OrdrePassage::create([
            'config_notation_id' => $config->id,
            'inscription_id'     => $combat->inscription_aka_id,
            'combat_id'          => $combat->id,
            'ordre'              => ++$ordre,
            'status'             => OrdrePassage::STATUS_NOT_STARTED,
        ]);

        OrdrePassage::create([
            'config_notation_id' => $config->id,
            'inscription_id'     => $combat->inscription_ao_id,
            'combat_id'          => $combat->id,
            'ordre'              => ++$ordre,
            'status'             => OrdrePassage::STATUS_NOT_STARTED,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT POULES
    // ─────────────────────────────────────────────────────────────────────────

    public function genererPoules(ConfigNotation $config): void
    {
        $combattants = $this->chargerCombattants($config);
        $nb = count($combattants);

        if ($nb < 3) throw new Exception("Minimum 3 combattants pour des poules");

        // 1. Calcul intelligent du nombre de poules
        $nbPoules = $this->calculerNbPoules($nb);

        // 2. Répartition en 4/3 sans poule de 1 ou 2
        $taillesPoules = $this->repartirEnTroisQuatre($nb, $nbPoules);

        // 3. Placement aléatoire en limitant les collisions de club/ligue
        // dans une même poule
        $repartition = $this->repartirEnPoules($combattants, $taillesPoules);

        $ordreGlobalCombat = 1;
        $order = 1;

        foreach ($repartition as $groupIndex => $membres) {
            $poule = Poule::create([
                'id'                 => Str::uuid(),
                'config_notation_id' => $config->id,
                'nom'                => 'Groupe ' . chr(65 + $groupIndex),
                'status'             => 0,
                'etape'              => 'qualification',
                'ordre'              => $order++,
            ]);

            // Attacher les membres à la poule
            foreach ($membres as $m) {
                $poule->combattants()->attach($m['inscription_id'], [
                    'id'                     => Str::uuid(),
                    'points_victoire'        => 0,
                    'total_points_marques'   => 0,
                    'total_points_encaisses' => 0,
                ]);
            }

            // Génération des combats Berger
            $list = array_map(fn($m) => $m['inscription_id'], $membres);
            $count = count($list);

            if ($count % 2 !== 0) {
                $list[] = null;
                $count++;
            }

            $totalRounds = $count - 1;
            $matchesPerRound = $count / 2;

            for ($round = 0; $round < $totalRounds; $round++) {
                for ($i = 0; $i < $matchesPerRound; $i++) {
                    $home = $list[$i];
                    $away = $list[$count - 1 - $i];

                    if ($home !== null && $away !== null) {
                        $combat = Combat::create([
                            'id'                 => Str::uuid(),
                            'competition_id'     => $config->competition_id,
                            'config_notation_id' => $config->id,
                            'poule_id'           => $poule->id,
                            'inscription_aka_id' => $home,
                            'inscription_ao_id'  => $away,
                            'etape'              => 'poule',
                            'ordre'              => $ordreGlobalCombat++,
                            'status'             => 0,
                        ]);

                        $this->creerPassagesKataSiBesoin($config, $combat);
                    }
                }

                $lastElement = array_pop($list);
                array_splice($list, 1, 0, [$lastElement]);
            }
        }
    }


    /**
     * Répartit les combattants au hasard dans les poules, un par un dans
     * la poule qui minimise les collisions de club/ligue (best effort,
     * une poule pleine de membres du même club reste possible si les
     * effectifs l'imposent).
     */
    private function repartirEnPoules(array $combattants, array $taillesPoules): array
    {
        $nbPoules = count($taillesPoules);
        $poules = array_fill(0, $nbPoules, []);

        $ordre = collect($combattants)->shuffle()->values();

        foreach ($ordre as $c) {
            $meilleurePoule = null;
            $meilleurScore  = null;

            for ($p = 0; $p < $nbPoules; $p++) {
                if (count($poules[$p]) >= $taillesPoules[$p]) continue;

                $conflits = collect($poules[$p])->where('club_key', $c['club_key'])->count();
                $score = [$conflits, count($poules[$p])];

                if ($meilleurScore === null || $score < $meilleurScore) {
                    $meilleurScore = $score;
                    $meilleurePoule = $p;
                }
            }

            $poules[$meilleurePoule][] = $c;
        }

        return $poules;
    }

    private function calculerNbPoules(int $nb): int
    {
        // Cas < 6 : 1 poule max
        if ($nb <= 5) return 1;

        // Test des puissances de 2 : 8, 16, 32 qualifiés
        $puissances2 = [32, 16, 8, 4];

        foreach ($puissances2 as $qualifies) {
            $nbPoules = $qualifies / 2; // 2 qualifiés/poule

            // Peut-on répartir $nb athlètes en $nbPoules poules de 3 ou 4 ?
            $min = $nbPoules * 3;
            $max = $nbPoules * 4;

            if ($nb >= $min && $nb <= $max) {
                return $nbPoules; // Zéro bye possible 
            }
        }

        // Si aucune puissance de 2 ne marche -> on prend le plus proche
        // Et on minimise les bye entre 3 et 4 par poule
        $poules4 = (int) ceil($nb / 4);
        $poules3 = (int) ceil($nb / 3);

        $bye4 = $this->calculerBye($poules4 * 2);
        $bye3 = $this->calculerBye($poules3 * 2);

        return $bye4 <= $bye3 ? $poules4 : $poules3;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMAT POULES + ÉLIMINATOIRE
    // ─────────────────────────────────────────────────────────────────────────
    public function genererPoulesEliminatoire(ConfigNotation $config): void
    {
        // Phase 1 — générer les poules
        $this->genererPoules($config);
    }

    /**
     * Appelé quand toutes les poules sont terminées
     * Génère le bracket éliminatoire avec les qualifiés
     */
    public function lancerPhaseEliminatoire(ConfigNotation $config): void
    {
        $poules = Poule::where('config_notation_id', $config->id)->get();

        $qualifies = [];
        $thirdCandidates = [];

        // Récupère 1er et 2ème de chaque poule, et garde les 3èmes en candidat
        foreach ($poules as $poule) {
            // Récupération ordonnée avec les critères de classement
            $rows = \DB::table('poule_inscriptions')
                ->where('poule_id', $poule->id)
                ->orderByDesc('points_victoire')
                ->orderByDesc('total_points_marques')
                ->orderBy('total_points_encaisses')
                ->get();

            if (isset($rows[0])) $qualifies[] = ['inscription_id' => $rows[0]->inscription_id, 'role' => 'premier'];
            if (isset($rows[1])) $qualifies[] = ['inscription_id' => $rows[1]->inscription_id, 'role' => 'second'];

            // Garder le 3ème si présent pour sélection possible
            if (isset($rows[2])) {
                $thirdCandidates[] = [
                    'inscription_id' => $rows[2]->inscription_id,
                    'points_victoire' => $rows[2]->points_victoire,
                    'total_points_marques' => $rows[2]->total_points_marques,
                    'total_points_encaisses' => $rows[2]->total_points_encaisses,
                ];
            }
        }

        // Déterminer la taille cible du bracket (puissance de 2)
        $baseQualifies = count($qualifies);
        $tailleCible = $this->prochainesPuissanceDeDeux($baseQualifies);
        $additionalNeeded = $tailleCible - $baseQualifies;

        // Si besoin, sélectionner les meilleurs 3èmes selon les critères
        if ($additionalNeeded > 0 && count($thirdCandidates) > 0) {
            usort($thirdCandidates, function ($a, $b) {
                if ($a['points_victoire'] !== $b['points_victoire']) return $b['points_victoire'] <=> $a['points_victoire'];
                if ($a['total_points_marques'] !== $b['total_points_marques']) return $b['total_points_marques'] <=> $a['total_points_marques'];
                return $a['total_points_encaisses'] <=> $b['total_points_encaisses'];
            });

            $selectedThirds = array_slice($thirdCandidates, 0, $additionalNeeded);
            foreach ($selectedThirds as $t) {
                $qualifies[] = ['inscription_id' => $t['inscription_id'], 'role' => 'troisieme'];
            }

            // Recompute cible si on a toujours un nombre non puissance de deux
            $baseQualifies = count($qualifies);
            $tailleCible = $this->prochainesPuissanceDeDeux($baseQualifies);
        }

        $qualifiesTriés = $this->seederBracket($qualifies, $poules->count());
        $taille = $this->prochainesPuissanceDeDeux(count($qualifies));
        $byes   = $taille - count($qualifiesTriés);

        $clubParInscription = Inscription::whereIn('id', collect($qualifiesTriés)->pluck('inscription_id'))
            ->get(['id', 'organisateur_type', 'organisateur_id'])
            ->keyBy('id');

        $slots = $this->slotsDepuisQualifies($qualifiesTriés, $clubParInscription, $taille);

        $this->construireBracket($config, $taille, $slots, $byes);
    }

    /**
     * Construit les positions du 1er tour à partir de l'ordre déjà seedé
     * par seederBracket, puis tente de limiter les rencontres intra-club
     * en n'échangeant que les "seconds" (les 1ers de poule restent fixes,
     * comme les têtes de série en éliminatoire directe).
     */
    private function slotsDepuisQualifies(array $qualifies, $clubParInscription, int $taille): array
    {
        $ordreSeed = $this->genererOrdreSeed($taille);
        $slots = array_fill(0, $taille, null);
        $nb = count($qualifies);

        foreach ($ordreSeed as $position => $rang) {
            if ($rang > $nb) continue; // pas de qualifié à ce rang -> bye

            $q = $qualifies[$rang - 1];
            $ins = $clubParInscription[$q['inscription_id']] ?? null;

            $slots[$position] = [
                'inscription_id' => $q['inscription_id'],
                'club_key'       => $ins ? "{$ins->organisateur_type}:{$ins->organisateur_id}" : null,
                'verrouille'     => ($q['role'] ?? null) === 'premier',
            ];
        }

        return $this->resoudreCollisionsClub($slots);
    }
    // ─────────────────────────────────────────────────────────────────────────

    private function seederBracket(array $qualifies, int $nbPoules): array
    {
        // Séparer 1ers et 2èmes de chaque poule
        $premiers = [];
        $seconds  = [];

        for ($i = 0; $i < $nbPoules; $i++) {
            if (isset($qualifies[$i * 2])) {
                $premiers[] = $qualifies[$i * 2];     // 1er de chaque poule
            }
            if (isset($qualifies[$i * 2 + 1])) {
                $seconds[] = $qualifies[$i * 2 + 1];  // 2ème de chaque poule
            }
        }

        // Placement WKF : 1ers d'un côté, 2èmes de l'autre
        // Pour éviter que 1er poule A affronte 1er poule B avant la finale
        $seeded = [];
        $nb = max(count($premiers), count($seconds));

        for ($i = 0; $i < $nb; $i++) {
            if (isset($premiers[$i])) $seeded[] = $premiers[$i];
            if (isset($seconds[$nb - 1 - $i])) $seeded[] = $seconds[$nb - 1 - $i];
        }

        return $seeded;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLASSEMENT POULE
    // ─────────────────────────────────────────────────────────────────────────
    public function classementPoule(Poule $poule): array
    {
        return DB::table('poule_inscriptions')
            ->where('poule_id', $poule->id)
            ->orderByDesc('points_victoire')
            ->orderByDesc('total_points_marques')
            ->orderBy('total_points_encaisses')
            ->get()
            ->map(fn($row) => ['inscription_id' => $row->inscription_id])
            ->toArray();
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

        // Si les deux adversaires sont connus -> combat prêt
        if ($combatSuivant->inscription_aka_id && $combatSuivant->inscription_ao_id) {
            $combatSuivant->update(['status' => 0]); // en attente
        }
    }

    private function getPerduContreFinaliste(string $finalisteId, ConfigNotation $config): array
    {
        $perdants = [];
        $combattantActuel = $finalisteId;

        // Remonter tous les combats gagnés par le finaliste
        $combats = Combat::where('config_notation_id', $config->id)
            ->where('vainqueur_id', $combattantActuel)
            ->where('status', 2)
            ->whereNull('poule_id') // phase éliminatoire seulement
            ->get();

        foreach ($combats as $combat) {
            // Le perdant = celui qui n'est pas le vainqueur
            $perdant = $combat->inscription_aka_id === $combattantActuel
                ? $combat->inscription_ao_id
                : $combat->inscription_aka_id;

            $perdants[] = $perdant;
        }

        return $perdants;
    }
    public function lancerRepechage(ConfigNotation $config): void
    {
        // Trouver les deux finalistes
        $finale = Combat::where('config_notation_id', $config->id)
            ->where('etape', 'Finale')
            ->first();

        $finalisteAka = $finale->inscription_aka_id;
        $finalisteAo  = $finale->inscription_ao_id;

        // Perdants de chaque côté
        $perdantsCoteAka = $this->getPerduContreFinaliste($finalisteAka, $config);
        $perdantsCoteAo  = $this->getPerduContreFinaliste($finalisteAo, $config);

        // Générer bracket repêchage côté AKA
        $this->construireBracketRepechage($config, $perdantsCoteAka, 'bronze_aka');

        // Générer bracket repêchage côté AO
        $this->construireBracketRepechage($config, $perdantsCoteAo, 'bronze_ao');
    }

    private function construireBracketRepechage(
        ConfigNotation $config,
        array $perdants,
        string $cote // 'bronze_aka' ou 'bronze_ao'
    ): void {
        $nb = count($perdants);

        if ($nb === 0) return;

        // Si un seul perdant -> bye direct -> bronze automatique
        if ($nb === 1) {
            Combat::create([
                'id'                 => Str::uuid(),
                'config_notation_id' => $config->id,
                'inscription_aka_id' => null,
                'inscription_ao_id'  => $perdants[0],
                'vainqueur_id'       => $perdants[0],
                'etape'              => "Bronze_{$cote}",
                'type_victoire'      => 'kiken',
                'status'             => 2,
                'ordre'              => Combat::where('config_notation_id', $config->id)->max('ordre') + 1,
                'score_final_aka'    => 0,
                'score_final_ao'     => 0,
            ]);
            return;
        }

        // Compléter à la puissance de 2
        $taille = $this->prochainesPuissanceDeDeux($nb);
        $byes   = $taille - $nb;

        // Construire le bracket de repêchage
        $combatsParRound = [];
        $ordre = Combat::where('config_notation_id', $config->id)
            ->max('ordre') + 100;

        $rounds = log($taille, 2);

        for ($round = 1; $round <= $rounds; $round++) {
            $nbCombats   = $taille / pow(2, $round);
            $roundCombats = [];

            for ($i = 0; $i < $nbCombats; $i++) {
                $combat = Combat::create([
                    'id'                 => Str::uuid(),
                    'config_notation_id' => $config->id,
                    'etape'              => $round === $rounds
                        ? "Bronze_{$cote}"   // Finale repêchage -> bronze
                        : "Repechage_{$cote}_R{$round}",
                    'ordre'              => $ordre++,
                    'status'             => 0,
                    'score_final_aka'    => 0,
                    'score_final_ao'     => 0,
                ]);
                $roundCombats[] = $combat;
            }
            $combatsParRound[$round] = $roundCombats;
        }

        // Lier les combats entre rounds
        for ($round = 1; $round < $rounds; $round++) {
            $combatsActuels  = $combatsParRound[$round];
            $combatsSuivants = $combatsParRound[$round + 1];

            foreach ($combatsActuels as $index => $combat) {
                $indexSuivant  = intdiv($index, 2);
                $combatSuivant = $combatsSuivants[$indexSuivant];

                $combat->update(['next_combat_id' => $combatSuivant->id]);

                if ($index % 2 === 0) {
                    $combatSuivant->update(['source_aka_combat_id' => $combat->id]);
                } else {
                    $combatSuivant->update(['source_ao_combat_id' => $combat->id]);
                }
            }
        }

        // Assigner les combattants au 1er round (répartition seedée pour
        // qu'aucune paire ne se retrouve avec deux byes empilés)
        $this->assignerCombattants(
            $config,
            $combatsParRound[1],
            $this->placerIdsAvecByes($perdants, $taille),
            $byes
        );
    }

    /**
     * Répartit une simple liste d'inscription_id dans un tableau de $taille
     * positions en utilisant l'algorithme de seeding standard, pour que les
     * byes ne se retrouvent jamais deux par deux dans la même paire.
     */
    private function placerIdsAvecByes(array $ids, int $taille): array
    {
        $ordreSeed = $this->genererOrdreSeed($taille);
        $slots = array_fill(0, $taille, null);
        $nb = count($ids);

        foreach ($ordreSeed as $position => $rang) {
            if ($rang > $nb) continue; // pas d'id à ce rang -> bye

            $slots[$position] = ['inscription_id' => $ids[$rang - 1]];
        }

        return $slots;
    }

    public function verifierEtLancerRepechage(ConfigNotation $config): void
    {
        // Vérifier que les deux demi-finales sont terminées

        DB::transaction(function () use ($config) {
            $repechageExiste = Combat::where('config_notation_id', $config->id)
                ->where('etape', 'LIKE', 'Bronze_%')
                ->lockForUpdate()
                ->exists();

            if ($repechageExiste) return;

            $demiesTerminees = Combat::where('config_notation_id', $config->id)
                ->where('etape', 'Demi-finale')
                ->where('status', 2)
                ->count();

            $totalDemies = Combat::where('config_notation_id', $config->id)
                ->where('etape', 'Demi-finale')
                ->count();

            if ($demiesTerminees === $totalDemies && $totalDemies > 0) {
                $this->lancerRepechage($config);
                broadcast(new TatamiUpdated($config->id));
            }
        });
    }
    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    // private function prochainesPuissanceDeDeux(int $n): int
    // {
    //     $puissance = 1;
    //     while ($puissance < $n) {
    //         $puissance *= 2;
    //     }
    //     return $puissance;
    // }


    private function repartirEnTroisQuatre(int $nb, int $nbPoules): array
    {
        $base = intdiv($nb, $nbPoules);
        $reste = $nb % $nbPoules;

        $tailles = array_fill(0, $nbPoules, $base);

        for ($i = 0; $i < $reste; $i++) {
            $tailles[$i]++;
        }

        // Sécurité WKF : transforme 2+4 en 3+3
        if (in_array(2, $tailles) && in_array(4, $tailles)) {
            $k4 = array_search(4, $tailles);
            $k2 = array_search(2, $tailles);
            $tailles[$k4] = 3;
            $tailles[$k2] = 3;
        }

        return $tailles; // Ex: 15 -> [4,4,4,3] | 14 -> [4,4,3,3]
    }

    private function calculerBye(int $qualifies): int
    {
        $puissance = $this->prochainesPuissanceDeDeux($qualifies);
        return $puissance - $qualifies;
    }

    private function prochainesPuissanceDeDeux(int $n): int
    {
        $p = 1;
        while ($p < $n) $p *= 2;
        return $p;
    }

    private function getEtape(int $round, int $totalRounds): string
    {
        $roundDepuisFin = $totalRounds - $round + 1;
        return match ($roundDepuisFin) {
            1 => 'Finale',
            2 => 'Demi-finale',
            3 => 'Quart-finale',
            default => 'tour_' . $round,
        };
    }
}
