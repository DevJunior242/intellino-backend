<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Models\League;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function user(Request $request)
    {
        return $request->user();
    }
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = auth()->user();
            $token = $user->createToken('auth')->plainTextToken;


            // 2. CHARGEMENT SÉCURISÉ ET FILTRÉ DES RELATIONS
            $user->load([
                'clubs.roles',
                'activeLeagues.roles',
                'activeFederations.roles',
                'globalRole'
            ]);
            $allRoles = Role::all()->keyBy('id');

            // 3. Fonction helper pour formater les organisations
            $formatOrg = function ($org) use ($allRoles) {
                $roleId = $org->pivot->role_id ?? null;
                $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'league_id' => $org->league_id,
                    'league_name' => $org->league ? $org->league->name : null,
                    'federation_id' => $org->federation_id,
                    'federation_name' => $org->federation ? $org->federation->name : null,
                    'role' => $roleName ? [$roleName] : [],
                    'invitation_code' => $org->invitation_code,
                ];
            };

            // 4. Préparation des listes séparées
            $clubs = $user->clubs->map($formatOrg);
            $leagues = $user->activeLeagues->map($formatOrg);
            $federations = $user->activeFederations->map($formatOrg);


            // 5. Détermination du rôle actuel (Priorité : Fédération > Ligue > Club)
            $currentRole = [];

            if ($user->current_federation_id) {
                $role = $federations->firstWhere('id', $user->current_federation_id)['role'] ?? [];
                $currentRole = $role;
            } elseif ($user->current_league_id) {
                $role = $leagues->firstWhere('id', $user->current_league_id)['role'] ?? [];
                $currentRole = $role;
            } elseif ($user->current_club_id) {
                $role = $clubs->firstWhere('id', $user->current_club_id)['role'] ?? [];
                $currentRole = $role;
            }

            // Sécurité antifraude : nettoyage des sessions expirées si l'utilisateur n'a plus les droits
            if ($user->current_federation_id && empty($currentRole)) {
                $user->update(['current_federation_id' => null]);
            }
            if ($user->current_league_id && empty($currentRole)) {
                $user->update(['current_league_id' => null]);
            }



            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'roleSuperAdmin' => $user->globalRole ? [$user->globalRole->name] : [],
                'clubs' => $clubs,
                'leagues' => $leagues,
                'federations' => $federations,
                'role' => $currentRole,
            ]);
        } else {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }
    }
    public function logout()
    {
        $user = auth()->user();
        if ($user) {
            $user->tokens()->delete();
            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
        } else {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }
    }

    public function me(Request $request)
    {
        // 1. Récupérer l'utilisateur de base avec ses relations directes
        $user = $request->user();
        $user->load([
            'clubs.roles',
            'activeLeagues.roles',
            'activeFederations.roles',
            'globalRole'
        ]);
        // 2. Extraire tous les IDs de ligues uniques associés aux clubs pour éviter le N+1

        $allRoles = Role::all()->keyBy('id');

        $formatOrg = function ($org) use ($allRoles) {
            $roleId = $org->pivot->role_id ?? null;
            $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

            return [
                'id' => $org->id,
                'name' => $org->name,
                'league_id' => $org->league_id,
                'league_name' => $org->league ? $org->league->name : null,
                'federation_id' => $org->federation_id,
                'federation_name' => $org->federation ? $org->federation->name : null,
                'role' => $roleName ? [$roleName] : [],
                'invitation_code' => $org->invitation_code,
            ];
        };

        // 4. Préparation des listes séparées
        $clubs = $user->clubs->map($formatOrg);
        $leagues = $user->activeLeagues->map($formatOrg);
        $federations = $user->activeFederations->map($formatOrg);


        // 5. Détermination du rôle actuel (Priorité : Fédération > Ligue > Club)
        $currentRole = [];

        if ($user->current_federation_id) {
            $role = $federations->firstWhere('id', $user->current_federation_id)['role'] ?? [];
            $currentRole = $role;
        } elseif ($user->current_league_id) {
            $role = $leagues->firstWhere('id', $user->current_league_id)['role'] ?? [];
            $currentRole = $role;
        } elseif ($user->current_club_id) {
            $role = $clubs->firstWhere('id', $user->current_club_id)['role'] ?? [];
            $currentRole = $role;
        }

        // Sécurité antifraude : nettoyage des sessions expirées si l'utilisateur n'a plus les droits
        if ($user->current_federation_id && empty($currentRole)) {
            $user->update(['current_federation_id' => null]);
        }
        if ($user->current_league_id && empty($currentRole)) {
            $user->update(['current_league_id' => null]);
        }



        return response()->json([
            'success' => true,
            'user' => $user,
            'roleSuperAdmin' => $user->globalRole ? [$user->globalRole->name] : [],
            'clubs' => $clubs,
            'leagues' => $leagues,
            'federations' => $federations,
            'role' => $currentRole,
        ]);
    }
}
