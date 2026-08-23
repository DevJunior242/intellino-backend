<?php

namespace App\Http\Controllers;

use App\Models\Licence;
use App\Models\Student;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;


class CategoryController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Chaque Ligue (affiliée ou non) et chaque Fédération gère et consulte
     * ses propres catégories, de façon indépendante — voir
     * SubDisciplineController::store(). Pas d'héritage entre une ligue et sa
     * fédération : chacune ne voit que ce qu'elle a défini elle-même.
     */
    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!in_array($activeType, ['Ligue', 'Federation'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Seules une ligue ou une fédération peuvent consulter les catégories.',
            ], 403);
        }

        $saisonActive = $this->saisonActivePour($activeId, $activeType);

        if (!$saisonActive) {
            return response()->json([
                'categories'      => [],
                'total_licencies' => 0,
                'message'         => $this->messageAucuneSaisonActivePour($activeId, $activeType),
            ]);
        }

        // Le comptage de licenciés n'a de sens que pour les catégories
        // définies par une Fédération (les licences sont toujours fédérales).
        $federationId = $activeType === 'Federation' ? $activeId : null;
        $leagueId = $activeType === 'Ligue' ? $activeId : null;

        $categories = Category::where('saison_id', $saisonActive->id)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->with('disciplines:id,nom')
            ->orderBy('age_min', 'asc')
            ->get();

        $result = $categories->map(function ($cat) use ($federationId, $leagueId, $saisonActive) {
            $dateAujourdhui = \Carbon\Carbon::now();
            $dateNaissanceMin = $dateAujourdhui->copy()->subYears($cat->age_max)->format('Y-m-d');
            $dateNaissanceMax = $dateAujourdhui->copy()->subYears($cat->age_min)->format('Y-m-d');

            $studentQuery = Student::whereHas('clubs', function ($q) use ($leagueId) {
                if ($leagueId) {
                    $q->where('league_id', $leagueId);
                }
            })
                ->whereBetween('birthdate', [$dateNaissanceMin, $dateNaissanceMax]);

            if ($cat->sexe !== 'Mixte') {
                $studentQuery->where('sex', $cat->sexe);
            }

            $studentCount = $studentQuery->count();
            $licenciesCount = $federationId
                ? $cat->getLicenciesCount($federationId, $saisonActive->id, $leagueId)
                : 0;

            return [
                'id'              => $cat->id,
                'nom'             => $cat->nom,
                'age_min'         => $cat->age_min,
                'age_max'         => $cat->age_max,
                'sexe'            => $cat->sexe,
                'poids_min'       => $cat->poids_min,
                'poids_max'       => $cat->poids_max,
                'licencies_count' => $licenciesCount,
                'student_count'   => $studentCount,
                'taux'            => $studentCount > 0
                    ? round(($licenciesCount / $studentCount) * 100)
                    : 0,
                'disciplines'     => $cat->disciplines->pluck('nom'),
            ];
        });

        $totalLicencies = $federationId
            ? Licence::where('federation_id', $federationId)
                ->where('saison_id', $saisonActive->id)
                ->where('status', Licence::STATUS_PAYE)
                ->when($leagueId, function ($query) use ($leagueId) {
                    $query->whereHas('club', fn($q) => $q->where('league_id', $leagueId));
                })
                ->distinct('student_id')
                ->count('student_id')
            : 0;

        return response()->json([
            'categories'      => $result,
            'total_licencies' => $totalLicencies,
        ]);
    }

    public function getCategories(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $saisonActive = $this->saisonActivePour($activeId, $activeType);

        if (!$saisonActive) {
            return response()->json([], 200);
        }

        $categories = Category::where('saison_id', $saisonActive->id)
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->with('disciplines:id,nom')
            ->orderBy('age_min', 'asc')
            ->get();

        return response()->json($categories);
    }
}
