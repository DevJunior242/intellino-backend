<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Course;
use App\Models\Saison;
use App\Models\Student;
use App\Models\SessionModel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CourseRequest;

class CourseController extends Controller
{
    /**
     * Utilisateurs autorisés à diriger les cours d'un club :
     * les admins/instructeurs du club, ainsi que les élèves ceinture noire
     * (qui possèdent un compte utilisateur lié), même sans le rôle instructeur.
     */
    private function eligibleInstructors(string $clubId)
    {
        $allowedRoleIds = Role::whereIn('name', ['admin', 'instructeur'])->pluck('id');

        $staff = User::select('id', 'fullname')
            ->whereHas('clubs', function ($q) use ($clubId, $allowedRoleIds) {
                $q->where('clubs.id', $clubId)
                    ->whereIn('club_users.role_id', $allowedRoleIds);
            })
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'fullname' => $user->fullname,
                'type' => 'instructeur',
            ]);

        $blackBelts = Student::query()
            ->whereNotNull('user_id')
            ->whereHas('clubs', fn ($q) => $q->where('club_id', $clubId))
            ->with('currentGrade.grade')
            ->get()
            ->filter(fn ($student) => $student->currentGrade?->grade?->isNoire())
            ->map(fn ($student) => [
                'id' => $student->user_id,
                'fullname' => $student->fullname,
                'type' => 'eleve_ceinture_noire',
            ]);

        return $staff->concat($blackBelts)->unique('id')->values();
    }

    /**
     * Liste des personnes pouvant être assignées comme instructeur d'un cours
     * (admin/instructeurs du club + élèves ceinture noire).
     */
    public function eligibleInstructorsList(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if ($activeType !== 'Club') {
            return response()->json(['success' => true, 'data' => []]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->eligibleInstructors($activeId),
        ]);
    }

    public function index(Request $request)
    {

        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $saisonActive =  Saison::where('active', true)->first();

        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer un cours',
            ], 422);
        }
        $saisonActive =  Saison::where('active', true)->first();

        $sessions = SessionModel::with(['course.organisateur', 'course.grade'])
            ->whereHas('course', function ($query) use ($activeId, $saisonActive, $activeType) {
                $query->where('organisateur_id', $activeId)
                    ->where('organisateur_type', $activeType)
                    ->where('saison_id', $saisonActive->id);
            })

            ->latest()
            ->paginate(6);
        return response()->json([
            'success' => true,
            'message' => 'listes des sessions ',
            'sessions' => $sessions,
        ]);
    }
    public function storeFullCourse(CourseRequest $request)
    {
        $saisonActive =  Saison::where('active', true)->first();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$saisonActive) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez définir une saison pour créer un cours',
            ], 422);
        }

        $validated = $request->validated();

        if ($activeType === 'Club') {
            $eligibleIds = $this->eligibleInstructors($activeId)->pluck('id');
            if (!$eligibleIds->contains($validated['course']['instructor_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => "L'instructeur choisi doit être un admin/instructeur du club, ou un élève ceinture noire.",
                ], 422);
            }
        }

        return DB::transaction(function () use ($validated, $request, $activeId, $activeType, $saisonActive) {
            $course = Course::create([
                ...$validated['course'],
                'saison_id' => $saisonActive->id,
            ]);
            $session = collect($validated['sessions'])->map(function ($session) {
                return [
                    'title' => $session['title'],
                    'session_date' => $session['session_date'],
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                    'description' => $session['description'] ?? null,
                ];
            });
            $course->sessions()->createMany($session->toArray());

            return response()->json(['success' => true, 'message' => 'Cours créé avec ses sessions', 'data' => $course->load('sessions')], 201);
        });
    }

    public function show(SessionModel $session)
    {

        $session->load([
            'course.organisateur',
            'course.grade',
            'course.instructor',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'liste des sessions ',
            'session' => $session,
        ]);
    }
}
