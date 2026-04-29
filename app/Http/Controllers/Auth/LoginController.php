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
            $token = $user->createToken('auth')->plainTextToken;


            $user->load(['clubs', 'leagues', 'globalRole']);

            $allRoles = Role::all()->keyBy('id');

            // 2. Fonction helper pour formater les organisations
            $formatOrg = function ($org) use ($allRoles) {
                $roleId = $org->pivot->role_id ?? null;
                $roleName = $roleId ? ($allRoles->get($roleId)->name ?? null) : null;

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'role' => $roleName ? [$roleName] : [],
                ];
            };

            // 3. On prépare les listes séparées
            $clubs = $user->clubs->map($formatOrg);
            $leagues = $user->leagues->map($formatOrg);
            //  $federations = $user->federations->map($formatOrg);

            // 4. On détermine le rôle actuel basé sur l'ID actif (priorité Ligue > Club)
            $currentRole = [];
            if ($user->current_league_id) {
                $role = $leagues->firstWhere('id', $user->current_league_id)['role'] ?? [];
                $currentRole = $role;
            } elseif ($user->current_club_id) {
                $role = $clubs->firstWhere('id', $user->current_club_id)['role'] ?? [];
                $currentRole = $role;
            }

            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'roleSuperAdmin' => $user->globalRole ? [$user->globalRole->name] : [],
                'clubs' => $clubs, // Liste des clubs
                'leagues' => $leagues,         // Liste des ligues
                // 'federations' => $federations, // Liste des fédérations
                'role' => $currentRole, // Rôle actuel
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
