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
        $user = auth()->user();

        $clubId = $request->attributes->get('club_id');
        $role = $request->validated_role_name;
        //retourner les eleves pour super admin
        $superAdmin = ($role === 'super_admin');


        if ($superAdmin) {
            $students = Student::with($superAdmin ? ['club:id,name'] : [])
                ->get()
                ->map(fn($student) => [
                    'id' => $student->id,
                    'fullname' => $student->fullname,
                    'birthdate' => $student->birthdate,
                    'sex' => $student->sex,
                    'status' => $student->status,
                    'club' => $student->club,
                    'photo' => $student->photo ? url('storage/' . $student->photo) : null,
                ]);
            return response()->json([
                'success' => true,
                'message' => 'Super Admin',
                'students' => $students,
            ]);
        }



        $students = Student::where('club_id', $clubId)
            ->get()
            ->map(function ($student) {
                $student->photo = $student->photo ? url('storage/' . $student->photo) : null;
                return $student;
            });

        return response()->json([
            'success' => true,
            'message' => 'Students list',
            'students' => $students,
        ]);
    }

    public function getParent(Request $request)
    {
        $user = auth()->user();
        $clubId = $request->attributes->get('club_id');
        Log::info('club_id', ['clubId' => $clubId]);
        $authorizedRoles = ['admin_club', 'secretaire', 'instructeur'];

        // 3. On vérifie si l'utilisateur a UN de ces rôles dans CE club précis
        $hasPermission = DB::table('club_users')
            ->join('roles', 'club_users.role_id', '=', 'roles.id')
            ->where('club_users.user_id', $user->id)
            ->where('club_users.club_id', $clubId)
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
            ->where('club_users.club_id', $clubId)
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
        $clubId = $request->attributes->get('club_id');
        $validated = $request->validated();
        try {
            return DB::transaction(function () use ($validated, $clubId, $request) {
                $parentId = null;

                // --- GESTION DU PARENT ---
                if (!$validated['is_own_responsible']) {
                    $parentUser = User::firstOrCreate(
                        ['email' => $validated['parent_email']],
                        [
                            'id'       => (string) Str::uuid(),
                            'fullname' => $validated['parent_fullname'],
                            'phone'    => $validated['parent_phone'] ?? null,
                            'password' => Hash::make(Str::random(16)),
                            'current_club_id' => $clubId,
                        ]
                    );

                    // Attribution rôle parent
                    $parentRole = cache()->rememberForever('role_parent', fn() => Role::where('name', 'parent')->first());
                    $parentUser->clubs()->syncWithoutDetaching([$clubId => ['role_id' => $parentRole->id]]);

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
                            'current_club_id' => $clubId,
                        ]);

                        $studentUserId = $studentUser->id;

                        // Déterminer le rôle
                        // $roleName = $validated['is_own_responsible'] ? 'parent' : 'karateka';
                        $role = cache()->rememberForever("role_karateka", fn() => Role::where('name', 'karateka')->first());

                        if ($role) {
                            $studentUser->clubs()->attach($clubId, ['role_id' => $role->id]);
                        }

                        // Si l'élève est son propre responsable, il devient son propre "ParentModel"
                        if ($validated['is_own_responsible']) {
                            $pProfile = ParentModel::firstOrCreate(['user_id' => $studentUserId]);
                            $currentStudentParentId = $pProfile->id;
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
                        'club_id'   => $clubId,
                        'user_id'   => $studentUserId,
                        'is_adult'  => Carbon::parse($studentData['birthdate'])->age >= 18,
                        'photo'     => $photoPath,
                    ]);

                    // 4. Liaison avec le responsable (Le parent créé au début OU l'élève lui-même)
                    if ($currentStudentParentId) {
                        $student->parents()->syncWithoutDetaching([$currentStudentParentId]);
                    }

                    $createdStudents[] = $student->load('user');
                }

                return response()->json([
                    'success' => true,
                    'message' => count($createdStudents) . ' élève(s) enregistré(s) avec succès.',
                    'data'    => $createdStudents
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement de l\'élève'], 400);
        }
        // $user = auth()->user();
        // $clubId = $request->validated_club_id;
        // $validated = $request->validated();
        // try {
        //     return  DB::transaction(function () use ($validated, $request, $user, $clubId) {

        //         $studentUserId = null;
        //         $parentId      = null;


        //         if ($request->user_id) {
        //             $parent = ParentModel::where('user_id', $request->user_id)->first();

        //             if ($parent) {
        //                 $parentId = $parent->id;
        //             } else {
        //                 Log::error("ParentModel introuvable", [
        //                     'user_id' => $request->user_id
        //                 ]);
        //             }
        //         }

        //         if ($request->is_own_responsible || $request->create_account) {
        //             $studentUser = User::create([
        //                 'id'       => (string) Str::uuid(),
        //                 'fullname' => $validated['fullname'],
        //                 'email'    => $validated['email'],
        //                 'phone'    => $validated['phone'],
        //                 'password' => Hash::make(Str::random(32)),
        //                 'current_club_id' => $clubId,
        //             ]);
        //             $studentUserId = $studentUser->id;


        //             $studentRole = cache()->rememberForever('role_student', fn() => Role::where('name', 'karateka')->first());
        //             $studentUser->clubs()->attach($clubId, ['role_id' => $studentRole->id]);



        //             if ($request->is_own_responsible) {
        //                 $parentProfile = ParentModel::firstOrCreate(
        //                     ['user_id' => $studentUserId]
        //                 );
        //                 $parentId = $parentProfile->id;
        //             }
        //             //envoi de token 
        //             $token = Password::createToken($studentUser);
        //             // $studentUser->notify(new WelcomeNewMember($token));
        //         }

        //         // LOGIQUE 2 : Création de la fiche élève  
        //         $file = $request->file('photo');
        //         if ($file) {
        //             $ext = $file->getClientOriginalExtension();
        //             $fileName = uniqid() . '.' . $ext;
        //             $validated['photo'] = $file->storeAs('students', $fileName, 'public');
        //         }
        //         $birthdate = Carbon::parse($validated['birthdate']);
        //         $isAdult = $birthdate->age >= 18;

        //         $student = Student::create([
        //             ...$validated,
        //             'club_id'    => $clubId,
        //             'user_id'    => $studentUserId,
        //             'is_adult'   => $isAdult,


        //         ]);

        //         if ($parentId) {
        //             $parent = ParentModel::find($parentId);

        //             if ($parent) {
        //                 $student->parents()->syncWithoutDetaching([$parent->id]);
        //             } else {
        //                 Log::error("Parent non trouvé lors de l'attachement", [
        //                     'parent_id' => $parentId
        //                 ]);
        //             }
        //         }


        //         return response()->json([
        //             'success' => true,
        //             'message' => 'Élève enregistré avec succès.',
        //             'data'    => $student->load('parents', 'user')
        //         ], 201);
        //     });
        // } catch (\Throwable $th) {
        //     //throw $th;
        //     Log::error('erreur', ['erreur' => $th->getMessage()]);
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Une erreur est survenue lors de l\'enregistrement de l\'élève',
        //     ], 400);
        // }
    }
    public function updateStudent(UpdatedStudentReq $request, Student $student)
    {
        $this->authorize('update', $student);

        $validated = $request->validated();
        $clubId = $request->attributes->get('club_id');
        $studentData = [
            ...$validated,
            'club_id' => $clubId,
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
        $clubId = $request->attributes->get('club_id');
        Log::info('club_id', ['clubId' => $clubId]);

        $stats = $gradeService->getGlobalStats($clubId);

        return response()->json([
            'success' => true,
            'message' => 'Statistiques globales des élèves',
            'data' => $stats,
        ]);
    }

    public function latestStudent(Request $request)
    {
        $this->authorize('viewStats', Student::class);
        $clubId = $request->attributes->get('club_id');
        Log::info('club_id', ['clubId' => $clubId]);

        $latestStudents = Student::where('club_id', $clubId)
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
