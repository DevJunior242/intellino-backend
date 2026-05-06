<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Grade;
use App\Models\Examen;
use App\Models\Saison;
use App\Models\Student;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ExamenCandidat;
use App\Services\BrevoService;
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
        $activeId = $request->validated_organisateur_id ?? $request->attributes->get('organisateur_id');
        $activeType = $request->validated_organisateur_type ?? $request->attributes->get('organisateur_type');

        $query = Examen::with(['organisateur', 'nextGrade:id,name'])
            ->select('id', 'organisateur_id', 'organisateur_type', 'next_grade_id', 'start_date', 'status')
            ->latest();

        // 1. Si Super Admin : voit TOUT
        if ($user?->isSuperAdmin()) {
            $examens = $query->paginate(8);
        }
        // 2. Si Ligue : voit ses propres examens
        else if ($activeType == 'Ligue') {
            $examens = $query->where('organisateur_id', $activeId)
                ->where('organisateur_type', 'Ligue')
                ->paginate(8);
        }
        // 3. Si Club : voit SES examens + les examens OUVERTS de la Ligue
        else {
            $currentClub = \App\Models\Club::select('id', 'league_id')->find($activeId);
            $parentLeagueId = $currentClub?->league_id;
            $examens = $query->where(function ($q) use ($activeId, $parentLeagueId) {
                // Ses propres examens de club
                $q->where(function ($sub) use ($activeId) {
                    $sub->where('organisateur_id', $activeId)
                        ->where('organisateur_type', 'Club');
                });

                // PLUS les examens de sa ligue (si elle existe)
                if ($parentLeagueId) {
                    $q->orWhere(function ($sub) use ($parentLeagueId) {
                        $sub->where('organisateur_id', $parentLeagueId)
                            ->where('organisateur_type', 'Ligue')
                            ->whereIn('status', [Examen::STATUS_SCHEDULED, Examen::STATUS_ONGOING]);
                    });
                }
            })->paginate(8);
        }

        return response()->json([
            'success' => true,
            'examens' => $examens
        ], 200);
    }
    //obtenir les candidats d'un examen
    public function getExamenCandidats(Examen $examen)
    {


        $candidats = ExamenCandidat::with('student:id,fullname,birthdate')
            ->where('examen_id', $examen->id)
            ->where('status', ExamenCandidat::STATUS_REGISTERED)
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
            $activeId = $request->attributes->get('organisateur_id');
            $activeType = $request->attributes->get('organisateur_type');
            $validated = $request->validated();

            $curentGrade = Grade::findOrFail($request->current_grade_id);
            $nextGrade = Grade::findOrFail($request->next_grade_id);
            $isNoire = str_contains(strtolower($nextGrade->name), 'noire');
            $saisonActive =  Saison::where('active', true)->first();
            if (!$saisonActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous devez définir une saison pour créer un examen',
                ], 422);
            }

            // 1. Validation de la hiérarchie
            if ($isNoire && $activeType !== 'Ligue') {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'examen pour la ceinture noire est réservé aux Ligues.'
                ], 422);
            }

            if (!$curentGrade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade spécifié n\'existe pas',
                ]);
            }
            //current_dade_id " next_grade_id

            if (!$nextGrade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade spécifié n\'existe pas',
                ]);
            }

            if ($curentGrade->id === $nextGrade->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le grade actuel ne peut pas être identique au grade visé.',
                ], 400);
            }

            $examenData = DB::transaction(function () use ($validated, $user, $activeId, $activeType, $saisonActive, $request) {


                // Créer l'examen
                $examen = Examen::create([
                    ...$validated,
                    'created_by' => $user->id,
                    'saison_id' => $saisonActive->id,
                ]);

                // 3. Inscription AUTOMATIQUE uniquement si c'est un CLUB
                if ($activeType === 'Club') {
                    $students = Student::where('club_id', $activeId)
                        ->whereHas('currentGrade', function ($q) use ($request) {
                            $q->where('current_grade_id', $request->current_grade_id)
                                ->whereDate('awarded_at', '<=', $request->start_date);
                        })->get();

                    if ($students->isNotEmpty()) {
                        $candidats = $students->map(fn($student) => [
                            'id' => (string) Str::uuid(),
                            'examen_id' => $examen->id,
                            'student_id' => $student->id,
                            'status' => ExamenCandidat::STATUS_REGISTERED,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->toArray();

                        DB::table('examen_candidats')->insert($candidats);
                    }
                }

                return $examen;
            });
            $examenData->load('candidates');

            $candidats = $examenData->candidates;
            $students = $examenData->candidates()
                ->with('student')
                ->get()
                ->pluck('student');
            Log::info('examen created', ['students' => $students]);

            Notification::send($students, new ExamenCreated($examenData));
            $message = ($activeType === 'Ligue')
                ? 'Examen de Ligue créé. Les clubs peuvent maintenant inscrire leurs candidats.'
                : 'Examen de Club créé avec inscriptions automatiques.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $examenData
            ], 201);
        } catch (QueryException $e) {
            $errcode = $e->getCode();
            $errmessage = $e->getMessage();

            Log::error('erreur', ['code' => $errcode, 'message' => $errmessage]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de l\'examen',
            ], 422);
        }
    }


    public function show(Examen $examen)
    {

        $examen->load([
            'organisateur.users',
            'nextGrade:id,name',
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
        // $studentsToNotify = User::whereHas('students.candidates', function ($query) use ($examen) {
        //     $query->where('examen_id', $examen->id)
        //         ->where('status', ExamenCandidat::STATUS_REGISTERED);
        // })
        //     ->get()
        //     ->unique('id');
        // if ($studentsToNotify->isNotEmpty()) {
        //     Notification::send($studentsToNotify, new ExamenCanceled($examen));
        // }

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
        // $studentsToNotify = User::whereHas('students.candidates', function ($query) use ($examen) {
        //     $query->where('examen_id', $examen->id)
        //         ->where('status', ExamenCandidat::STATUS_REGISTERED);
        // })
        //     ->get()
        //     ->unique('id');
        // if ($studentsToNotify->isNotEmpty()) {
        //     Notification::send($studentsToNotify, new ExamenChanged($examen));
        // }

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
        if ($examen->status !== Examen::STATUS_SCHEDULED) {
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
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        return response()->json($stats->getExamenStats($activeId, $activeType));
    }
}
