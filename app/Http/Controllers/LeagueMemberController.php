<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Notifications\WelcomeToLeague;
use App\Notifications\WelcomeNewMember;
use App\Http\Requests\StoreMemberRequest;

class LeagueMemberController extends Controller
{

    public function index()
    {
        $user = auth()->user();

        // On détermine l'ID et le type dynamiquement
        $activeId = $user->current_league_id ?? $user->current_federation_id;
        $relation = $user->current_league_id ? 'leagues' : 'federations';

        if (!$activeId) {
            return response()->json(['message' => 'Aucune organisation active'], 400);
        }
        $allRoles =  Role::all()->keyBy('id');
        $members = User::whereHas($relation, function ($q) use ($activeId) {
            $q->where('id', $activeId);
        })
            ->with([$relation => function ($q) use ($activeId) {
                // On récupère le rôle via le pivot et on charge la relation 'role' si elle existe
                $q->where('id', $activeId)->withPivot('role_id');
            }])
            ->get()
            ->map(function ($member) use ($relation, $allRoles) {
                // On simplifie l'objet pour le Frontend
                $orgData = $member->$relation->first();
                $roleId = $orgData?->pivot?->role_id;

                $roleObj = $allRoles->get($roleId);
                // 2. Vérifier si l'organisation et le pivot existent avant d'accéder aux données
                if (!$orgData || !$orgData->pivot) {
                    return [
                        'id' => $member->id,
                        'fullname' => $member->fullname,
                        'role_name' => 'Non défini',
                        'role_id' => null
                    ];
                }

                // 3. Accès sécurisé au pivot
                $roleId = $orgData?->pivot->role_id;
                return [
                    'id'         => $member->id,
                    'name'       => $member->fullname,
                    'email'      => $member->email,
                    'initials'   => strtoupper(substr($member->fullname, 0, 2)),
                    'role'       => $roleObj ? $roleObj->name : 'Membre',
                    'badge'      => $roleObj ? $roleObj->name : 'Inconnu',
                    'badgeColor' => $this->getBadgeColor($roleObj?->name),
                    'avatarBg'   => $this->generateAvatarColor($member->fullname),
                    'mandate'    => '2023–2027',
                ];
            });

        return response()->json([
            'success' => true,
            'organization_type' => $user->current_league_id ? 'Ligue' : 'Fédération',
            'members' => $members
        ]);
    }

    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $leagueId = $user->current_league_id;


        $targetUser = User::where('email', $validated['email'])
            ->orWhere('phone', $validated['phone'])
            ->first();
        if ($targetUser) {
            $isAlreadyMember = DB::table('league_users')
                ->where('user_id', $targetUser->id)
                ->where('league_id', $leagueId)
                ->exists();

            if ($isAlreadyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur est deja membre.'
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
                'password' => Hash::make('password123'),
            ]);
        }
        $exists = DB::table('league_users')
            ->where('user_id', $targetUser->id)
            ->where('league_id', $leagueId)
            ->where('role_id', $validated['role_id'])
            ->exists();

        if (!$exists) {
            $targetUser->leagues()->sync([
                $leagueId => ['role_id' => $validated['role_id']]
            ], false);
        }

        $targetUser->current_league_id = $leagueId;
        $targetUser->save();
        $targetUser->notify(new WelcomeToLeague($targetUser));



        return response()->json([
            'success' => true,
            'message' => 'Membre ajouté avec succès',
        ], 201);
    }

    public function getRoles(Request $request)
    {

        $roles = Role::whereNotIn('name', ['super_admin', 'admin_club', 'admin_league', 'parent', 'karateka', 'instructeur', 'secretaire'])
            ->get();
        return response()->json(['success' => true, 'roles' => $roles]);
    }

    private function getBadgeColor($roleName)
    {
        return match ($roleName) {
            'Admin'   => 'error',   // Rouge
            'Arbitre' => 'info',    // Bleu
            'Coach'   => 'warning', // Orange
            default   => 'success', // Vert
        };
    }
    private function generateAvatarColor($name)
    {
        $hash = md5($name);
        $color = str_replace('#', '', str_replace('ff', '', $hash));
        return "#{$color}";
    }
}
