<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Student;
use App\Models\SessionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SessionStatsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SessionController extends Controller
{
    use AuthorizesRequests;
    public function students($sessionId, Request $request)
    {


        $clubId = $request->attributes->get('club_id');

        $session = SessionModel::with('course')->findOrFail($sessionId);

        $gradeId = $session?->course?->current_grade_id;
        if (!$clubId && $request->validated_role_name === 'super_admin') {
            $clubId = $request->query('club_id') ?? $session?->course?->club_id;
            Log::info('clubId super dmin', ['clubId' => $clubId]);
        }
        $students = Student::with(['club', 'currentGrade'])
            ->where('club_id', $clubId)
            ->whereHas('currentGrade', function ($q) use ($gradeId) {
                $q->where('current_grade_id', $gradeId);
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'students' => $students
        ]);
    }


    public function cancel(SessionModel $session, Request $request)
    {
        $request->validate([
            'cancel_reason' => ['required', 'string', 'regex:/^[a-zA-Z0-9\s\.,\'\"\-!?éèàùûô]+$/u', 'min:5', 'max:1000']
        ], [
            'cancel_reason.required' => 'La raison de l\'annulation est requise.',
            'cancel_reason.min' => 'La raison de l\'annulation doit comporter au moins 5 caractères.',
            'cancel_reason.max' => 'La raison de l\'annulation doit comporter au plus 1000 caractères.',
            'cancel_reason.regex' => 'La raison de l\'annulation doit être une chaîne de caractères alphanumériques.',
        ]);

        if ($session->status === 'cancelled') {
            return response()->json([
                'message' => 'Session déjà annulée'
            ], 400);
        }

        $session->update([
            'status' => 3,
            'cancel_reason' => $request->cancel_reason,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session annulée avec succès',
            'session' => $session,
        ], 201);
    }


    public function reschedule(SessionModel $session, Request $request)
    {
        $request->validate([
            'session_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i,H:i:s',
            'end_time'   => 'required|date_format:H:i,H:i:s|after:start_time',

        ], [
            'session_date.required' => 'La date de la séance est requise.',
            'start_time.required' => 'L\'heure de début est requise.',
            'end_time.required' => 'L\'heure de fin est requise.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
        ]);

        $session->update([
            'old_session_date' => $session->session_date,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'replacement_start_time' => $session->start_time,
            'end_time' => $request->end_time,
            'replacement_end_time' => $session->end_time,
            'parent_session_id' => $session->id,
            'status' => 0,

        ]);

        return response()->json(
            [
                'success' => true,
                'message' => 'Cours reporté avec succès'
            ],
            201
        );
    }

    public function startSession(SessionModel $session, Request $request)
    {
        //demarer une session avec un nouveau horaire carbone
        $request->validate([
            'actual_start_time' => 'nullable|date_format:H:i',
        ]);
        if ($session->status !== SessionModel::STATUS_SCHEDULED) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session ne peut pas être démarrée (Statut : ' . $session->getStatutLabelAttribute() . ')'
            ], 400);
        }

        $session->update([
            'actual_start_time' => Carbon::now()->format('H:i:s'),
            'status' => SessionModel::STATUS_ONGOING,
        ]);
        return response()->json([
            'success' => true,
            'session' => $session,
            'message' => 'Session actualisée avec succès'
        ], 201);
    }
    public function stopSession(SessionModel $session, Request $request)
    {

        $request->validate([
            'actual_end_time' => 'nullable|date_format:H:i',
        ]);

        if ($session->status !== SessionModel::STATUS_ONGOING) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session ne peut pas être arrêtée (Statut : ' . $session->getStatutLabelAttribute() . ')'
            ], 400);
        }

        $session->update([
            'actual_end_time' => Carbon::now()->format('H:i:s'),
            'status' => SessionModel::STATUS_COMPLETED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session actualisée avec succès'
        ], 201);
    }


    public function __invoke(SessionStatsService $stats, Request $request)
    {

        $clubId = $request->validated_club_id;
        return response()->json($stats->getStats($clubId));
    }
    public function editSession(SessionModel $session, Request $request)
    {
        $this->authorize('update', $session);
        $request->validate([
            'title' => 'required|string|min:5',
            'session_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i,H:i:s',
            'end_time'   => 'required|date_format:H:i,H:i:s|after:start_time',
        ], [
            'title.required' => 'Le titre de la session est requis.',
            'session_date.required' => 'La date de la session est requise.',
            'start_time.required' => 'L\'heure de début est requise.',
            'end_time.required' => 'L\'heure de fin est requise.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
        ]);
        $session->update([
            'title' => $request->title,
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Session mise à jour avec succès'
        ], 201);
    }

    public function removeSession(SessionModel $session)
    {
        $this->authorize('delete', $session);
        $session->delete();
        return response()->json([
            'success' => true,
            'message' => 'Session supprimée avec succès',
        ]);
    }
}
