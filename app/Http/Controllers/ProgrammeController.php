<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\Course;
use App\Models\Examen;
use App\Models\Evenement;
use App\Models\SessionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class ProgrammeController extends Controller
{
    use ResolvesActiveSaison;

    /**
     * Programme d'activités de la saison pour une Ligue ou une Fédération :
     * agrège Stages + Événements/Compétitions + Examens de grade, classés
     * Réalisées / En cours / Planifiées, pour alimenter le Kanban.
     */
    public function activites(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $saison = $this->saisonActivePour($activeId, $activeType);

        if (!$saison) {
            return response()->json([
                'success' => false,
                'message' => $this->messageAucuneSaisonActivePour($activeId, $activeType),
                'code' => 'aucune_saison_active',
            ], 422);
        }

        $now = now();
        $activites = collect();

        // --- Stages : status non fiabilisé côté modèle, on dérive des dates ---
        $stages = Stage::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saison->id)
            ->get();

        foreach ($stages as $stage) {
            $start = $stage->start_at;
            $end = $stage->end_at ?? $stage->start_at;

            $kanban = 'planifiee';
            if ($now->gt($end)) {
                $kanban = 'realisee';
            } elseif ($now->gte($start)) {
                $kanban = 'en_cours';
            }

            $activites->push([
                'id' => $stage->id,
                'type' => 'stage',
                'title' => $stage->title,
                'category' => $stage->type,
                'date_debut' => $stage->start_at,
                'date_fin' => $stage->end_at,
                'kanban_status' => $kanban,
                'is_late' => $kanban === 'planifiee' && $stage->end_at && $now->gt($stage->end_at),
            ]);
        }

        // --- Événements / compétitions : status fiable, piloté par ouvrir()/cloturer() ---
        $evenements = Evenement::where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saison->id)
            ->get();

        foreach ($evenements as $evenement) {
            $kanban = match ((int) $evenement->status) {
                Evenement::STATUT_TERMINE => 'realisee',
                Evenement::STATUT_EN_COURS => 'en_cours',
                default => 'planifiee',
            };

            $activites->push([
                'id' => $evenement->id,
                'type' => 'competition',
                'title' => $evenement->nom,
                'category' => 'Compétition',
                'date_debut' => $evenement->date_debut,
                'date_fin' => $evenement->date_fin,
                'kanban_status' => $kanban,
                'is_late' => $kanban === 'planifiee' && $evenement->date_fin && $now->gt($evenement->date_fin),
            ]);
        }

        // --- Examens de grade : on exclut les annulés du tableau ---
        $examens = Examen::with('nextGrade:id,name')
            ->where('organisateur_id', $activeId)
            ->where('organisateur_type', $activeType)
            ->where('saison_id', $saison->id)
            ->where('status', '!=', Examen::STATUS_CANCELLED)
            ->get();

        foreach ($examens as $examen) {
            $kanban = match ((int) $examen->status) {
                Examen::STATUS_COMPLETED => 'realisee',
                Examen::STATUS_ONGOING => 'en_cours',
                default => 'planifiee', // scheduled ou postponed
            };

            $activites->push([
                'id' => $examen->id,
                'type' => 'examen',
                'title' => $examen->nextGrade ? "Examen — {$examen->nextGrade->name}" : 'Examen de grade',
                'category' => 'Grades',
                'date_debut' => $examen->start_date,
                'date_fin' => $examen->end_date,
                'kanban_status' => $kanban,
                'is_late' => $kanban === 'planifiee' && $examen->end_date && $now->gt($examen->end_date),
            ]);
        }

        $activites = $activites->sortBy('date_debut')->values();

        $enRetard = $activites->where('is_late', true)->count();

        $total = $activites->count();
        $realisees = $activites->where('kanban_status', 'realisee')->count();

        return response()->json([
            'success' => true,
            'saison' => [
                'id' => $saison->id,
                'libele' => $saison->libele,
            ],
            'stats' => [
                'total' => $total,
                'realisees' => $realisees,
                'en_cours' => $activites->where('kanban_status', 'en_cours')->count(),
                'planifiees' => $activites->where('kanban_status', 'planifiee')->count(),
                'taux_realisation' => $total > 0 ? round(($realisees / $total) * 100) : 0,
                'en_retard' => $enRetard,
            ],
            'activites' => [
                'realisees' => $activites->where('kanban_status', 'realisee')->values(),
                'en_cours' => $activites->where('kanban_status', 'en_cours')->values(),
                'planifiees' => $activites->where('kanban_status', 'planifiee')->values(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $orgId = $request->attributes->get('organisateur_id');
        $range = $request->get('range', 'today');

        if ($range === 'week') {
            $start = now()->startOfDay();
            $end = now()->addDays(7)->endOfDay();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $sessions = SessionModel::whereHas('course', function ($q) use ($orgId) {
            $q->where('organisateur_id', $orgId);
        })
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', SessionModel::STATUS_CANCELLED)
            ->get();

        $examens = Examen::with('currentGrade')
            ->where('organisateur_id', $orgId)
            ->whereBetween('start_date', [$start, $end])
            ->where('status', '!=', Examen::STATUS_CANCELLED)
            ->get();

        $programmes = collect();

        foreach ($sessions as $s) {
            $programmes->push([
                'id' => $s->id,
                'type' => 'cours',
                'title' => $s->title,
                'datetime' => $s->session_date . ' ' . ($s->start_time ?? $s->replacement_start_time),
                'end_datetime' => $s->session_date . ' ' . ($s->end_time ?? $s->replacement_end_time),
                'status' => $s->status,
            ]);
        }

        foreach ($examens as $e) {
            $programmes->push([
                'id' => $e->id,
                'type' => 'examen',
                'title' => 'Examen grade',
                'grade' => $e->currentGrade ? $e->currentGrade->name : null,
                'datetime' => $e->start_date . ' ' . ($e->start_time ?? $e->replacement_start_time),
                'end_datetime' => $e->end_date . ' ' . ($e->end_time ?? $e->replacement_end_time),
                'status' => $e->status,
            ]);
        }

        $programmes = $programmes
            ->sortBy('datetime')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $programmes
        ]);
    }
}
