<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function user(Request $request)
    {
        return $request->user();
    }

    /**
     * Construit le payload user/roles/organisations renvoyé après un login
     * réussi. Extrait en méthode statique réutilisable pour que
     * TwoFactorAuthController::challenge (étape 2 du login quand la 2FA est
     * active) renvoie exactement la même forme de réponse.
     */
    public static function sessionPayload(User $user): array
    {
        $user->load([
            'clubs.roles',
            'activeLeagues.roles',
            'activeFederations.roles',
            'globalRole'
        ]);
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

        $clubs = $user->clubs->map($formatOrg);
        $leagues = $user->activeLeagues->map($formatOrg);
        $federations = $user->activeFederations->map($formatOrg);

        // Détermination du rôle actuel (Priorité : Fédération > Ligue > Club)
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

        return [
            'user' => $user,
            'roleSuperAdmin' => $user->globalRole ? [$user->globalRole->name] : [],
            'clubs' => $clubs,
            'leagues' => $leagues,
            'federations' => $federations,
            'role' => $currentRole,
        ];
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = auth()->user();

            if ($user->hasEnabledTwoFactor()) {
                // Pas de token Sanctum tant que le code 2FA n'est pas vérifié :
                // un jeton temporaire en cache (5 min, à usage unique) fait le
                // lien avec TwoFactorAuthController::challenge.
                $challengeToken = Str::random(64);
                Cache::put("2fa-challenge:{$challengeToken}", $user->id, now()->addMinutes(5));

                return response()->json([
                    'two_factor' => true,
                    'token' => $challengeToken,
                ]);
            }

            $token = $user->createToken('auth')->plainTextToken;

            return response()->json(array_merge(
                ['success' => true, 'token' => $token],
                self::sessionPayload($user)
            ));
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
        return response()->json(array_merge(
            ['success' => true],
            self::sessionPayload($request->user())
        ));
    }
}
