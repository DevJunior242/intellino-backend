<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Club;
use App\Models\League;
use App\Models\Role;
use App\Models\User;
use App\Models\Licence;
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
use App\Http\Controllers\Concerns\ResolvesActiveSaison;

class StudentController extends Controller
{
    use AuthorizesRequests;
    use ResolvesActiveSaison;

    /**
     * Élèves visibles par l'organisateur connecté : un Club ne voit que le
     * sien, une Ligue/Fédération voit tous les clubs de son ressort
     * (hiérarchie club -> ligue -> fédération) PLUS les athlètes
     * indépendants (sans club) qu'elle a elle-même inscrits — même logique
     * que InscriptionController::studentsDisponibles() pour les épreuves.
     * $clubId (optionnel) restreint encore à un club précis dans ce ressort
     * (et exclut alors les indépendants, non rattachés à ce club précis).
     */
    private function studentsVisiblesPar(string $activeId, string $activeType, ?string $clubId = null, ?string $q = null)
    {
        $appliquerRecherche = fn ($query) => $q
            ? $query->where('students.fullname', 'LIKE', '%' . $q . '%')
            : $query;

        $viaClub = Student::query()
            ->join('club_students', 'club_students.student_id', '=', 'students.id')
            ->whereNull('club_students.deleted_at')
            ->select('students.*');

        if ($activeType === 'Club') {
            $viaClub->where('club_students.club_id', $activeId);
        } elseif ($activeType === 'Ligue') {
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->where('clubs.league_id', $activeId);
        } elseif ($activeType === 'Federation') {
            $liguesIds = League::where('federation_id', $activeId)->pluck('id');
            $viaClub->join('clubs', 'clubs.id', '=', 'club_students.club_id')
                ->whereIn('clubs.league_id', $liguesIds);
        }

        if ($clubId && $activeType !== 'Club') {
            $viaClub->where('club_students.club_id', $clubId);
        }

        $students = $appliquerRecherche($viaClub)->distinct()->get();

        if ($activeType !== 'Club' && !$clubId) {
            $organisateurs = [[$activeType, $activeId]];
            if ($activeType === 'Federation') {
                foreach (League::where('federation_id', $activeId)->pluck('id') as $ligueId) {
                    $organisateurs[] = ['Ligue', $ligueId];
                }
            }

            $independants = Student::query()->where(function ($query) use ($organisateurs) {
                foreach ($organisateurs as [$type, $id]) {
                    $query->orWhere(function ($q2) use ($type, $id) {
                        $q2->where('organisateur_type', $type)->where('organisateur_id', $id);
                    });
                }
            });

            $independants = $appliquerRecherche($independants)->get();
            $students = $students->concat($independants);
        }

        return $students->unique('id')->sortBy('fullname')->values();
    }

