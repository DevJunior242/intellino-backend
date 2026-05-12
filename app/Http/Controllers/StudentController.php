<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Role;
use App\Models\User;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Services\StudentGradeService;
use App\Notifications\WelcomeNewMember;
use App\Http\Requests\UpdatedStudentReq;
use Illuminate\Support\Facades\Password;
use App\Http\Requests\StoreStudentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StudentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $role = $request->attributes->get('role');
        $isSuperAdmin = ($role === 'super_admin');

        if ($isSuperAdmin) {
            $students = Student::with('club:id,name')->get();
        } else {
            $students = Student::where('club_id', $activeId)->get();
        }

        $formattedStudents = $students->map(function ($student) use ($isSuperAdmin) {
            return [
                'id' => $student->id,
                'fullname' => $student->fullname,
                'birthdate' => $student->birthdate,
                'sex' => $student->sex,
                'status' => $student->status,
                'club' => $isSuperAdmin ? $student->club : null,
                'photo' => $student->photo ? url('storage/' . $student->photo) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $isSuperAdmin ? 'Super Admin' : 'Students list',
            'students' => $formattedStudents,
        ]);
    }
    public function studentsWithoutGrade(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $role = $request->attributes->get('role');
        $isSuperAdmin = ($role === 'super_admin');

        $query = Student::query()
            ->whereDoesntHave('grades');

        if (!$isSuperAdmin) {
            $query->where('club_id', $activeId);
        }

        $students = $query
            ->with('club:id,name')
            ->get();

        $formattedStudents = $students->map(function ($student) use ($isSuperAdmin) {
            return [
                'id' => $student->id,
                'fullname' => $student->fullname,
                'birthdate' => $student->birthdate,
                'sex' => $student->sex,
                'status' => $student->status,
                'club' => $isSuperAdmin ? $student->club : null,
                'photo' => $student->photo
                    ? url('storage/' . $student->photo)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Students without grade',
            'students' => $formattedStudents,
        ]);
    }

    public function getParent(Request $request)
    {
        $user = auth()->user();
        $activeId = $request->attributes->get('organisateur_id');
        Log::info('club_id', ['activeId' => $activeId]);
        $authorizedRoles = ['admin_club', 'secretaire', 'instructeur'];

        // 3. On vérifie si l'utilisateur a UN de ces rôles dans CE club précis
        $hasPermission = DB::table('club_users')
            ->join('roles', 'club_users.role_id', '=', 'roles.id')
            ->where('club_users.user_id', $user->id)
            ->where('club_users.club_id', $activeId)
            ->whereIn('roles.name', $authorizedRoles)
            ->exists();
        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour accéder à cette page.listes parents',
            ], 403);
        }
        $parents = DB::table('club_users')
            ->join('users', 'club_users.user_id', '=', 'users.id')
            ->join('roles', 'club_users.role_id', '=', 'roles.id')
            ->where('club_users.club_id', $activeId)
            ->where('roles.name', 'parent')
            ->select('users.id', 'users.fullname', 'users.phone')
            ->get()
            ->map(function ($parent) {
                return [
                    'id' => $parent->id,
                    'fullname' => $parent?->fullname,
                    'phone' => $parent?->phone,
                    'user_id' => $parent?->id
                ];
            });
        return response()->json([
            'success' => true,
            'message' => 'listes des parents',
            'parentUsers' => $parents,
        ]);
    }

    public function store(StoreStudentRequest $request)
    {

        $this->authorize('create', Student::class);
        $activeId = $request->attributes->get('organisateur_id');
        $validated = $request->validated();
        try {
            return DB::transaction(function () use ($validated, $activeId, $request) {
                $parentId = null;
                $createdStudents = [];
                // --- GESTION DU PARENT ---
                if (!$validated['is_own_responsible']) {
                    $parentUser = User::firstOrCreate(
                        ['email' => $validated['parent_email']],
                        [
                            'id'       => (string) Str::uuid(),
                            'fullname' => $validated['parent_fullname'],
                            'phone'    => $validated['parent_phone'] ?? null,
                            'password' => Hash::make(Str::random(16)),
                            'current_club_id' => $activeId,
                        ]
                    );

                    // Attribution rôle parent
                    $parentRole = cache()->rememberForever('role_parent', fn() => Role::where('name', 'parent')->first());
                    $parentUser->clubs()->syncWithoutDetaching([$activeId => ['role_id' => $parentRole->id]]);

                    $parentProfile = ParentModel::firstOrCreate(['user_id' => $parentUser->id]);
                    $parentId = $parentProfile->id;
                }

                // --- GESTION DES ÉLÈVES ---
                foreach ($validated['students'] as $index => $studentData) {
                    $studentUserId = null;
                    $currentStudentParentId = $parentId;

                    // 1. Création du compte User (si responsable ou si accès demandé)
                    if ($validated['is_own_responsible'] || ($studentData['create_account'] ?? false)) {
                        $studentUser = User::create([
                            'id'       => (string) Str::uuid(),
                            'fullname' => $studentData['fullname'],
                            'email'    => $studentData['email'],
                            'phone'    => $studentData['phone'] ?? null,
                            'password' => Hash::make(Str::random(32)),
                            'current_club_id' => $activeId,
                        ]);

                        $studentUserId = $studentUser->id;

                        // Déterminer le rôle
                        // $roleName = $validated['is_own_responsible'] ? 'parent' : 'karateka';
                        $role = cache()->rememberForever("role_karateka", fn() => Role::where('name', 'karateka')->first());

                        if ($role) {
                            $studentUser->clubs()->attach($activeId, ['role_id' => $role->id]);
                        }

                        // Si l'élève est son propre responsable, il devient son propre "ParentModel"
                        if ($validated['is_own_responsible']) {
                            $pProfile = ParentModel::firstOrCreate(['user_id' => $studentUserId]);
                            $currentStudentParentId = null;
                        }
                        // $token = Password::createToken($studentUser);
                        // $studentUser->notify(new WelcomeNewMember($token));
                    }

                    // 2. Gestion de la photo
                    $photoPath = null;
                    $photoFile = $request->file("students.$index.photo");

                    if ($photoFile) {
                        $photoPath = $photoFile->store('students', 'public');
                    }

                    // 3. Création de la fiche Student
                    $student = Student::create([
                        'fullname'  => $studentData['fullname'],
                        'birthdate' => $studentData['birthdate'],
                        'sex'       => $studentData['sex'],
                        'club_id'   => $activeId,
                        'user_id'   => $studentUserId,
                        'is_adult'  => Carbon::parse($studentData['birthdate'])->age >= 18,
                        'photo'     => $photoPath,
                    ]);

                    // 4. Liaison avec le responsable (Le parent créé au début OU l'élève lui-même)
                    if ($currentStudentParentId) {
                        $student->parents()->syncWithoutDetaching([$currentStudentParentId]);
                    }
                }
                $createdStudents[] = $student->load('user');

                return response()->json([
                    'success' => true,
                    'message' => count($createdStudents) . ' élève(s) enregistré(s) avec succès.',
                    'data'    => $createdStudents
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement de l\'élève'], 400);
        }
    }
    public function updateStudent(UpdatedStudentReq $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validated();
        $activeId = $request->attributes->get('organisateur_id');
        $studentData = [
            ...$validated,
            'club_id' => $activeId,
        ];
        $file = $request->file('photo');
        if ($file) {
            $ext = $file->getClientOriginalExtension();
            $fileName = uniqid() . '.' . $ext;
            $studentData['photo'] = $file->storeAs('students', $fileName, 'public');
        }
        $student->update($studentData);
        return response()->json([
            'success' => true,
            'message' => 'Élève mis à jour avec succès',
            'student' => [
                ...$student->toArray(),
                'id' => $student->id,
                'fullname' => $student->fullname,
                'birthdate' => $student->birthdate,
                'sex' => $student->sex,
                'status' => $student->status,
                'club' => $student->club,
                'photo' => $student->photo ? url('storage/' . $student->photo) : null,
            ],
        ]);
    }
    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);
        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Élève archivé avec succès'
        ]);
    }


    public function storegrade(Request $request)
    {
        $validatedData = $request->validate([
            'student_id' => 'required|exists:students,id',
            'current_grade_id' => 'required|exists:grades,id',
            'awarded_at' => 'required|date',
            'instructor_id' => 'required|string|max:255',
        ]);

        $studentGrade = \App\Models\StudentGrade::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Grade assigné avec succès',
            'studentGrade' => $studentGrade,
        ], 201);
    }

    public function StudentStatsDashboard(StudentGradeService $gradeService, Request $request)
    {
        $this->authorize('viewStats', Student::class);
        $activeId = $request->attributes->get('organisateur_id');

        $stats = $gradeService->getGlobalStats($activeId);

        return response()->json([
            'success' => true,
            'message' => 'Statistiques globales des élèves',
            'data' => $stats,
        ]);
    }

    public function latestStudent(Request $request)
    {
        $this->authorize('viewStats', Student::class);
        $activeId = $request->attributes->get('organisateur_id');

        $latestStudents = Student::where('club_id', $activeId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'fullname' => $student->fullname,
                    'birthdate' => $student->birthdate,
                    'sex' => $student->sex,
                    'status' => $student->status,
                    'photo' => $student->photo ? url('storage/' . $student->photo) : null,
                ];
            });

        return response()->json($latestStudents);
    }
}
