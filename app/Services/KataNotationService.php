<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Poule;
use App\Models\Combat;
use App\Models\Inscription;
use App\Events\NoteAjoutee;
use App\Models\OrdrePassage;
use App\Models\ConfigNotation;
use App\Models\RotationArbitre;
use App\Services\BracketService;
use Illuminate\Support\Facades\DB;

class KataNotationService
{
    /**
     * Rotation active de l'arbitre connecté sur ce tatami, ou null s'il
     * n'est pas assigné/actif dessus.
     */
    public function trouverRotationActive(string $configNotationId, string $userId): ?RotationArbitre
    {
        return RotationArbitre::where('config_notation_id', $configNotationId)
            ->whereHas('arbitreCompetition', fn($q) => $q->where('user_id', $userId))
            ->where('actif', true)
            ->first();
    }

    public function aDejaNote(string $ordrePassageId, string $rotationArbitreId): bool
    {
        return Note::where('ordre_passage_id', $ordrePassageId)
            ->where('rotation_arbitre_id', $rotationArbitreId)
            ->exists();
    }

    /**
     * Enregistre la note d'un juge pour un passage (mode tablettes) et
     * notifie le tatami en temps réel.
     */
    public function enregistrerNote(OrdrePassage $passage, RotationArbitre $rotation, float $valeur): Note
    {
        $note = Note::updateOrCreate(
            [
                'ordre_passage_id'    => $passage->id,
                'rotation_arbitre_id' => $rotation->id,
            ],
            [
                'valeur' => $valeur,
                'note_a' => now(),
            ]
        );

        broadcast(new NoteAjoutee(
            configId: $passage->config_notation_id,
            ordrePassageId: $passage->id,
        ));

        return $note;
    }

    /**
     * Enregistre la note d'un juge pour un passage (mode centralisé, saisie
     * par poste de juge plutôt que par rotation individuelle).
     */
    public function enregistrerNoteCentralisee(string $inscriptionId, string $arbitreCompetitionId, float $valeur): Note
    {
        return Note::updateOrCreate(
            [
                'inscription_id'         => $inscriptionId,
                'arbitre_competition_id' => $arbitreCompetitionId,
            ],
            ['valeur' => $valeur]
        );
    }

