<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function user()
    {
        $user = auth()->user();
        $role = $user->role ? [$user->role->name] : [];
        return response()->json([
            'user' => $user,
            'role' => $role,
        ]);
    }
    public function login(LoginRequest $request)
    {

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = auth()->user();
            if ($user) {
                $token = $user->createToken('auth')->plainTextToken;
            }
            // On détermine l'ID et le type dynamiquement
            $activeId = $user->current_club_id ?? $user->current_league_id ?? $user->current_federation_id;
            $relation = $user->current_club_id
                ? 'clubs'
                : ($user->current_league_id
                    ? 'leagues'
                    : ($user->current_federation_id
                        ? 'federations'
                        : null
                    ));
            if (!$relation) {
                return response()->json([
                    'success' => true,
                    'user' => $user,
                    'token' => $token,
                    'roleSuperAdmin' => $user->globalRole?->name ? [$user->globalRole->name] : [],
                    'memberships' => [],
                    'role' => [],
                ]);
            }
            $user->load($relation);

            $allRoles =  Role::all()->keyBy('id');

            $organizations = collect($user->$relation)->map(function ($org) use ($allRoles) {

                $roleId = $org->pivot->role_id ?? null;
                $roleObj = $allRoles->get($roleId);

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'role' => $roleObj ? $roleObj->name : 'Membre',
                ];
            });

            // $user->load([$relation]);

            $roleSuperAdmin = $user->globalRole?->name;
            $currentRole = $organizations->first()['role'] ?? null;
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'roleSuperAdmin' => $roleSuperAdmin ? [$roleSuperAdmin] : [],
                'memberships' => $organizations,
                'role' => [$currentRole],
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
}
