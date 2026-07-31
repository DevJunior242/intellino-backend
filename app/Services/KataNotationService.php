<?php

namespace App\Services;

use App\Models\Note;
use App\Events\NoteAjoutee;
use App\Models\OrdrePassage;
use App\Models\RotationArbitre;

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
}
