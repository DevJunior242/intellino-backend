<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\LeagueUser;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Services\RoleSecurityService;
use App\Notifications\WelcomeToLeague;
use App\Notifications\WelcomeNewMember;
use App\Http\Requests\StoreMemberRequest;

class LeagueMemberController extends Controller
{
    protected $roleSecurity;

    public function __construct(RoleSecurityService $roleSecurity)
    {
        $this->roleSecurity = $roleSecurity;
    }

    public function index(Request $request)
    {
        // On détermine l'ID de la ligue active dynamiquement
        $activeId = $request->attributes->get('organisateur_id');

        $roleinclus = ["admin", "secretaire", "directeur_technique", "responsable_technique", "vice_president"];

        $roleIds = Role::whereIn('name', $roleinclus)->pluck('id');
        $allRoles = Role::whereIn('name', $roleinclus)->get();

        $members = User::whereHas('leagues', function ($q) use ($activeId, $roleIds) {
            $q->where('leagues.id', $activeId)
                ->whereIn('league_users.role_id', $roleIds);
        })
            ->with(['leagues' => function ($q) use ($activeId) {
                $q->where('leagues.id', $activeId)
                    ->withPivot('role_id', 'mandate_start_at', 'mandate_end_at', 'mandate_status');
            }])
            ->get()
            ->map(function ($member) use ($allRoles) {
                $leagueData = $member->leagues->first();

                // Récupération des données de la table pivot league_users
                $roleId = $leagueData?->pivot?->role_id;
                $startAt = $leagueData?->pivot?->mandate_start_at;
                $endAt = $leagueData?->pivot?->mandate_end_at;
                $status = $leagueData?->pivot?->mandate_status;

                $roleObj = $allRoles->firstWhere('id', $roleId);
                $roleName = $roleObj ? $roleObj->name : 'Membre';

                // Le mandat s'affiche uniquement pour l'admin de la ligue
                if ($roleName === 'admin') {
                    $startYear = $startAt ? date('Y', strtotime($startAt)) : '';
                    $endYear = $endAt ? date('Y', strtotime($endAt)) : 'En cours';
                    $mandateString = "$startYear – $endYear";
                } else {
                    $mandateString = '';
                }

                return [
                    'id'             => $member->id,
                    'name'           => $member->fullname,
                    'email'          => $member->email,
                    'initials'       => strtoupper(substr($member->fullname, 0, 2)),
                    'role'           => $roleName,
                    'badge'          => $roleName,
                    'badgeColor'     => $this->getBadgeColor($roleObj?->name),
                    'avatarBg'       => $this->generateAvatarColor($member->fullname),
                    'mandate'        => $mandateString,
                    'mandate_status' => $status,
                ];
            });

        return response()->json([
            'success' => true,
            'organization_type' => 'Ligue',
            'members' => $members
        ]);
    }



    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();
        $activeId = $request->attributes->get('organisateur_id');
        $activeType = $request->attributes->get('organisateur_type');

        $this->roleSecurity->checkPermission(
            $activeType,
            $request->attributes->get('role'),
            $validated['role_id']
        );

        // 1. Rechercher si l'email ou le téléphone existe individuellement
        $userByEmail = User::where('email', $validated['email'])->first();
        $userByPhone = User::where('phone', $validated['phone'])->first();

        // Si l'email et le téléphone appartiennent à deux comptes différents, c'est un conflit majeur
        if ($userByEmail && $userByPhone && $userByEmail->id !== $userByPhone->id) {
            return response()->json([
                'success' => false,
                'message' => 'Conflit : Cet email et ce numéro de téléphone appartiennent à deux utilisateurs différents.'
            ], 422);
        }

        // On définit l'utilisateur cible s'il existe (soit par email, soit par téléphone)
        $targetUser = $userByEmail ?? $userByPhone;

        if ($targetUser) {
            // Vérifier s'il est déjà membre de cette ligue spécifique
            $isAlreadyMember = DB::table('league_users')
                ->where('user_id', $targetUser->id)
                ->where('league_id', $activeId)
                ->exists();

            if ($isAlreadyMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur est déjà membre de cette ligue.'
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
            LeagueUser::create([
                'league_id' => $activeId,
                'user_id' => $targetUser->id,
                'role_id' => $validated['role_id'],
            ]);
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

        $roles = Role::whereNotIn('name', ['super_admin', 'admin', 'parent', 'karateka', 'instructeur', 'secretaire'])
            ->get();
        return response()->json(['success' => true, 'roles' => $roles]);
    }

    private function getBadgeColor($roleName)
    {
        return match ($roleName) {
            'admin'   => 'error',   // Rouge
            'arbitre' => 'info',    // Bleu
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
        // On détermine l'ID de la ligue active dynamiquement
        $activeId = $request->attributes->get('organisateur_id');

        // Uniquement pour le rôle arbitre
        $roleinclus = ["arbitre"];

        $roleIds = Role::whereIn('name', $roleinclus)->pluck('id');

        $members = User::whereHas('leagues', function ($q) use ($activeId, $roleIds) {
            $q->where('leagues.id', $activeId)
                ->whereIn('league_users.role_id', $roleIds);
        })
            ->with(['leagues' => function ($q) use ($activeId) {
                $q->where('leagues.id', $activeId)
                    ->withPivot('role_id');
            }])
            ->get()
            ->map(function ($member) {
                return [
                    'id'       => $member->id,
                    'name'     => $member->fullname,
                    'email'    => $member->email,
                    'phone'    => $member->phone,
                    'initials' => strtoupper(substr($member->fullname, 0, 2)),
                ];
            });

        return response()->json([
            'success'           => true,
            'organization_type' => 'Ligue',
            'members'           => $members
        ]);
    }

    public function destroyArbitre(Request $request, string $userId)
    {
        if ($request->attributes->get('role') !== 'admin') {
            return response()->json(['success' => false, 'message' => "Action non autorisée."], 403);
        }

        $activeId = $request->attributes->get('organisateur_id');
        $arbitreRoleId = Role::where('name', 'arbitre')->value('id');

        $deleted = DB::table('league_users')
            ->where('league_id', $activeId)
            ->where('user_id', $userId)
            ->where('role_id', $arbitreRoleId)
            ->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => "Arbitre introuvable pour cette ligue."], 404);
        }

        return response()->json(['success' => true, 'message' => 'Arbitre retiré avec succès.']);
    }
}