    public function index(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $role       = $request->attributes->get('role');
        $isSuperAdmin = ($role === 'super_admin');

        if ($isSuperAdmin) {
            $students = Student::with('clubs:id,name')->get();
        } else {
            $students = $this->studentsVisiblesPar($activeId, $activeType, $request->input('club_id'));
        }

        $formattedStudents = $students->map(function ($student) use ($isSuperAdmin) {
            return [
                'id' => $student->id,
                'fullname' => $student->fullname,
                'birthdate' => $student->birthdate,
                'sex' => $student->sex,
                'status' => $student->status,
                'club' => $isSuperAdmin ? $student->clubs->first() : null,
                'photo' => $student->photo ? url('storage/' . $student->photo) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $isSuperAdmin ? 'Super Admin' : 'Students list',
            'students' => $formattedStudents,
        ]);
    }

    /**
     * Recherche paginée d'élèves par nom, pour les sélecteurs de type
     * Autocomplete (ex: ajout d'un candidat à un examen) — évite de
     * télécharger tout le roster d'un coup à chaque ouverture du sélecteur.
     */
    public function search(Request $request)
    {
        $activeId   = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        if (!$activeId || !$activeType) {
            return response()->json(['success' => false, 'message' => 'Organisateur non identifié.'], 403);
        }

        $perPage = min((int) $request->input('per_page', 15), 50);
        $page = max((int) $request->input('page', 1), 1);

        $students = $this->studentsVisiblesPar(
            $activeId,
            $activeType,
            $request->input('club_id'),
            $request->input('q') ?: null,
        );

        $total = $students->count();
        $items = $students->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'students' => $items,
            'has_more' => $page * $perPage < $total,
        ]);
    }

    /**
     * Club(s) auquel/auxquels appartient l'élève (karateka) connecté.
     */
    public function monClub(Request $request)
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

        $clubs = $student->clubs()->get(['clubs.id', 'clubs.name', 'clubs.logo']);

        return response()->json([
            'success' => true,
            'clubs'   => $clubs,
        ]);
    }

    /**
     * Parent(s) rattaché(s) à l'élève (karateka) connecté.
     */
    public function mesParents(Request $request)
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

        $parents = $student->parents()->with('user:id,fullname,email,phone')->get();

        return response()->json([
            'success' => true,
            'parents' => $parents,
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
            $query->whereHas('clubs', function ($q) use ($activeId) {
                $q->where('club_id', $activeId);
            });
        }

        $students = $query
            ->with('clubs:id,name')
            ->get();

        $formattedStudents = $students->map(function ($student) use ($isSuperAdmin) {
            return [
                'id' => $student->id,
                'fullname' => $student->fullname,
                'birthdate' => $student->birthdate,
                'sex' => $student->sex,
                'status' => $student->status,
                'club' => $isSuperAdmin ? $student->clubs->first() : null,
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
        $authorizedRoles = ['admin', 'secretaire', 'instructeur'];

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
        $activeId = $request->attributes->get('organisateur_id'); // L'ID du club actuel
        $activeType = $request->attributes->get('organisateur_type');
        $validated = $request->validated();
        // Trouver la saison active actuelle dans la base de données
        $saisonActive = $this->saisonActivePour($activeId, $activeType);
        $messageAucuneSaison = $this->messageAucuneSaisonActivePour($activeId, $activeType);

        try {
            return DB::transaction(function () use ($validated, $activeId, $activeType, $request, $saisonActive, $messageAucuneSaison) {
                $parentId = null;
                $createdStudents = [];


                if (!$saisonActive) {
                    return response()->json(['success' => false, 'message' => $messageAucuneSaison], 400);
                }

                $seasonId = $saisonActive->id;

                // Un Club s'auto-affecte toujours comme club de l'élève/parent.
                // Une Ligue/Fédération qui inscrit un athlète manuellement
                // peut choisir un club réel de son ressort (club_id fourni)
                // ou laisser vide (athlète indépendant, sans club) —
                // $clubIdPourLiaison reste alors null : utiliser $activeId
                // dans ce cas planterait, ce serait l'ID de la Ligue/
                // Fédération elle-même, pas un club réel.
                $clubIdPourLiaison = $activeType === 'Club'
                    ? $activeId
                    : ($validated['club_id'] ?? null);

                // --- GESTION DU PARENT ---
                if (!$validated['is_own_responsible']) {
                    $parentUser = User::updateOrCreate(
                        ['email' => $validated['parent_email']],
                        [
                            'id'       => (string) Str::uuid(),
                            'fullname' => $validated['parent_fullname'],
                            'phone'    => $validated['parent_phone'] ?? null,
                            'password' => Hash::make(Str::random(16)),
                            'current_club_id' => $clubIdPourLiaison,
                        ]
                    );

                    if ($clubIdPourLiaison) {
                        $parentRole = cache()->rememberForever('role_parent', fn() => Role::where('name', 'parent')->first());
                        $parentUser->clubs()->syncWithoutDetaching([$clubIdPourLiaison => ['role_id' => $parentRole->id]]);
                    }

                    $parentProfile = ParentModel::firstOrCreate(['user_id' => $parentUser->id]);
                    $parentId = $parentProfile->id;
                }

                // --- GESTION DES ÉLÈVES ---
                foreach ($validated['students'] as $index => $studentData) {
                    $studentUserId = null;
                    $currentStudentParentId = $parentId;

                    // 1. Gestion du compte User (si responsable ou si accès demandé)
                    if ($validated['is_own_responsible'] || ($studentData['create_account'] ?? false)) {

                        // On cherche si l'utilisateur existe déjà via son email
                        $studentUser = User::where('email', $studentData['email'])->first();

                        if (!$studentUser) {
                            // SCÉNARIO A : C'est un nouvel élève, on crée son compte User
                            $studentUser = User::create([
                                'id'       => (string) Str::uuid(),
                                'fullname' => $studentData['fullname'],
                                'email'    => $studentData['email'],
                                'phone'    => $studentData['phone'] ?? null,
                                'password' => Hash::make(Str::random(32)), // Il changera son mot de passe par mail
                                'current_club_id' => $clubIdPourLiaison,
                            ]);
                        } else {
                            // SCÉNARIO B : L'élève existe déjà (Réinscription ou Transfert)
                            // On met juste à jour son club actuel et ses infos si elles ont changé
                            $studentUser->update([
                                'fullname' => $studentData['fullname'],
                                'phone'    => $studentData['phone'] ?? $studentUser->phone,
                                'current_club_id' => $clubIdPourLiaison,
                            ]);
                        }

                        $studentUserId = $studentUser->id;

                        // Gestion du rôle dans le nouveau club (club_users) —
                        // seulement s'il y a un vrai club (athlète indépendant
                        // sinon, voir $clubIdPourLiaison plus haut).
                        if ($clubIdPourLiaison) {
                            $role = cache()->rememberForever("role_karateka", fn() => Role::where('name', 'karateka')->first());
                            if ($role) {
                                // syncWithoutDetaching évite de recréer la ligne si elle existe déjà pour ce club
                                $studentUser->clubs()->syncWithoutDetaching([$clubIdPourLiaison => ['role_id' => $role->id]]);
                            }
                        }
                    }

                    // 2. Gestion de la photo
                    $photoPath = null;
                    $photoFile = $request->file("students.$index.photo");
                    if ($photoFile) {
                        $photoPath = $photoFile->store('students', 'public');
                    }
                    // --- 3. GESTION DE LA FICHE STUDENT (Recherche ou Création) ---
                    $student = null;

                    if ($studentUserId) {
                        // Cas A : L'élève a un compte utilisateur, on cherche par son user_id
                        $student = Student::where('user_id', $studentUserId)->first();
                    } else {
                        // Cas B : Enfant sans compte, on cherche par ses données d'identité uniques
                        $student = Student::where('fullname', $studentData['fullname'])
                            ->where('birthdate', $studentData['birthdate'])
                            ->where('sex', $studentData['sex'])
                            ->first();
                    }

                    if (!$student) {
                        // SCÉNARIO 1 : L'élève n'existe nulle part, c'est sa toute première inscription
                        $student = Student::create([
                            'fullname'  => $studentData['fullname'],
                            'birthdate' => $studentData['birthdate'],
                            'sex'       => $studentData['sex'],
                            'user_id'   => $studentUserId,
                            'is_adult'  => Carbon::parse($studentData['birthdate'])->age >= 18,
                            'photo'     => $photoPath,
                            // Athlète indépendant (pas de club) : on retient
                            // qui l'a inscrit directement, sinon aucune
                            // requête d'éligibilité aux épreuves ne pourrait
                            // jamais le retrouver (voir studentsDisponibles()).
                            'organisateur_id'   => $clubIdPourLiaison ? null : $activeId,
                            'organisateur_type' => $clubIdPourLiaison ? null : $activeType,
                        ]);
                    } else {
                        // SCÉNARIO 2 : L'élève existe déjà ! (Réinscription ou Transfert)
                        // On met juste à jour sa photo si une nouvelle a été envoyée
                        if ($photoPath) {
                            $student->update(['photo' => $photoPath]);
                        }

                        // SÉCURITÉ TRANSFERT : On désactive ses anciennes liaisons clubs actives pour les saisons passées
                        $student->clubs()->wherePivot('is_active', true)->updateExistingPivot($student->clubs, ['is_active' => false]);
                    }

                    // --- 4. LIAISON HISTORIQUE CLUB-ÉLÈVE AVEC LE SAISON_ID ---
                    // $clubIdPourLiaison calculé une fois plus haut (avant la
                    // boucle) : null = athlète indépendant, aucune ligne
                    // club_students créée.
                    if ($clubIdPourLiaison) {
                        $student->clubs()->syncWithoutDetaching([
                            $clubIdPourLiaison => [
                                'saison_id' => $seasonId,
                                'is_active' => true
                            ]
                        ]);
                    }

                    // On récupère la fédération : via le club si l'élève en
                    // a un, sinon directement depuis le contexte de
                    // l'organisateur connecté (Ligue/Fédération).
                    if ($clubIdPourLiaison) {
                        $club = Club::with('league')->find($clubIdPourLiaison);
                        $federationId = $club?->league?->federation_id;
                    } elseif ($activeType === 'Federation') {
                        $federationId = $activeId;
                    } elseif ($activeType === 'Ligue') {
                        $federationId = League::find($activeId)?->federation_id;
                    } else {
                        $federationId = null;
                    }

                    // Licence::firstOrCreate(
                    //     [
                    //         'student_id' => $student->id,
                    //         'saison_id'  => $seasonId,
                    //     ],
                    //     [
                    //         'id'             => (string) Str::uuid(),
                    //         'club_id'        => $activeId,
                    //         'federation_id'  => $federationId,
                    //         'licence_number' => $student->matricule,
                    //         'status'         => 0,
                    //         'montant_paye'    => 0.00,
                    //     ]
                    // );
                    // --- 5. Liaison avec le responsable (Parent) ---
                    if ($currentStudentParentId) {
                        $student->parents()->syncWithoutDetaching([$currentStudentParentId]);
                    }

                    // On charge la relation pour la réponse JSON
                    $createdStudents[] = $student->load('user');
                }
                return response()->json([
                    'success' => true,
                    'message' => count($createdStudents) . ' élève(s) enregistré(s) avec succès.',
                    'data'    => $createdStudents
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de l\'enregistrement de l\'élève'], 500);
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
                'club' => $student->clubs->first(),
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

        $latestStudents = Student::whereHas('clubs', function ($query) use ($activeId) {
            $query->where('club_id', $activeId);
        })
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
