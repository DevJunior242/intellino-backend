<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Examen;
use App\Models\Saison;
use App\Models\Licence;
use App\Models\Evenement;
use App\Models\Affiliation;
use App\Models\Competition;
use App\Models\ExamenResult;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DashBoardLeagueController extends Controller
{
    /**
     * Helper pour centraliser la récupération de la saison active de la Fédération parente
     */
    private function getSaisonActiveFederation($leagueId)
    {
        $league = \App\Models\League::find($leagueId);

        if (!$league || !$league->federation_id) {
            return null;
        }

        return Saison::where('active', true)
            ->where('organisateur_id', $league->federation_id)
            ->where('organisateur_type', 'Federation')
            ->first();
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

        $afiliation = Affiliation::where('league_id', $activeId)
            ->where('saison_id', $saisonActive->id) // Ajouté pour filtrer sur la saison en cours
            ->count();

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
            if ($saisonActive->date_fin && now()->diffInDays($saisonActive->date_fin, false) <= 7) {
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
            $unpaidAffiliations = Affiliation::where('league_id', $activeId)
                ->where('status', Affiliation::STATUS_EN_ATTENTE)
                ->where('saison_id', $saisonActive->id)
                ->get();

            $unpaidCount = $unpaidAffiliations->count();
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

    public function exmenstates(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // Récupération de la saison active de la Fédé
        $currentSaison = $this->getSaisonActiveFederation($activeId);

        if (!$currentSaison) {
            return response()->json(['message' => 'Aucune saison active trouvée.'], 200);
        }

        $saisonId = $currentSaison->id;

        // Grades décernés par cette ligue pendant cette saison
        $gradesDecernees = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $saisonId);
        })
            ->where('decision', 'Admis')
            ->count();

        $totalSessions = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonId)
            ->count();

        $examAVenir = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonId) // Filtré par la saison en cours
            ->where('start_date', '>', now())
            ->count();

        // Calcul du Taux de Réussite Moyen
        $statsCandidats = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $saisonId);
        })
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN decision = 'Admis' THEN 1 ELSE 0 END) as Admis
            ")
            ->first();

        $taux = 0;
        if ($statsCandidats && $statsCandidats->total > 0) {
            $taux = ($statsCandidats->Admis / $statsCandidats->total) * 100;
        }

        $nextExamen = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonId)
            ->where('start_date', '>=', now())
            ->withCount(['candidates' => function ($query) {
                $query->where('status', ExamenCandidat::STATUS_REGISTERED);
            }])
            ->orderBy('start_date', 'asc')
            ->first();

        // Trouver la saison fédérale précédente
        $previousSaison = Saison::where('organisateur_id', $currentSaison->organisateur_id)
            ->where('organisateur_type', 'Federation')
            ->where('date_fin', '<', $currentSaison->date_debut) // Ajusté selon tes colonnes (date_fin / date_debut)
            ->orderBy('date_fin', 'desc')
            ->first();

        // Compter les admis de la saison précédente pour cette ligue
        $countPrevious = 0;
        if ($previousSaison) {
            $countPrevious = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $previousSaison) {
                $q->where('organisateur_id', $activeId)
                    ->where('organisateur_type', $activeType)
                    ->where('saison_id', $previousSaison->id);
            })->where('decision', 'Admis')->count();
        }

        $progression = 0;
        if ($countPrevious > 0) {
            $progression = (($gradesDecernees - $countPrevious) / $countPrevious) * 100;
        }

        // Score de vitalité
        $idsGradesNoirs = Grade::all()->filter->isNoire()->pluck('id');

        $nouveauxDan = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $saisonId);
        })
            ->whereIn('new_grade_id', $idsGradesNoirs)
            ->where('decision', 'Admis')
            ->count();

        $objectifAnnuels = 0;
        if ($previousSaison) {
            $objectifAnnuels = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $previousSaison) {
                $q->where('organisateur_id', $activeId)
                    ->where('organisateur_type', $activeType)
                    ->where('saison_id', $previousSaison->id);
            })
                ->whereIn('new_grade_id', $idsGradesNoirs)
                ->where('decision', 'Admis')
                ->count();
        }

        $objectifAnnuels = $objectifAnnuels ?: 10;

        $scoreQuantite = min(($nouveauxDan / $objectifAnnuels) * 100, 100);
        $vitalityScore = ($scoreQuantite * 0.6) + ($taux * 0.4);

        return response()->json([
            'grades_decernes' => $gradesDecernees,
            'sessions_count' => $totalSessions,
            'sessions_a_venir' => $examAVenir,
            'taux_reussite' => round($taux, 1),
            'next_examen_lieu' => $nextExamen ? $nextExamen->lieu : '',
            'next_exam_date' => $nextExamen ? $nextExamen->start_date : null,
            'next_exam_candidates' => $nextExamen ? $nextExamen->candidates_count : 0,
            'next_examen_grade' => $nextExamen ? $nextExamen->currentGrade?->name : 'Aucune',
            'progression' => round($progression, 1),
            'vitality_score' => round($vitalityScore),
            'nouveaux_dan' => $nouveauxDan,
        ]);
    }
}