    /**
     * Notes et score d'un passage, prêts à être renvoyés au frontend.
     */
    public function resultatPassage(OrdrePassage $ordrePassage): array
    {
        $notes = Note::where('ordre_passage_id', $ordrePassage->id)
            ->with([
                'rotationArbitre.arbitreCompetition.user:id,fullname',
                'ordrePassage.inscription.athlete:id,fullname'
            ])
            ->get();

        $dataNotes = $notes->map(fn($note) => [
            'id'      => $note->id,
            'valeur'  => (float) $note->valeur,
            'poste'   => $note->rotationArbitre?->poste ?? "Inconnu",
            'arbitre' => $note->rotationArbitre?->arbitreCompetition?->user?->fullname ?? "Inconnu",
        ]);

        $config = $ordrePassage->configNotation()->with('nbJugesOption')->first();
        $nbJugesAttendus = $config->nbJugesOption ? (int) $config->nbJugesOption->valeur : 5;

        $termine = $dataNotes->count() >= $nbJugesAttendus;

        return [
            'notes'   => $dataNotes,
            'total'   => $dataNotes->count(),
            'attendu' => $nbJugesAttendus,
            'termine' => $termine,
            'message' => $termine
                ? "Notation terminée"
                : "En attente des notes ({$dataNotes->count()}/{$nbJugesAttendus})",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // DUEL AKA/AO (WKF Art. 3.1.2, 5.4.2, 5.5.1) — un combat Kata est un
    // duel tranché au vote majoritaire des juges, à chaque tour du tableau
    // (poules comme élimination), pas un classement séquentiel.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Appelé une fois juste après BracketService::generer() pour une config
     * Kata : crée les OrdrePassage nécessaires pour chaque combat déjà prêt
     * (les deux camps connus) du 1er tour.
     */
    public function creerPassagesInitiales(ConfigNotation $config): void
    {
        // Les OrdrePassage sans combat_id sont les files d'attente créées par
        // OrdrePassageController::assigner() avant la génération du tableau —
        // BracketService::chargerCombattants() les a déjà lues pour composer
        // les camps ; elles doivent disparaître pour ne pas être prises pour
        // le 1er passage à juger par lancerSeance() (qui prend le plus petit
        // `ordre` en NOT_STARTED sans distinguer combat_id).
        OrdrePassage::where('config_notation_id', $config->id)
            ->whereNull('combat_id')
            ->delete();

        Combat::where('config_notation_id', $config->id)
            ->whereNotNull('inscription_aka_id')
            ->whereNotNull('inscription_ao_id')
            ->orderBy('ordre')
            ->get()
            ->each(fn(Combat $combat) => $this->creerPassagesPourCombat($combat));
    }

    /**
     * Crée le(s) OrdrePassage nécessaires pour juger un combat dont les deux
     * camps sont désormais connus. Idempotent. Un camp a normalement une
     * seule prestation ; une finale d'équipe Kata en a deux (Kata puis
     * Bunkai, Art. 3.5.4/5.4.3 — poids égal, mis en musique dans
     * determinerVainqueurDuel() en sommant les notes des deux phases).
     * Ignore les byes (rien à juger — cohérent avec le comportement Kumite).
     */
    public function creerPassagesPourCombat(Combat $combat): void
    {
        if (!$combat->configNotation->estKata()) return;
        if (!$combat->inscription_aka_id || !$combat->inscription_ao_id) return;
        if (OrdrePassage::where('combat_id', $combat->id)->exists()) return;

        $ordre = OrdrePassage::where('config_notation_id', $combat->config_notation_id)->max('ordre') ?? 0;

        $phases = ($this->estMatchMedaille($combat) && $combat->configNotation->estKataEquipe())
            ? ['kata', 'bunkai']
            : [null];

        foreach ([$combat->inscription_aka_id, $combat->inscription_ao_id] as $inscriptionId) {
            // Le kata choisi à l'inscription (Inscription::kata_id), affiché
            // aux juges/écran public — mêmes règles que OrdrePassageController::assigner().
            $kataId = Inscription::find($inscriptionId)?->kata_id;

            foreach ($phases as $phase) {
                OrdrePassage::create([
                    'config_notation_id' => $combat->config_notation_id,
                    'combat_id'          => $combat->id,
                    'inscription_id'     => $inscriptionId,
                    'kata_id'            => $kataId,
                    'phase'              => $phase,
                    'ordre'              => ++$ordre,
                    'status'             => OrdrePassage::STATUS_NOT_STARTED,
                ]);
            }
        }
    }

    public function estMatchMedaille(Combat $combat): bool
    {
        return $combat->etape === 'Finale' || str_starts_with($combat->etape, 'Bronze_');
    }

    /**
     * Vainqueur d'un combat au vote majoritaire (Art. 5.4.2/5.5.1/5.10.1) :
     * chaque juge compare la somme de ses propres notes pour Aka et pour Ao
     * (sur toutes les prestations de ce camp — une pour un duel normal,
     * kata+bunkai pour une finale d'équipe) et vote pour le mieux noté.
     *
     * En cas d'égalité des votes, l'Annexe 4 du règlement (tableau des
     * critères de départage) ne prévoit AUCUNE procédure pour un duel en
     * élimination directe — contrairement au classement de poule (Art.
     * 5.11/5.12 : confrontation directe, kata supplémentaire, etc.), qui ne
     * concerne que le classement d'un groupe entier, pas un duel isolé. On
     * ne peut donc pas fabriquer un critère de départage supplémentaire ici.
     * C'est l'Art. 10 (« Issues not specifically covered by the rules ») qui
     * s'applique : la décision revient au Chief Referee — le superviseur du
     * tatami dans l'app — via trancherEgaliteJuges().
     */
    public function determinerVainqueurDuel(Combat $combat): array
    {
        $notesAka = $this->totalNotesParJuge($combat->ordrePassageAka);
        $notesAo  = $this->totalNotesParJuge($combat->ordrePassageAo);

        $votesAka = 0;
        $votesAo  = 0;

        foreach ($notesAka as $jugeId => $totalAka) {
            if (!array_key_exists($jugeId, $notesAo)) continue; // juge n'ayant pas noté les deux camps

            $totalAo = $notesAo[$jugeId];
            if ($totalAka > $totalAo) $votesAka++;
            elseif ($totalAo > $totalAka) $votesAo++;
        }

        $vainqueurId = match (true) {
            $votesAka > $votesAo => $combat->inscription_aka_id,
            $votesAo > $votesAka => $combat->inscription_ao_id,
            default              => null, // égalité — Art. 10, décision du superviseur
        };

        return ['vainqueur_id' => $vainqueurId, 'votes_aka' => $votesAka, 'votes_ao' => $votesAo];
    }

    /**
     * Total des notes de chaque juge pour un camp, toutes prestations
     * confondues (une normalement, kata+bunkai pour une finale d'équipe).
     */
    private function totalNotesParJuge($ordrePassages): array
    {
        $totaux = [];
        foreach ($ordrePassages as $passage) {
            foreach ($passage->notes as $note) {
                $totaux[$note->rotation_arbitre_id] = ($totaux[$note->rotation_arbitre_id] ?? 0) + (float) $note->valeur;
            }
        }
        return $totaux;
    }

    /**
     * Détail par juge des notes des deux camps et du vote qui en résulte
     * (Art. 5.4.2 : chaque juge compare ses deux propres notes), pour
     * l'affichage public façon feuille de match WKF — les deux notes d'un
     * même juge côte à côte plutôt qu'un camp puis l'autre séparément.
     * Un juge sans note des deux côtés a un vote null (en attente).
     */
    public function detailVotesJuges(Combat $combat): array
    {
        $notesAka = $this->totalNotesParJuge($combat->ordrePassageAka);
        $notesAo  = $this->totalNotesParJuge($combat->ordrePassageAo);

        $jugeIds = array_unique(array_merge(array_keys($notesAka), array_keys($notesAo)));

        $rotations = RotationArbitre::whereIn('id', $jugeIds)
            ->with('arbitreCompetition.user:id,fullname')
            ->get()
            ->keyBy('id');

        $lignes = array_map(function ($jugeId) use ($notesAka, $notesAo, $rotations) {
            $rotation = $rotations->get($jugeId);
            $noteAka  = $notesAka[$jugeId] ?? null;
            $noteAo   = $notesAo[$jugeId] ?? null;

            $vote = null;
            if ($noteAka !== null && $noteAo !== null) {
                if ($noteAka > $noteAo) $vote = 'aka';
                elseif ($noteAo > $noteAka) $vote = 'ao';
                // sinon note identique des deux côtés pour ce juge → pas de vote
            }

            return [
                'poste'    => $rotation?->poste,
                'juge'     => $rotation?->arbitreCompetition?->user?->fullname ?? "Inconnu",
                'note_aka' => $noteAka,
                'note_ao'  => $noteAo,
                'vote'     => $vote,
            ];
        }, $jugeIds);

        usort($lignes, fn($a, $b) => ($a['poste'] ?? 999) <=> ($b['poste'] ?? 999));

        return $lignes;
    }

    /**
     * À appeler après qu'un passage devienne Terminé ou Kiken. Un Kiken fait
     * gagner l'adversaire immédiatement, sans attendre sa propre prestation
     * (même logique que les byes Kumite). Sinon, résout le combat dès que
     * les deux camps ont terminé toutes leurs prestations.
     */
    public function resoudreDuelSiComplet(OrdrePassage $passage): ?Combat
    {
        if (!$passage->combat_id) return null;

        $combat = Combat::find($passage->combat_id);
        if (!$combat || $combat->status === Combat::STATUS_TERMINE) return null;

        if ($passage->status === OrdrePassage::STATUS_KIKEN) {
            $vainqueurEstAka = $passage->inscription_id !== $combat->inscription_aka_id;
            $vainqueurId = $vainqueurEstAka ? $combat->inscription_aka_id : $combat->inscription_ao_id;

            // Art. 5.11 : "In the case of Kiken, the winning Athlete/Team
            // will be awarded 4 votes for the bout."
            return $this->cloreCombat(
                $combat,
                $vainqueurId,
                'kiken',
                $vainqueurEstAka ? 4 : 0,
                $vainqueurEstAka ? 0 : 4
            );
        }

        $tousTermines = OrdrePassage::where('combat_id', $combat->id)
            ->get()
            ->every(fn($op) => in_array($op->status, [OrdrePassage::STATUS_FINISHED, OrdrePassage::STATUS_KIKEN]));

        if (!$tousTermines) return null;

        $resultat = $this->determinerVainqueurDuel($combat);

        if (is_null($resultat['vainqueur_id'])) {
            $combat->update([
                'status'    => Combat::STATUS_HANTEI,
                'votes_aka' => $resultat['votes_aka'],
                'votes_ao'  => $resultat['votes_ao'],
            ]);
            return $combat;
        }

        return $this->cloreCombat($combat, $resultat['vainqueur_id'], 'points', $resultat['votes_aka'], $resultat['votes_ao']);
    }

    /**
     * Décision manuelle du superviseur quand le vote des juges est à
     * égalité (Combat::STATUS_HANTEI). Ce n'est pas une procédure Hantei
     * officielle du Kata (l'Annexe 4 du règlement ne prévoit aucun critère
     * de départage pour un duel en élimination directe) — le nom/statut
     * partagé vient du Kumite, où "Hantei" est bien une règle officielle.
     * Pour le Kata c'est l'Art. 10 qui s'applique : décision du Chief
     * Referee (le superviseur du tatami) faute de règle spécifique.
     */
    public function forcerHantei(Combat $combat, string $vainqueurCote): Combat
    {
        $vainqueurId = $vainqueurCote === 'aka' ? $combat->inscription_aka_id : $combat->inscription_ao_id;

        return $this->cloreCombat($combat, $vainqueurId, 'hantei', $combat->votes_aka ?? 0, $combat->votes_ao ?? 0);
    }

    private function cloreCombat(Combat $combat, string $vainqueurId, string $typeVictoire, int $votesAka, int $votesAo): Combat
    {
        $combat->update([
            'vainqueur_id'  => $vainqueurId,
            'type_victoire' => $typeVictoire,
            'status'        => Combat::STATUS_TERMINE,
            'votes_aka'     => $votesAka,
            'votes_ao'      => $votesAo,
        ]);

        if ($combat->poule_id) {
            $this->finaliserCombatPoule($combat);
        }

        app(BracketService::class)->propaguerVainqueur($combat, $vainqueurId);

        if (str_contains($combat->etape, 'Demi-finale')) {
            app(BracketService::class)->verifierEtLancerRepechage($combat->configNotation);

            // Le repêchage (Bronze_*/Repechage_*) est peuplé directement par
            // BracketService via assignerCombattants(), pas via next_combat_id
            // — donc cloreCombat() ne le detecte pas plus bas. On crée ici les
            // passages de tout combat de repêchage déjà prêt (byes déjà
            // Combat::STATUS_TERMINE ignorés par creerPassagesPourCombat).
            Combat::where('config_notation_id', $combat->config_notation_id)
                ->where(function ($q) {
                    $q->where('etape', 'LIKE', 'Bronze_%')
                        ->orWhere('etape', 'LIKE', 'Repechage_%');
                })
                ->whereNotNull('inscription_aka_id')
                ->whereNotNull('inscription_ao_id')
                ->where('status', '!=', Combat::STATUS_TERMINE)
                ->get()
                ->each(fn(Combat $c) => $this->creerPassagesPourCombat($c));
        }

        if ($combat->next_combat_id) {
            $suivant = Combat::find($combat->next_combat_id);
            if ($suivant && $suivant->inscription_aka_id && $suivant->inscription_ao_id) {
                $this->creerPassagesPourCombat($suivant);
            }
        }

        return $combat;
    }

    /**
     * Met à jour le classement de poule après un duel Kata de phase de
     * groupe : points_victoire = 3 par victoire, 0 pour la défaite, aucune
     * égalité possible (Art. 5.5.2 : "the Athlete/Team earns 3 Victory
     * points and the loser zero victory points. No draws are allowed.").
     * On réutilise total_points_marques/encaisses (colonnes partagées avec
     * le Kumite) pour stocker les votes des juges reçus/donnés.
     *
     * Classement/départage de poule (Art. 5.11 individuel / 5.12 équipes) :
     * 1) points_victoire, 2) confrontation directe, 3) somme des votes
     * reçus sur toutes les poules, 4) classement mondial (individuel
     * seulement), 5) kata supplémentaire. Actuellement seuls les critères 1
     * et 3 sont reflétés par le tri (points_victoire desc, marques desc,
     * encaissés asc) — la confrontation directe (2) et le kata
     * supplémentaire (5) ne sont pas encore implémentés ; à faire en
     * complément si des égalités de poule surviennent en pratique.
     * Une fois toutes les poules du tatami terminées, lance la phase
     * éliminatoire si le format choisi est poules_eliminatoire.
     */
    private function finaliserCombatPoule(Combat $combat): void
    {
        $votesAka = $combat->votes_aka ?? 0;
        $votesAo  = $combat->votes_ao ?? 0;
        $akaGagne = $combat->vainqueur_id === $combat->inscription_aka_id;

        DB::table('poule_inscriptions')
            ->where('poule_id', $combat->poule_id)
            ->where('inscription_id', $combat->inscription_aka_id)
            ->update([
                'points_victoire'        => DB::raw('points_victoire + ' . ($akaGagne ? 3 : 0)),
                'total_points_marques'   => DB::raw("total_points_marques + {$votesAka}"),
                'total_points_encaisses' => DB::raw("total_points_encaisses + {$votesAo}"),
            ]);

        DB::table('poule_inscriptions')
            ->where('poule_id', $combat->poule_id)
            ->where('inscription_id', $combat->inscription_ao_id)
            ->update([
                'points_victoire'        => DB::raw('points_victoire + ' . ($akaGagne ? 0 : 3)),
                'total_points_marques'   => DB::raw("total_points_marques + {$votesAo}"),
                'total_points_encaisses' => DB::raw("total_points_encaisses + {$votesAka}"),
            ]);

        $tousFinis = Combat::where('poule_id', $combat->poule_id)
            ->where('id', '!=', $combat->id)
            ->where('status', '!=', Combat::STATUS_TERMINE)
            ->doesntExist();

        if (!$tousFinis) return;

        Poule::where('id', $combat->poule_id)->update(['status' => 2]);

        $toutesLesPoulesFinies = Poule::where('config_notation_id', $combat->config_notation_id)
            ->where('status', '!=', 2)
            ->doesntExist();

        if (!$toutesLesPoulesFinies) return;

        $config = $combat->configNotation()->with('kumiteFormat')->first();

        if ($config?->kumiteFormat?->code !== 'poules_eliminatoire') return;

        app(BracketService::class)->lancerPhaseEliminatoire($config);

        // Les combats du 1er tour éliminatoire (et le Bronze_poule à 1 seule
        // poule) sont peuplés directement par construireBracket()/
        // assignerCombattants(), pas via next_combat_id — creerPassagesPourCombat
        // doit donc être appelé explicitement ici, comme pour le repêchage.
        Combat::where('config_notation_id', $config->id)
            ->whereNull('poule_id')
            ->whereNotNull('inscription_aka_id')
            ->whereNotNull('inscription_ao_id')
            ->where('status', '!=', Combat::STATUS_TERMINE)
            ->get()
            ->each(fn(Combat $c) => $this->creerPassagesPourCombat($c));
    }
}
