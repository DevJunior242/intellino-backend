<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Combat;
use App\Events\NoteAjoutee;
use App\Events\TatamiUpdated;
use App\Models\OrdrePassage;
use App\Models\RotationArbitre;
use App\Services\BracketService;

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

        // Ce passage fait partie d'un combat Kata (Aka/Ao) : si les deux
        // côtés sont maintenant complets, on calcule le vainqueur.
        if ($passage->combat_id) {
            $this->verifierEtFinaliserCombat($passage->combat);
        }

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
     * Détermine le vainqueur d'un combat Kata (Aka vs Ao) au vote majoritaire
     * des juges : chaque juge compare sa propre note pour Aka et pour Ao, et
     * vote pour celui qu'il a le mieux noté. Le vainqueur est celui qui
     * obtient la majorité des votes (WKF Kata Competition Rules, Art. 5.4.2,
     * 5.5.1, 5.10.1) — on ne fait jamais la somme des notes entre athlètes.
     *
     * Retourne null tant que les deux côtés n'ont pas été notés par tous les
     * juges attendus.
     */
    public function determinerVainqueur(Combat $combat): ?array
    {
        if (!$combat->inscription_aka_id || !$combat->inscription_ao_id) return null;

        $passageAka = OrdrePassage::where('combat_id', $combat->id)
            ->where('inscription_id', $combat->inscription_aka_id)
            ->first();
        $passageAo = OrdrePassage::where('combat_id', $combat->id)
            ->where('inscription_id', $combat->inscription_ao_id)
            ->first();

        if (!$passageAka || !$passageAo) return null;

        $nbJugesAttendus = $combat->configNotation->getNbJuges();

        $notesAka = Note::where('ordre_passage_id', $passageAka->id)->pluck('valeur', 'rotation_arbitre_id');
        $notesAo  = Note::where('ordre_passage_id', $passageAo->id)->pluck('valeur', 'rotation_arbitre_id');

        if ($notesAka->count() < $nbJugesAttendus || $notesAo->count() < $nbJugesAttendus) {
            return null;
        }

        $votesAka = 0;
        $votesAo  = 0;
        $detail   = [];

        foreach ($notesAka as $rotationArbitreId => $noteAka) {
            $noteAo = $notesAo[$rotationArbitreId] ?? null;
            if (is_null($noteAo)) continue; // juge n'ayant pas noté les deux côtés

            $noteAkaF = (float) $noteAka;
            $noteAoF  = (float) $noteAo;
            $vote     = $noteAkaF > $noteAoF ? 'aka' : 'ao';

            $vote === 'aka' ? $votesAka++ : $votesAo++;

            $detail[] = [
                'rotation_arbitre_id' => $rotationArbitreId,
                'note_aka'            => $noteAkaF,
                'note_ao'             => $noteAoF,
                'vote'                => $vote,
            ];
        }

        if ($votesAka === $votesAo) return null; // égalité anormale — pas de vainqueur

        return [
            'detail'                   => $detail,
            'votes_aka'                => $votesAka,
            'votes_ao'                 => $votesAo,
            'vainqueur'                => $votesAka > $votesAo ? 'aka' : 'ao',
            'vainqueur_inscription_id' => $votesAka > $votesAo ? $combat->inscription_aka_id : $combat->inscription_ao_id,
        ];
    }

    /**
     * Si les deux passages du combat sont désormais complets, fige le
     * vainqueur sur le combat et propage vers le tour suivant (BracketService)
     * comme pour un combat de Kumite.
     */
    public function verifierEtFinaliserCombat(Combat $combat): ?array
    {
        $resultat = $this->determinerVainqueur($combat);
        if (is_null($resultat)) return null;

        $combat->update([
            'vainqueur_id'    => $resultat['vainqueur_inscription_id'],
            'type_victoire'   => 'hantei',
            'status'          => Combat::STATUS_TERMINE,
            'score_final_aka' => $resultat['votes_aka'],
            'score_final_ao'  => $resultat['votes_ao'],
        ]);

        app(BracketService::class)->propaguerVainqueur($combat, $resultat['vainqueur_inscription_id']);

        broadcast(new TatamiUpdated($combat->config_notation_id));

        return $resultat;
    }

    /**
     * Tableau de vote d'un combat Kata pour affichage (façon écran d'arbitrage
     * WKF : note de chaque juge pour Aka et Ao, vote, et vainqueur).
     */
    public function resultatCombat(Combat $combat): array
    {
        $passageAka = OrdrePassage::where('combat_id', $combat->id)
            ->where('inscription_id', $combat->inscription_aka_id)
            ->first();
        $passageAo = OrdrePassage::where('combat_id', $combat->id)
            ->where('inscription_id', $combat->inscription_ao_id)
            ->first();

        $nbJugesAttendus = $combat->configNotation->getNbJuges();
        $nbNotesAka = $passageAka ? Note::where('ordre_passage_id', $passageAka->id)->count() : 0;
        $nbNotesAo  = $passageAo ? Note::where('ordre_passage_id', $passageAo->id)->count() : 0;

        $resultat = $this->determinerVainqueur($combat);

        $message = "En attente des notes (Aka {$nbNotesAka}/{$nbJugesAttendus}, Ao {$nbNotesAo}/{$nbJugesAttendus})";
        if ($resultat) {
            $votesVainqueur = $resultat['vainqueur'] === 'aka' ? $resultat['votes_aka'] : $resultat['votes_ao'];
            $votesPerdant   = $resultat['vainqueur'] === 'aka' ? $resultat['votes_ao'] : $resultat['votes_aka'];
            $message = sprintf('Vainqueur : %s %d-%d', strtoupper($resultat['vainqueur']), $votesVainqueur, $votesPerdant);
        }

        return [
            'attendu'                  => $nbJugesAttendus,
            'notes_aka'                => $nbNotesAka,
            'notes_ao'                 => $nbNotesAo,
            'detail'                   => $resultat['detail'] ?? [],
            'votes_aka'                => $resultat['votes_aka'] ?? null,
            'votes_ao'                 => $resultat['votes_ao'] ?? null,
            'vainqueur'                => $resultat['vainqueur'] ?? null,
            'vainqueur_inscription_id' => $resultat['vainqueur_inscription_id'] ?? null,
            'termine'                  => !is_null($resultat),
            'message'                  => $message,
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
}
