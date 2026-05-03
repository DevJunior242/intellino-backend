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
    public function stats(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $studentsCount = Licence::where('league_id', $activeId)
            ->distinct('student_id')
            ->count('student_id');

        $event = Evenement::where('organisateur_id', $activeId)
            ->withCount('competitions')

            ->count();
        $afiliation = Affiliation::where('league_id', $activeId)
            ->withCount('club')
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
        $activeType = $request->attributes->get('organisateur_type');

        $expiredLicences = Licence::where('league_id', $activeId)
            ->whereDate('date_expiration', '<=', now()->addDays(7))
            ->with('club')
            ->get();

        $expiredCount = $expiredLicences->count();

        $clubs = $expiredLicences->pluck('club.name')
            ->filter()
            ->unique()
            ->take(3)
            ->values();

        $unpaidAffiliations = Affiliation::where('league_id', $activeId)
            ->where('status', Affiliation::STATUS_EN_ATTENTE)
            ->whereDate('date_fin', '<', now()->subDays(30))
            ->with('club')
            ->get();

        $unpaidCount = $unpaidAffiliations->count();

        $clubs = $expiredLicences->pluck('club.name')
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->toArray();

        $exam = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->whereBetween('start_date', [now(), now()->addDays(5)])
            ->withCount('candidates')
            ->first();

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
                    'clubs' => $clubs,
                ],
                [
                    'type' => 'cotisations_impayees',
                    'count' => $unpaidCount,
                    'clubs' => $clubs,
                    'message' => 'Échéance dépassée depuis >30 jours'
                ],
                [
                    'type' => 'examens',
                    'count' => $exam?->candidats_count ?? 0,
                    'date' => $exam?->date,
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
        // 1. Récupération du contexte
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonId = Saison::where('active', true)->first()?->id;

        $gradesDecernees = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $saisonId);
        })
            ->where('decision', 'Admis')
            ->count();

        // 3. Sessions d'examen (Total cette saison et "à venir")
        $totalSessions = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saisonId)
            ->count();

        $examAVenir = Examen::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('start_date', '>', now())
            ->count();

        // 4. Calcul du Taux de Réussite Moyen
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
        // 1. Récupérer la saison actuelle et la saison précédente
        $currentSaison = Saison::where('id', $saisonId)->first();
        $previousSaison = Saison::where('dateFin', '<', $currentSaison->dateDebut)
            ->orderBy('dateFin', 'desc')
            ->first();

        // 2. Compter les grades pour la saison actuelle
        $countCurrent = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $currentSaison) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $currentSaison->id);
        })->where('decision', 'Admis')->count();

        // 3. Compter les grades pour la saison précédente
        $countPrevious = 0;
        if ($previousSaison) {
            $countPrevious = ExamenResult::whereHas('examen', function ($q) use ($activeId, $activeType, $previousSaison) {
                $q->where('organisateur_id', $activeId)
                    ->where('organisateur_type', $activeType)
                    ->where('saison_id', $previousSaison->id);
            })->where('decision', 'Admis')->count();
        }

        // 4. Calcul de la progression
        $progression = 0;
        if ($countPrevious > 0) {
            $progression = (($countCurrent - $countPrevious) / $countPrevious) * 100;
        }
        return response()->json([
            'grades_decernes' => $gradesDecernees,
            'sessions_count' => $totalSessions,
            'sessions_a_venir' => $examAVenir,
            'taux_reussite' => round($taux, 1),
            'next_examen_lieu' => $nextExamen ? $nextExamen?->lieu : '',
            'next_exam_date' => $nextExamen ? $nextExamen->start_date : 'Aucune',
            'next_exam_candidates' => $nextExamen ? $nextExamen?->candidates_count : 0,
            'next_examen_grade' => $nextExamen ? $nextExamen?->currentGrade?->name : 'Aucune',
            'progression' => $progression,
        ]);
    }


    public function getVitalityScore(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $saisonId = Saison::where('active', true)->first()?->id;

        // 1. Nombre de nouveaux 1er Dan (Ceintures Noires) Admis cette saison
        $idsGradesNoirs = Grade::all()->filter->isNoire()->pluck('id');

        $nouveauxDan = ExamenCandidat::whereHas('examen', function ($q) use ($activeId, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('saison_id', $saisonId);
        })
            ->whereIn('next_grade_id', $idsGradesNoirs)
            ->where('decision', 'dAdmis')
            ->count();

        // 2. Taux de réussite global de la saison
        $statsCandidats = ExamenCandidat::whereHas('examen', function ($q) use ($activeId, $activeType, $saisonId) {
            $q->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->where('saison_id', $saisonId);
        })
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN decision = 'Admis' THEN 1 ELSE 0 END) as Admis")
            ->first();

        $tauxReussite = $statsCandidats->total > 0 ? ($statsCandidats->Admis / $statsCandidats->total) * 100 : 0;

        $currentSaison = Saison::where('id', $saisonId)->first();
        $previousSaison = Saison::where('dateFin', '<', $currentSaison->dateDebut)
            ->orderBy('dateFin', 'desc')
            ->first();

        $objectifAnnuels = ExamenCandidat::whereHas('examen', function ($q) use ($activeId, $activeType, $previousSaison) {
            $q->where('organisateur_id', $activeId)
                ->where('saison_id', $previousSaison->id);
        })
            ->where('next_grade_id', $idsGradesNoirs)
            ->where('decision', 'Admis')
            ->count();

        // Si c'est la première année, on met une valeur par défaut pour éviter la division par zéro
        $objectifAnnuels = $objectifAnnuels ?: 10;
        $scoreQuantite = min(($nouveauxDan / $objectifAnnuels) * 100, 100);
        $vitalityScore = ($scoreQuantite * 0.6) + ($tauxReussite * 0.4);

        return response()->json([
            'vitality_score' => round($vitalityScore),
            'nouveaux_dan' => $nouveauxDan,
            'taux_reussite' => round($tauxReussite, 1)
        ]);
    }
}
