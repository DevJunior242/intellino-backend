<?php

namespace App\Services;

use App\Models\ConfigNotation;
use App\Models\JugeCompetition;
use App\Models\RotationArbitre;
use App\Models\ArbitreCompetition;

class RotationArbitreService
{
    /**
     * Initialiser la file de rotation au moment de la validation config
     * On prend tous les arbitres inscrits, on les mélange
     * Les X premiers (nb_juges) sont actifs, les autres au banc
     */
    public function initialiser(ConfigNotation $config): void
    {
        $nbJuges  = $config->getNbJuges();
        $nbNecess = $nbJuges + 1; // juges + superviseur

        // Prendre UNIQUEMENT les arbitres pas encore assignés
        // à un autre tatami de cette compétition
        $dejaPris = RotationArbitre::where('config_notation_id', $config->id)
            ->pluck('arbitre_competition_id');

        // load evenement
        $config->load('competition');
        $arbitres = ArbitreCompetition::where('evenement_id', $config?->competition?->evenement_id)
            ->whereNotIn('id', $dejaPris)  //  exclure déjà assignés
            ->inRandomOrder()
            ->take($nbNecess + 5) // prendre un peu plus pour le banc
            ->get();

        if ($arbitres->count() < $nbNecess) {
            throw new \Exception(
                "Pas assez d'arbitres disponibles — 
             besoin {$nbNecess}, 
             disponibles {$arbitres->count()}"
            );
        }


        foreach ($arbitres as $index => $arbitre) {
            $estActif       = $index < $nbJuges;
            $estSuperviseur = $index === $nbNecess;

            RotationArbitre::create([
                'config_notation_id'     => $config->id,
                'arbitre_competition_id' => $arbitre->id,
                'ordre'                  => $index + 1,
                'actif'                  => $estActif,
                'poste'                  => $estActif ? ($index + 1) : null,
                'nb_passages'            => 0,
                'est_superviseur'        => $estSuperviseur,
            ]);
        }

        $this->syncJuges($config);
    }
    /**
     * Tourner après chaque athlète
     * nb_rotation juges sortent, nb_rotation juges entrent
     */
    public function tourner(ConfigNotation $config): void
    {
        $nbRotation = $config->nb_rotation;

        for ($i = 0; $i < $nbRotation; $i++) {
            $this->effectuerUnEchange($config);
        }

        // Incrémenter nb_passages pour tous les actifs
        RotationArbitre::where('config_notation_id', $config->id)
            ->where('actif', true)
            ->increment('nb_passages');

        // Sync avec juge_competitions
        $this->syncJuges($config);
    }

    /**
     * Un seul échange : le juge actif avec le plus de passages
     * cède sa place au premier au banc dans l'ordre
     */
    private function effectuerUnEchange(ConfigNotation $config): void
    {
        // Juge qui sort — celui qui a jugé le plus d'athlètes
        $jugeSort = RotationArbitre::where('config_notation_id', $config->id)
            ->where('actif', true)
            ->orderByDesc('nb_passages')
            ->orderBy('ordre') // départage si égalité
            ->first();

        // Juge qui entre — premier au banc dans l'ordre de la file
        $jugeEntree = RotationArbitre::where('config_notation_id', $config->id)
            ->where('actif', false)
            ->orderBy('ordre')
            ->first();

        // Pas assez d'arbitres au banc — on ne fait rien
        if (!$jugeEntree || !$jugeSort) {
            return;
        }

        $posteLibere = $jugeSort->poste;

        // Le juge actif va au banc
        $jugeSort->update([
            'actif' => false,
            'poste' => null,
        ]);

        // Le juge au banc prend le poste libéré
        $jugeEntree->update([
            'actif' => true,
            'poste' => $posteLibere,
        ]);
    }

    /**
     * Synchroniser juge_competitions avec la rotation active
     * C'est ce qui met à jour les tablettes en temps réel
     */
    private function syncJuges(ConfigNotation $config): void
    {
        $actifs = RotationArbitre::where('config_notation_id', $config->id)
            ->where('actif', true)
            ->with('arbitreCompetition.user')
            ->get();

        foreach ($actifs as $rotation) {
            JugeCompetition::where('config_notation_id', $config->id)
                ->where('numero_juge', $rotation->poste)
                ->update([
                    'user_id'       => $rotation->arbitreCompetition->user_id,
                    'nom_affichage' => $rotation->arbitreCompetition->user->fullname,
                ]);
        }
    }

    /**
     * État actuel de la rotation — pour l'affichage admin
     */
    public function etat(ConfigNotation $config): array
    {
        $tous = RotationArbitre::where('config_notation_id', $config->id)
            ->with('arbitreCompetition.user')
            ->orderBy('ordre')
            ->get();

        return [
            'actifs' => $tous->where('actif', true)
                ->map(fn($r) => [
                    'poste'        => $r->poste,
                    'nom'          => $r->arbitreCompetition->user->fullname,
                    'nb_passages'  => $r->nb_passages,
                ])->values(),

            'banc' => $tous->where('actif', false)
                ->map(fn($r) => [
                    'ordre'        => $r->ordre,
                    'nom'          => $r->arbitreCompetition->user->fullname,
                    'nb_passages'  => $r->nb_passages,
                ])->values(),
        ];
    }
}
