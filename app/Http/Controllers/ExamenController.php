<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Grade;
use App\Models\Examen;
use App\Models\Student;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use Illuminate\Support\Facades\DB;
use App\Services\ExamenStatService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Notifications\ExamenChanged;
use App\Notifications\ExamenCreated;
use App\Notifications\ExamenCanceled;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreExamenRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExamenController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {

        $user = auth()->user();
        $clubId = $request->attributes->get('club_id');
        //super_admin 
        if ($user?->globalRole?->name == 'super_admin') {
            $examen = Examen::with(['club.users', 'currentGrade:id,name'])
                ->select('id', 'club_id', 'current_grade_id', 'start_date', 'status')
                ->orderBy('start_date', 'desc')
                ->latest()
                ->paginate(8);

            return response()->json([
                'success' => true,
                'message' => 'examens récupérés avec succès',
                'examens' => $examen
            ], 200);
        }

        $examen = Examen::with(['club.users', 'currentGrade:id,name'])
            ->select('id', 'club_id', 'current_grade_id', 'start_date', 'status')
            ->where('club_id', $clubId)
            ->orderBy('start_date', 'desc')
            ->latest()
            ->paginate(8);


        return response()->json([
            'success' => true,
            'message' => 'examens récupérés avec succès',
            'examens' => $examen
        ], 200);
    }
    //obtenir les candidats d'un examen
    public function getExamenCandidats(Examen $examen)
    {
        $user = auth()->user();


        $candidats = ExamenCandidat::with('student:id,fullname,birthdate')
            ->where('examen_id', $examen->id)
            ->where('status', 'registered')
            ->get();
        return response()->json([
            'success' => true,
            'message' => $candidats->isEmpty() ? 'Aucun candidat trouvé' : 'candidats trouvés',
            'candidats' => $candidats
        ], 200);
    }
    public function store(StoreExamenRequest $request)
    {

        $this->authorize('create', Examen::class);

        try {
            $user = auth()->user();
            $clubId = $request->attributes->get('club_id');
            $validated = $request->validated();

            $curentGrade = Grade::where('id', $request->current_grade_id)
                ->first();
            if (!$curentGrade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade spécifié n\'existe pas',
                ]);
            }
            //current_dade_id " next_grade_id
            $nextGrade = Grade::where('id', $request->next_grade_id)
                ->first();
            if (!$nextGrade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade spécifié n\'existe pas',
                ]);
            }

            if ($curentGrade->id == $nextGrade->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade spécifié est identique au suivant',
                ], 400);
            }

            $examenData = DB::transaction(function () use ($validated, $user, $clubId, $request) {

                $students = Student::where('club_id', $clubId)
                    // ->where('status', Student::STATUS_ACTIV)
                    ->whereHas('currentGrade', function ($q) use ($request) {
                        $q->where('current_grade_id', $request->current_grade_id)
                            ->whereDate('awarded_at', '<=', $request->start_date);
                    })
                    ->with('currentGrade')
                    ->get();
                if ($students->isEmpty()) {
                    return null;
                }

                // Créer l'examen
                $examen = Examen::create([
                    ...$validated,
                    'club_id' => $clubId ?? null,
                    'created_by' => $user->id,
                ]);

                // Créer les candidats
                ExamenCandidat::insert(
                    $students->map(fn($student) => [
                        'id' => Str::uuid(),
                        'examen_id' => $examen->id,
                        'student_id' => $student->id,
                        'status' => ExamenCandidat::STATUS_REGISTERED,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray()
                );

                return $examen;
            });

            // Vérifier le résultat et renvoyer la réponse
            if (!$examenData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun étudiant trouvé pour ce grade à cette date.',
                    'data' => $examenData
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Examen et candidats ajoutés avec succès',
                'data' => $examenData
            ], 201);
            //envoyer notif aux candidats
            //load relation
            $candidats = $examenData->candidates;
            Log::info('Examen created', ['examen' => $examenData]);
            if ($candidats->isNotEmpty()) {
                Notification::send($candidats, new ExamenCreated($examenData));
            }
        } catch (QueryException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'examen',
            ], 400);
        }
    }


    public function show(Examen $examen)
    {

        $examen->load([
            'club.users',
            'currentGrade:id,name',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'liste d\'examen ',
            'examen' => $examen,
        ]);
    }



    public function cancel(Examen $examen, Request $request)
    {
        $request->validate([
            'cancel_reason' => ['required', 'string', 'regex:/^[a-zA-Z0-9\s\.,\'\"\-!?éèàùûô]+$/u', 'min:5', 'max:1000']
        ], [
            'cancel_reason.required' => 'La raison de l\'annulation est requise.',
            'cancel_reason.min' => 'La raison de l\'annulation doit comporter au moins 5 caractères.',
            'cancel_reason.max' => 'La raison de l\'annulation doit comporter au plus 1000 caractères.',
            'cancel_reason.regex' => 'La raison de l\'annulation doit être une chaîne de caractères alphanumériques.',
        ]);

        if ($examen->status === 'cancelled') {
            return response()->json([
                'message' => 'examen déjà annulée'
            ], 400);
        }

        $examen->update([
            'status' => 3,
            'cancel_reason' => $request->cancel_reason,
            'cancelled_at' => now(),
        ]);
        //envoyer notif aux candidats
        //load relation
        $candidats = $examen->candidates;
        if ($candidats->isNotEmpty()) {
            Notification::send($candidats, new ExamenCanceled($examen));
        }

        return response()->json([
            'success' => true,
            'message' => 'examen annulée avec succès',
            'examen' => $examen,
        ], 201);
    }


    public function reschedule(Examen $examen, Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:today',
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'replacement_start_time' => 'nullable|date_format:H:i',
            'replacement_end_time' => 'nullable|after:replacement_start_time',
        ], [
            'start_date.required' => 'La date de la séance est requise.',
            'start_date.after' => 'La date de début doit être après la date de fin.',
            'start_time.required' => 'L\'heure de début est requise.',
            'end_time.required' => 'L\'heure de fin est requise.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',

        ]);

        $examen->update([
            'old_start_date' => $examen->start_date,
            'start_date' => $request->start_date,
            'old_end_date' => $examen->end_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'replacement_start_time' => $examen->start_time,
            'replacement_end_time' => $examen->end_time,
            'status' => Examen::STATUS_SCHEDULED,
            'created_by' => $user->id,

        ]);

        //doit passer par user->students->candidates
        $studentsToNotify = User::whereHas('students.candidates', function ($query) use ($examen) {
            $query->where('examen_id', $examen->id)
                ->where('status', ExamenCandidat::STATUS_REGISTERED);
        })
            ->get()
            ->unique('id');
        if ($studentsToNotify->isNotEmpty()) {
            Notification::send($studentsToNotify, new ExamenChanged($examen));
        }

        return response()->json(
            [
                'success' => true,
                'message' => 'Cours reporté avec succès'
            ],
            201
        );
    }

    public function startExamen(Examen $examen, Request $request)
    {
        //demarer une examen avec un nouveau horaire carbone
        $request->validate([
            'actual_start_time' => 'nullable|date_format:H:i',
        ]);
        if ($examen->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Cette examen ne peut pas être démarrée (Statut : ' . $examen->status . ')'
            ], 400);
        }

        $examen->update([
            'actual_start_time' => Carbon::now()->format('H:i:s'),
            'status' => 1,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'examen actualisée avec succès'
        ], 201);
    }
    public function stopExamen(Examen $examen, Request $request)
    {

        $request->validate([
            'actual_end_time' => 'nullable|date_format:H:i',
        ]);

        if ($examen->status !== 'ongoing') {
            return response()->json([
                'success' => false,
                'message' => 'Cette examen ne peut pas être arrêtée (Statut : ' . $examen->status . ')'
            ], 400);
        }

        $examen->update([
            'actual_end_time' => Carbon::now()->format('H:i:s'),
            'status' => 2,

        ]);

        return response()->json([
            'success' => true,
            'message' => 'examen actualisée avec succès'
        ], 201);
    }

    public function stats(ExamenStatService $stats, Request $request)
    {
        $this->authorize('viewStats', Examen::class);
        $clubId = $request->attributes->get('club_id');
        return response()->json($stats->getExamenStats($clubId));
    }
}
