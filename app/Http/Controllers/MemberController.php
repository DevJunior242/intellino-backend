<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\ParentModel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Notifications\AddedToNewClub;
use App\Notifications\WelcomeNewMember;
use Illuminate\Support\Facades\Password;
use App\Http\Requests\StoreMemberRequest;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $clubId = $request->validated_club_id;
        $role = $request->validated_role_name;
        $isSuperAdmin = ($role === 'super_admin');

        $query = User::query()
            ->select('id', 'fullname', 'phone');
        $excludedRoleId = Role::where('name', 'karateka')->value('id');

        if ($isSuperAdmin) {
            $query->whereHas('clubs', function ($q) use ($excludedRoleId, $clubId) {
                $q->where('clubs.id', $clubId);
                if ($excludedRoleId) {
                    $q->where('club_users.role_id', '!=', $excludedRoleId);
                }
            });

            $query->with(['clubs' => function ($q) {
                $q->select('clubs.id', 'clubs.name')
                    ->withPivot('role_id');
            }]);
        } else {



            $query->whereHas('clubs', function ($q) use ($clubId, $excludedRoleId) {
                $q->where('clubs.id', $clubId)
                    ->where('club_users.role_id', '!=', $excludedRoleId);
            });

            $query->with(['clubs' => function ($q) use ($clubId) {
                $q->where('clubs.id', $clubId)->withPivot('role_id');
            }]);
        }


        $members = $query->latest()->paginate();
        $roles = Role::pluck('name', 'id');

        $membersTransformed = $members->through(function ($member) use ($isSuperAdmin, $roles, $clubId) {
            $club = $member->clubs->first();
            $roleId = $club?->pivot?->role_id;
            return [
                'id' => $member->id,
                'fullname' => $member->fullname,
                'phone' => $member->phone,
                'role' => $roles[$roleId] ?? 'N/A',
                'club' => $isSuperAdmin ? ($club?->name ?? 'Aucun') : null,
            ];
        });

        return response()->json([
            'members' => $membersTransformed,
            'is_super_admin' => $isSuperAdmin,
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'total' => $members->total(),
            ]
        ]);
    }

    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $clubId = $request->validated_club_id;

        $targetUser = User::where('email', $validated['email'])
            ->orWhere('phone', $validated['phone'])
            ->first();
        if ($targetUser) {
            $isAlreadyMember = DB::table('club_users')
                ->where('user_id', $targetUser->id)
                ->where('club_id', $clubId)
                ->exists();

            if ($isAlreadyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur est déjà inscrit dans ce club. Pour changer son rôle, utilisez le menu de modification.'
                ], 422);
            }
        }
        // 2. Créer ou récupérer l'utilisateur
        if (!$targetUser) {
            $targetUser = User::create([
                'id'       => (string) Str::uuid(),
                'fullname' => $validated['fullname'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
                'password' => Hash::make(Str::random(12)),
            ]);
        }
        $exists = DB::table('club_users')
            ->where('user_id', $targetUser->id)
            ->where('club_id', $clubId)
            ->where('role_id', $validated['role_id'])
            ->exists();

        if (!$exists) {
            $targetUser->clubs()->sync([
                $clubId => ['role_id' => $validated['role_id']]
            ], false);
        }

        //creer un token pour la renitialisation du mot de passe
        if ($targetUser->wasRecentlyCreated) {
            $targetUser->current_club_id = $clubId;
            $targetUser->save();
            // $token = Password::createToken($targetUser);
            // $targetUser->notify(new WelcomeNewMember($token));
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Membre ajouté avec succès, mais un compte existant a été trouvé. Un email de notification a été envoyé à l\'utilisateur.',
            ], 201);
            // $targetUser->notify(new AddedToNewClub());
        }

        // 4. Si c'est un parent, on gère son ParentModel
        $role = Role::findOrfail($validated['role_id']);
        // 4. Si c'est un parent, on gère son ParentModel
        if (strtolower($role->name) == 'parent') {
            ParentModel::updateOrCreate(
                ['user_id' => $targetUser->id],
                [
                    'id'         => (string) Str::uuid(),
                    'profession' => $validated['profession'],
                    'domicile'   => $validated['domicile'],
                    'relation'   => $validated['relation'] ?? 'Parent',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Membre ajouté avec succès',
            'user'    => $targetUser->load('parentProfile')
        ], 201);
    }


    //retirer un utilisateur de mon club
    public function deleteUser(Request $request, User $user)
    {
        $clubId = $request->validated_club_id;
        Log::info('clubId', ['clubId' => $clubId]);
        $role = $request->validated_role_name;
        $me = $request->user();
        $meRole = $me->globalRole?->name;
        Log::info('meRole', ['meRole' => $meRole]);

        if ($role !== 'admin_club' && $meRole !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => "Vous n'avez pas le droit de faire cela",
            ], 403);
        }

        if ($me->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez pas vous désinscrire de votre club.contactez les services de la plateforme",
            ], 400);
        }

        if (!$user->clubs()->where('clubs.id', $clubId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "Cet utilisateur ne fait pas partie de votre club.",
            ], 403);
        }
        $user->clubs()->detach($clubId);
        if ($user->current_club_id === $clubId) {
            $nextClub = $user->clubs()->first();
            $user->update(['current_club_id' => $nextClub ? $nextClub->id : null]);
        }
        $user->load('clubs');
        return response()->json([
            'success' => true,
            'message' => 'vous avez retiré un utilisateur de votre club',
        ], 200);
    }

    public function updateRole(Request $request, User $user)
    {
        // 1. L'admin connecté et les infos du middleware
        $admin = $request->user();
        $adminRole = $request->validated_role_name;
        $clubId = $request->validated_club_id;


        if ($adminRole !== 'admin_club') {
            return response()->json([
                'success' => false,
                'message' => "Accès refusé : privilèges insuffisants.",
            ], 403);
        }

        if ($admin->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez pas modifier votre propre rôle.",
            ], 403);
        }

        if (!$user->clubs()->where('clubs.id', $clubId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "Cet utilisateur n'est pas membre de ce club.",
            ], 404);
        }

        $user->clubs()->updateExistingPivot($clubId, [
            'role_id' => $request->role_id
        ]);

        return response()->json([
            'success' => true,
            'message' => "Le rôle de {$user->fullname} a été mis à jour.",
        ]);
    }
}
