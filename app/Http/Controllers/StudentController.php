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

        $clubId = $request->query('club_id', $user->current_club_id);
        Log::info('club_id', ['clubId' => $clubId]);
        $role = $user?->globalRole?->name;
        Log::info('user_role', ['role' => $role]);
        //retourner les eleves pour super admin
        $superAdmin = ($role == 'super_admin');


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
        $clubId = $request->validated_club_id;
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
        $user = auth()->user();
        $clubId = $request->validated_club_id;
        $validated = $request->validated();
        try {
            return  DB::transaction(function () use ($validated, $request, $user, $clubId) {

                $studentUserId = null;
                $parentId      = null;


                if ($request->user_id) {
                    $parent = ParentModel::where('user_id', $request->user_id)->first();

                    if ($parent) {
                        $parentId = $parent->id;
                    } else {
                        Log::error("ParentModel introuvable", [
                            'user_id' => $request->user_id
                        ]);
                    }
                }

                if ($request->is_own_responsible || $request->create_account) {
                    $studentUser = User::create([
                        'id'       => (string) Str::uuid(),
                        'fullname' => $validated['fullname'],
                        'email'    => $validated['email'],
                        'phone'    => $validated['phone'],
                        'password' => Hash::make(Str::random(32)),
                        'current_club_id' => $clubId,
                    ]);
                    $studentUserId = $studentUser->id;


                    $studentRole = cache()->rememberForever('role_student', fn() => Role::where('name', 'karateka')->first());
                    $studentUser->clubs()->attach($clubId, ['role_id' => $studentRole->id]);



                    if ($request->is_own_responsible) {
                        $parentProfile = ParentModel::firstOrCreate(
                            ['user_id' => $studentUserId]
                        );
                        $parentId = $parentProfile->id;
                    }
                    //envoi de token 
                    $token = Password::createToken($studentUser);
                    // $studentUser->notify(new WelcomeNewMember($token));
                }

                // LOGIQUE 2 : Création de la fiche élève  
                $file = $request->file('photo');
                if ($file) {
                    $ext = $file->getClientOriginalExtension();
                    $fileName = uniqid() . '.' . $ext;
                    $validated['photo'] = $file->storeAs('students', $fileName, 'public');
                }
                $birthdate = Carbon::parse($validated['birthdate']);
                $isAdult = $birthdate->age >= 18;

                $student = Student::create([
                    ...$validated,
                    'club_id'    => $clubId,
                    'user_id'    => $studentUserId,
                    'is_adult'   => $isAdult,


                ]);

                if ($parentId) {
                    $parent = ParentModel::find($parentId);

                    if ($parent) {
                        $student->parents()->syncWithoutDetaching([$parent->id]);
                    } else {
                        Log::error("Parent non trouvé lors de l'attachement", [
                            'parent_id' => $parentId
                        ]);
                    }
                }


                return response()->json([
                    'success' => true,
                    'message' => 'Élève enregistré avec succès.',
                    'data'    => $student->load('parents', 'user')
                ], 201);
            });
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('erreur', ['erreur' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement de l\'élève',
            ], 400);
        }
    }
    public function updateStudent(UpdatedStudentReq $request, Student $student)
    {
        $this->authorize('update', $student);
        $user = auth()->user();

        $validated = $request->validated();
        $clubId = $request->validated_club_id;
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
        $clubId = $request->validated_club_id;
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
        $clubId = $request->validated_club_id;
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
