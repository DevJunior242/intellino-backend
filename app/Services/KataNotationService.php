<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Poule;
use App\Models\Combat;
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
    public function trouverRotationActive(string $configNotationId, int $userId): ?RotationArbitre
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

        $score = $this->calculerScore(
            $dataNotes->pluck('valeur')->toArray(),
            $nbJugesAttendus
        );

        $athleteNom = $ordrePassage->inscription?->athlete?->fullname ?? "Athlète inconnu";

        return [
            'notes'            => $dataNotes,
            'total'            => $dataNotes->count(),
            'attendu'          => $nbJugesAttendus,
            'score'            => $score,
            'termine'          => !is_null($score),
            'message'          => is_null($score)
                ? "En attente des notes ({$dataNotes->count()}/{$nbJugesAttendus})"
                : "L'athlète {$athleteNom} a obtenu {$score} points",
        ];
    }

    /**
     * Score retenu d'un passage : on écarte la note la plus haute et la
     * plus basse données par les juges (2 de chaque côté à 7 juges) et on
     * additionne les notes restantes (WKF Kata Competition Rules, Art. 5.4).
     */
    public function calculerScore(array $valeurs, int $nbJugesAttendus): ?float
    {
        // On ne calcule que si TOUS les juges ont noté
        if (count($valeurs) < $nbJugesAttendus) return null;

        sort($valeurs);

        $nbAEnlever = ($nbJugesAttendus === 7) ? 2 : 1;

        $retenues = array_slice($valeurs, $nbAEnlever, count($valeurs) - ($nbAEnlever * 2));

        return (float) array_sum($retenues);
    }

    /**
     * Départage entre deux athlètes à score retenu égal. WKF Art. 5.11 prévoit
     * (pour un système à duels) : points de victoire → confrontation directe →
     * somme des votes → classement mondial → kata supplémentaire. Rien de tout
     * ça ne s'applique à une notation séquentielle sans duel ni classement
     * mondial : on compare donc la somme de TOUTES les notes (y compris les
     * extrêmes écartées du score retenu), puis la note individuelle la plus
     * haute reçue. Une égalité persistante au-delà signifie qu'un kata
     * supplémentaire est nécessaire (Art. 5.11.5) — l'app ne peut pas trancher
     * seule dans ce cas et renvoie 0 (égalité).
     */
    public function departagerEgalite(array $valeursA, array $valeursB): int
    {
        $sommeA = array_sum($valeursA);
        $sommeB = array_sum($valeursB);

        if ($sommeA !== $sommeB) {
            return $sommeB <=> $sommeA;
        }

        $maxA = empty($valeursA) ? 0 : max($valeursA);
        $maxB = empty($valeursB) ? 0 : max($valeursB);

        return $maxB <=> $maxA;
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
            ->each(fn (Combat $combat) => $this->creerPassagesPourCombat($combat));
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
            foreach ($phases as $phase) {
                OrdrePassage::create([
                    'config_notation_id' => $combat->config_notation_id,
                    'combat_id'          => $combat->id,
                    'inscription_id'     => $inscriptionId,
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
     * Vainqueur d'un combat au vote majoritaire : chaque juge compare la
     * somme de ses propres notes pour Aka et pour Ao (sur toutes les
     * prestations de ce camp — une pour un duel normal, kata+bunkai pour une
     * finale d'équipe) et vote pour le mieux noté. Égalité de votes → repli
     * sur departagerEgalite() ; égalité persistante → vainqueur_id null,
     * décision Hantei manuelle requise (Art. 5.11 adapté, pas de duel WKF
     * pur ne prévoit ce cas mais un panel à effectif impair ne devrait
     * normalement jamais y arriver, sauf juge manquant).
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

        if ($votesAka === $votesAo) {
            $cmp = $this->departagerEgalite(array_values($notesAka), array_values($notesAo));
            if ($cmp === 0) {
                return ['vainqueur_id' => null, 'votes_aka' => $votesAka, 'votes_ao' => $votesAo];
            }
            $vainqueurId = $cmp < 0 ? $combat->inscription_aka_id : $combat->inscription_ao_id;
        } else {
            $vainqueurId = $votesAka > $votesAo ? $combat->inscription_aka_id : $combat->inscription_ao_id;
        }

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
            $vainqueurId = $passage->inscription_id === $combat->inscription_aka_id
                ? $combat->inscription_ao_id
                : $combat->inscription_aka_id;

            return $this->cloreCombat($combat, $vainqueurId, 'kiken', 0, 0);
        }

        $tousTermines = OrdrePassage::where('combat_id', $combat->id)
            ->get()
            ->every(fn ($op) => in_array($op->status, [OrdrePassage::STATUS_FINISHED, OrdrePassage::STATUS_KIKEN]));

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
     * égalité (Combat::STATUS_HANTEI).
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
                ->each(fn (Combat $c) => $this->creerPassagesPourCombat($c));
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
     * groupe : points_victoire = nb de bouts gagnés (Art. 3.7.5 WKF —
     * classement au nombre de victoires, pas au score), et on réutilise
     * total_points_marques/encaisses (colonnes partagées avec le Kumite)
     * pour stocker les votes des juges reçus/donnés — le départage à
     * égalité de victoires (Art. 5.11 : confrontation directe -> somme des
     * votes) retombe alors sur exactement la même requête de classement que
     * le Kumite (points_victoire desc, marques desc, encaissés asc).
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
                'points_victoire'        => DB::raw('points_victoire + ' . ($akaGagne ? 1 : 0)),
                'total_points_marques'   => DB::raw("total_points_marques + {$votesAka}"),
                'total_points_encaisses' => DB::raw("total_points_encaisses + {$votesAo}"),
            ]);

        DB::table('poule_inscriptions')
            ->where('poule_id', $combat->poule_id)
            ->where('inscription_id', $combat->inscription_ao_id)
            ->update([
                'points_victoire'        => DB::raw('points_victoire + ' . ($akaGagne ? 0 : 1)),
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
            ->each(fn (Combat $c) => $this->creerPassagesPourCombat($c));
    }
}
