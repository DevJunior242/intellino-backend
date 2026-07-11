<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Grade;
use App\Models\Examen;
use App\Models\Saison;
use App\Models\Student;
use Illuminate\Support\Str;
use App\Models\ExamenResult;
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
use App\Http\Controllers\Concerns\ResolvesFederationSaison;

class ExamenController extends Controller
{
    use AuthorizesRequests;
    use ResolvesFederationSaison;

    public function index(Request $request)
    {
        $user = auth()->user();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $query = Examen::with(['organisateur', 'nextGrade:id,name'])
            ->select('id', 'organisateur_id', 'organisateur_type', 'next_grade_id', 'start_date', 'status')
            ->latest();

        // 1. Si Super Admin : voit TOUT
        if ($user?->isSuperAdmin()) {
            $examens = $query->paginate(8);
        }
        // 2. Si Fédération : voit ses propres examens
        else if ($activeType == 'Federation') {
            $examens = $query->where('organisateur_id', $activeId)
                ->where('organisateur_type', $activeType)
                ->paginate(8);
        }
        // 3. Si Ligue : voit ses propres examens
        else if ($activeType == 'Ligue') {
            $examens = $query->where('organisateur_id', $activeId)
                ->where('organisateur_type', 'Ligue')
                ->paginate(8);
        }
        // 4. Si Club : voit SES examens + les examens OUVERTS de la Ligue
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
            $saisonActive = $this->saisonActivePour($activeId, $activeType);
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


                // Créer l'examen — organisateur_id/type viennent du contexte
                // authentifié (middleware), jamais du payload client.
                $examen = Examen::create([
                    ...$validated,
                    'organisateur_id' => $activeId,
                    'organisateur_type' => $activeType,
                    'created_by' => $user->id,
                    'saison_id' => $saisonActive->id,
                ]);

                // 3. Inscription AUTOMATIQUE uniquement si c'est un CLUB
                if ($activeType === 'Club') {
                    $students = Student::whereHas('clubs', function ($q) use ($activeId) {
                        $q->where('club_id', $activeId);
                    })
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

            $students = $examenData->candidates()
                ->with('student')
                ->get()
                ->pluck('student');

            Notification::send($students, new ExamenCreated($examenData));
            $message = match ($activeType) {
                'Federation' => 'Examen de Fédération créé. Les ligues et clubs peuvent maintenant inscrire leurs candidats.',
                'Ligue' => 'Examen de Ligue créé. Les clubs peuvent maintenant inscrire leurs candidats.',
                default => 'Examen de Club créé avec inscriptions automatiques.',
            };

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

    public function update(Request $request, Examen $examen)
    {
        $this->authorize('update', $examen);

        $validated = $request->validate([
            'current_grade_id' => ['required', 'uuid', 'exists:grades,id'],
            'next_grade_id'    => ['required', 'uuid', 'exists:grades,id'],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date', 'after_or_equal:start_date'],
            'start_time'       => ['required', 'date_format:H:i'],
            'end_time'         => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        if ($validated['current_grade_id'] === $validated['next_grade_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Le grade actuel ne peut pas être identique au grade visé.',
            ], 422);
        }

        $nextGrade = Grade::findOrFail($validated['next_grade_id']);
        $isNoire = str_contains(strtolower($nextGrade->name), 'noire');

        if ($isNoire && $examen->organisateur_type !== 'Ligue') {
            return response()->json([
                'success' => false,
                'message' => 'L\'examen pour la ceinture noire est réservé aux Ligues.'
            ], 422);
        }

        $examen->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Examen mis à jour avec succès',
            'data'    => $examen->fresh(['currentGrade', 'nextGrade']),
        ]);
    }

    public function destroy(Examen $examen)
    {
        $this->authorize('delete', $examen);

        if ($examen->candidates()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cet examen a déjà des candidats inscrits — annulez-le plutôt que de le supprimer.',
            ], 422);
        }

        $examen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Examen supprimé avec succès',
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

    /**
     * Candidatures et résultats d'examens de l'élève (karateka) connecté.
     */
    public function mesExamens(Request $request)
    {
        $role = $request->attributes->get('role');

        if ($role !== 'karateka') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux élèves.'
            ], 403);
        }

        $student = Student::where('user_id', auth()->id())->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève introuvable.'
            ], 404);
        }

        $candidatures = ExamenCandidat::where('student_id', $student->id)
            ->with([
                'examen:id,start_date,status,current_grade_id,next_grade_id',
                'examen.currentGrade:id,name',
                'examen.nextGrade:id,name',
            ])
            ->latest('created_at')
            ->get();

        $resultats = ExamenResult::where('student_id', $student->id)
            ->with('examen:id,start_date')
            ->latest('created_at')
            ->get();

        return response()->json([
            'success'      => true,
            'candidatures' => $candidatures,
            'resultats'    => $resultats,
        ]);
    }

    public function stats(ExamenStatService $stats, Request $request)
    {
        $this->authorize('viewStats', Examen::class);
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        return response()->json($stats->getExamenStats($activeId, $activeType));
    }

    public function exmenstates(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        // La saison est toujours définie par la Fédération : on la résout
        // quel que soit le niveau connecté (Fédération, Ligue ou Club).
        $currentSaison = $this->saisonActivePour($activeId, $activeType);

        if (!$currentSaison) {
            return response()->json(['message' => 'Aucune saison active trouvée.'], 422);
        }

        $saisonId = $currentSaison->id;

        // Grades décernés par cet organisateur pendant cette saison
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
            ->where('dateFin', '<', $currentSaison->dateDebut)
            ->orderBy('dateFin', 'desc')
            ->first();

        // Compter les admis de la saison précédente pour cet organisateur
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
            'grades_decernes' => $gradesDecernees ?? 0,
            'sessions_count' => $totalSessions ?? 0,
            'sessions_a_venir' => $examAVenir ?? 0,
            'taux_reussite' => round($taux, 1) ?? 0,
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
