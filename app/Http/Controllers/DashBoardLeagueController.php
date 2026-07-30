<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Saison;
use App\Models\Licence;
use App\Models\Evenement;
use App\Models\Affiliation;
use App\Models\Competition;
use Illuminate\Http\Request;
use App\Models\AffiliationPayment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class DashBoardLeagueController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Saison active applicable à cette ligue : la sienne si elle est
     * indépendante (pas de fédération), sinon celle de sa fédération.
     */
    private function getSaisonActiveFederation($leagueId)
    {
        return $this->saisonActivePour($leagueId, 'Ligue');
    }

    public function stats(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');

        // Récupération de la saison active de la Fédé
        $saisonActive = $this->getSaisonActiveFederation($activeId);

        if (!$saisonActive) {
            return response()->json([
                'total_students' => 0,
                'total_competitions' => 0,
                'total_afiliation' => 0,
            ]);
        }

        $studentsCount = Licence::join('clubs', 'clubs.id', '=', 'licences.club_id')
            ->where('clubs.league_id', $activeId)
            ->where('licences.saison_id', $saisonActive->id)
            ->distinct('licences.student_id')
            ->count('licences.student_id');

        $event = Evenement::where('organisateur_id', $activeId)
            ->withCount('competitions')
            ->count();
        // Nombre de clubs de la ligue à jour de leur affiliation (saison active)
        $league = \App\Models\League::find($activeId);
        $clubIds = $league->clubs->pluck('id');

        $affiliation = Affiliation::where('federation_id', $league->federation_id)
            ->where('saison_id', $saisonActive->id)
            ->first();

        $afiliation = $affiliation
            ? AffiliationPayment::where('affiliation_id', $affiliation->id)
            ->where('status', 'paid')
            ->whereIn('club_id', $clubIds)
            ->count()
            : 0;

        return response()->json([
            'total_students' => $studentsCount,
            'total_competitions' => $event,
            'total_afiliation' => $afiliation,
        ]);
    }

    public function Alert(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type'); // Reste 'Ligue'

        // Récupération de la saison active de la Fédé
        $saisonActive = $this->getSaisonActiveFederation($activeId);

        $expiredCount = 0;
        $clubsAlert = collect([]);
        $unpaidCount = 0;

        if ($saisonActive) {
            // 2. Vérification de la fin de saison nationale (< 7 jours)
            if ($saisonActive->dateFin && now()->diffInDays($saisonActive->dateFin, false) <= 7) {
                $expiredLicences = Licence::join('clubs', 'clubs.id', '=', 'licences.club_id')
                    ->where('clubs.league_id', $activeId)
                    ->where('licences.saison_id', $saisonActive->id)
                    ->select('clubs.name as club_name')
                    ->get();

                $expiredCount = $expiredLicences->count();
                $clubsAlert = $expiredLicences->pluck('club_name')
                    ->filter()
                    ->unique()
                    ->take(3)
                    ->values();
            }

            // Cotisations impayées pour la ligue sur cette saison
            $league = \App\Models\League::find($activeId);
            $clubIds = $league->clubs->pluck('id');

            $affiliation = Affiliation::where('federation_id', $league->federation_id)
                ->where('saison_id', $saisonActive->id)
                ->first();

            $unpaidCount = $affiliation
                ? $clubIds->count() - AffiliationPayment::where('affiliation_id', $affiliation->id)
                ->where('status', 'paid')
                ->whereIn('club_id', $clubIds)
                ->count()
                : 0;
        }

        // Examens propres à la ligue
        $exam = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->whereBetween('start_date', [now(), now()->addDays(5)])
            ->withCount('candidates')
            ->first();

        // Compétitions propres à la ligue
        $competition = Competition::whereHas('evenement', function ($q) use ($activeId, $activeType) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType);
        })
            ->where('status', Competition::STATUT_EN_COURS)
            ->withCount('inscriptions')
            ->latest()
            ->first();

        return response()->json([
            'alerts' => [
                [
                    'type' => 'licences_expirees',
                    'count' => $expiredCount,
                    'clubs' => $clubsAlert,
                ],
                [
                    'type' => 'cotisations_impayees',
                    'count' => $unpaidCount,
                    'clubs' => $clubsAlert,
                    'message' => 'Échéance dépassée depuis >30 jours'
                ],
                [
                    'type' => 'examens',
                    'count' => $exam?->candidates_count ?? 0, // Correction typo : 'candidates' au lieu de 'candidats' pour correspondre au withCount
                    'date' => $exam?->start_date, // Correction de $exam->date en start_date
                    'message' => 'jury à confirmer',
                ],
                [
                    'type' => 'competition',
                    'count' => $competition?->inscriptions_count ?? 0,
                    'date_limit' => $competition?->date_fin,
                    'message' => 'inscriptions ouvertes',
                ]
            ]
        ]);
    }
}
