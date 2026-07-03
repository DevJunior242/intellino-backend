<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\League;
use App\Models\Saison;
use App\Models\Licence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationDashboardController extends Controller
{
    public function getStats(Request $request)
    {
        try {
            $activeId = $request->attributes->get('organisateur_id');
            $activeType = $request->attributes->get('organisateur_type');

            // 1. On récupère la saison active de la Fédération
            $saison = Saison::where('active', true)
                ->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->first();

            if (!$saison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune saison active trouvée pour cette fédération.',
                    'stats' => $this->defaultStats()
                ], 200); // On renvoie du vide propre pour éviter de faire planter le front
            }

            // 2. Total des Ligues affiliées à cette Fédération
            $totalLigues = League::where('federation_id', $activeId)->count();

            // 3. Total des Clubs affiliés (via les ligues de cette fédération)
            $totalClubs = Club::join('leagues', 'leagues.id', '=', 'clubs.league_id')
                ->where('leagues.federation_id', $activeId)
                ->count();

            // 4. Statistiques des Licences sur la saison active
            $licencesStats = Licence::where('saison_id', $saison->id)
                ->select(
                    DB::raw('COUNT(id) as total_licences'),
                    DB::raw('SUM(CASE WHEN status = 2 THEN montant_paye ELSE 0 END) as total_recettes'),
                    DB::raw('COUNT(CASE WHEN status = 0 THEN 1 END) as licences_en_attente')
                )
                ->first();

            // 5. Répartition des athlètes par Sexe pour la saison active
            $sexeRepartition = Licence::join('students', 'students.id', '=', 'licences.student_id')
                ->where('licences.saison_id', $saison->id)
                ->select('students.sex', DB::raw('COUNT(licences.id) as total'))
                ->groupBy('students.sex')
                ->get()
                ->pluck('total', 'sex'); // Renvoie un tableau associatif ex: ['M' => 120, 'F' => 45]

            return response()->json([
                'success' => true,
                'saison'  => [
                    'id' => $saison->id,
                    'libele' => $saison->libele,
                ],
                'stats'   => [
                    'total_ligues'         => $totalLigues,
                    'total_clubs'          => $totalClubs,
                    'total_licences'       => $licencesStats->total_licences ?? 0,
                    'licences_en_attente'  => $licencesStats->licences_en_attente ?? 0,
                    'total_recettes'       => floatval($licencesStats->total_recettes ?? 0),
                    'hommes'               => $sexeRepartition->get('M', 0),
                    'femmes'               => $sexeRepartition->get('F', 0) + $sexeRepartition->get('Mixte', 0),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur FederationDashboardController: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques fédérales.'
            ], 500);
        }
    }

    private function defaultStats()
    {
        return [
            'total_ligues' => 0,
            'total_clubs' => 0,
            'total_licences' => 0,
            'licences_en_attente' => 0,
            'total_recettes' => 0,
            'hommes' => 0,
            'femmes' => 0
        ];
    }

    /**
     * Répartition du nombre de clubs par ligue, pour la fédération connectée.
     * Utilisé par le graphique du dashboard Fédération.
     */
    public function clubsParLigue(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');

        $repartition = Club::join('leagues', 'leagues.id', '=', 'clubs.league_id')
            ->where('leagues.federation_id', $activeId)
            ->select('leagues.name as league_name', DB::raw('COUNT(clubs.id) as total_clubs'))
            ->groupBy('leagues.id', 'leagues.name')
            ->orderByDesc('total_clubs')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $repartition,
        ]);
    }
}
