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

    public function index(Request $request)
    {

        // On détermine l'ID et le type dynamiquement
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $relation = $activeType === 'Ligue' ? 'leagues' : 'federations';
        $roleinclus = ["admin_league", "secretaire_league", "directeur_technique"];

        $roleIds = Role::whereIn('name', $roleinclus)->pluck('id');

        $allRoles = Role::whereIn('name', $roleinclus)->get();

        $members = User::whereHas($relation, function ($q) use ($activeId, $roleIds) {

            $q->where('leagues.id', $activeId)
                ->whereIn('league_users.role_id', $roleIds);
        })
            ->with([$relation => function ($q) use ($activeId) {

                $q->where('id', $activeId)
                    ->withPivot('role_id');
            }])
            ->get()
            ->map(function ($member) use ($relation, $allRoles) {

                $orgData = $member->$relation->first();

                $roleId = $orgData?->pivot?->role_id;

                $roleObj = $allRoles->firstWhere('id', $roleId);
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
            'organization_type' => $activeType,
            'members' => $members
        ]);
    }

    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');


        $targetUser = User::where('email', $validated['email'])
            ->orWhere('phone', $validated['phone'])
            ->first();
        if ($targetUser) {
            $isAlreadyMember = DB::table('league_users')
                ->where('user_id', $targetUser->id)
                ->where('league_id', $activeId)
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
            ->where('league_id', $activeId)
            ->where('role_id', $validated['role_id'])
            ->exists();

        if (!$exists) {
            $targetUser->leagues()->sync([
                $activeId => ['role_id' => $validated['role_id']]
            ], false);
        }

        $targetUser->current_league_id = $activeId;
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
            'Admin_league'   => 'error',   // Rouge
            'Arbitre_league' => 'info',    // Bleu
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



    public function arbitres(Request $request)
    {
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');
        $relation = $activeType === 'Ligue' ? 'leagues' : 'federations';
        $roleinclus = ["arbitre_league"];

        $roleIds = Role::whereIn('name', $roleinclus)->pluck('id');

        $allRoles = Role::whereIn('name', $roleinclus)->get();

        $members = User::whereHas($relation, function ($q) use ($activeId, $roleIds) {

            $q->where('leagues.id', $activeId)
                ->whereIn('league_users.role_id', $roleIds);
        })
            ->with([$relation => function ($q) use ($activeId) {

                $q->where('id', $activeId)
                    ->withPivot('role_id');
            }])
            ->get()
            ->map(function ($member) use ($relation, $allRoles) {

                $orgData = $member->$relation->first();

                $roleId = $orgData?->pivot?->role_id;

                $roleObj = $allRoles->firstWhere('id', $roleId);
                return [
                    'id'         => $member->id,
                    'name'       => $member->fullname,
                    'email'      => $member->email,
                    'phone'      => $member->phone,
                    'initials'   => strtoupper(substr($member->fullname, 0, 2)),

                ];
            });

        return response()->json([
            'success' => true,
            'organization_type' => $activeType,
            'members' => $members
        ]);
    }
}
