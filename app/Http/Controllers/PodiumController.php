<?php

namespace App\Http\Controllers;

use App\Models\Inscription;
use Illuminate\Http\Request;
use App\Models\ConfigNotation;
use Illuminate\Support\Facades\DB;

class PodiumController extends Controller
{
    public function obtenirPodium(ConfigNotation $config)
    {
        // 1. Récupérer tous les athlètes de toutes les poules de cette compétition
        $athletes = DB::table('poule_inscriptions')
            ->join('poules', 'poule_inscriptions.poule_id', '=', 'poules.id')
            ->join('inscriptions', 'poule_inscriptions.inscription_id', '=', 'inscriptions.id')
            ->join('students', 'inscriptions.athlete_id', '=', 'students.id')
            ->where('poules.config_notation_id', $config->id)
            ->select([
                'inscriptions.id as inscription_id',
                'students.fullname as athlete_nom',
                'inscriptions.organisateur_id',
                'inscriptions.organisateur_type',
                'poules.nom as nom_poule',
                'poule_inscriptions.points_victoire',
                'poule_inscriptions.total_points_marques',
                'poule_inscriptions.total_points_encaisses',
            ])
            ->get();

        // 2. Calculer le nombre de combats réels joués par poule pour faire les moyennes
        // Poule de 4 athlètes = 3 combats par athlète. Poule de 3 athlètes = 2 combats.
        $classementGeneral = $athletes->map(function ($athlete) use ($config) {
            $inscription = Inscription::find($athlete->inscription_id);
            $clubNom = $inscription?->organisateur?->name ?? '—';
            // Compter combien de combats ce combattant a réellement disputés
            $nbCombats = DB::table('combats')
                ->where('config_notation_id', $config->id)
                ->where(function ($query) use ($athlete) {
                    $query->where('inscription_aka_id', $athlete->inscription_id)
                        ->orWhere('inscription_ao_id', $athlete->inscription_id);
                })
                ->where('status', 2) // Uniquement les combats terminés
                ->count();

            // Éviter la division par zéro si aucun combat n'a eu lieu
            $diviseur = $nbCombats > 0 ? $nbCombats : 1;

            return [
                'inscription_id'         => $athlete->inscription_id,
                'athlete_nom'            => $athlete->athlete_nom,
                'club_nom'               => $clubNom,
                'nom_poule'              => $athlete->nom_poule,
                'points_victoire'        => $athlete->points_victoire,
                'total_points_marques'   => $athlete->total_points_marques,
                'total_points_encaisses' => $athlete->total_points_encaisses,
                // Calcul des ratios pour le tri équitable inter-poules
                'ratio_victoire'         => $athlete->points_victoire / $diviseur,
                'ratio_marques'          => $athlete->total_points_marques / $diviseur,
                'ratio_encaisses'        => $athlete->total_points_encaisses / $diviseur,
            ];
        });

        // 3. Trier la collection selon les règles de départage
        $classementTrie = $classementGeneral->sort(function ($a, $b) {
            // 1er critère : Plus grand ratio de points de victoire
            if ($a['ratio_victoire'] !== $b['ratio_victoire']) {
                return $b['ratio_victoire'] <=> $a['ratio_victoire'];
            }
            // 2ème critère : Plus grand ratio de points marqués
            if ($a['ratio_marques'] !== $b['ratio_marques']) {
                return $b['ratio_marques'] <=> $a['ratio_marques'];
            }
            // 3ème critère : Moins de points encaissés (ratio le plus bas)
            return $a['ratio_encaisses'] <=> $b['ratio_encaisses'];
        })->values();

        // 4. Structurer la réponse en attribuant les médailles
        $podium = [
            'or'       => $classementTrie->get(0), // 1er global
            'argent'   => $classementTrie->get(1), // 2ème global
            'bronze_1' => $classementTrie->get(2), // 3ème global
            'bronze_2' => $classementTrie->get(3), // 4ème global (Fréquent en Karaté)
            'complet'  => $classementTrie          // Tout le classement du 1er au 7ème
        ];

        return response()->json($podium);
    }
}
