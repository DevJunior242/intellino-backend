<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Club;
use App\Models\Role;
use App\Models\League;
use App\Models\Federation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckClubRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();

        // 1. Centralisation de la récupération de l'ID et du Type
        $activeId = $request->input('organisateur_id')
            ?? $request->input('club_id')
            ?? $request->input('league_id')
            ?? $request->input('federation_id');

        $activeType = $request->query('organisateur_type')
            ?? $request->input('organisateur_type')
            ?? $request->attributes->get('organisateur_type');

        if (blank($activeId) || in_array($activeId, ['null', 'undefined'], true)) {
            $activeId = null;
        }

        // ------------------------------------------------------------------
        // CAS COMPTE SUPER ADMIN
        // ------------------------------------------------------------------
        if ($user?->isSuperAdmin()) {
            if ($activeId) {
                $modelClass = match ($activeType) {
                    'Federation' => Federation::class,
                    'Ligue'      => League::class,
                    default      => Club::class,
                };

                if (!$modelClass::where('id', $activeId)->exists()) {
                    return response()->json(['message' => ucfirst($activeType) . ' introuvable'], 404);
                }
            }

            $request->attributes->set('organisateur_id', $activeId);
            $request->attributes->set('organisateur_type', $activeType);
            $request->attributes->set('club_id', $activeId);
            $request->attributes->set('league_id', $activeId);
            $request->attributes->set('federation_id', $activeId);
            $request->attributes->set('role', 'super_admin');
            return $next($request);
        }

        if (!$activeId || !$activeType) {
            return response()->json(['message' => 'Humm, nous sommes désolés, veuillez nous contacter: support@intellino.bf'], 422);
        }

        // ------------------------------------------------------------------
        // CAS UTILISATEUR LAMBDA (Club, Ligue, Fédération)
        // ------------------------------------------------------------------
        $activeOrg = null;

        if ($activeType === 'Club') {
            $activeOrg = $user->clubs()
                ->withPivot('role_id')
                ->where('clubs.id', $activeId)
                ->first();
        } elseif ($activeType === 'Ligue') {
            // SÉCURITÉ : On charge le rôle d'administration de la ligue pour vérifier le mandat
            $adminLeagueRole = Role::where('name', 'admin')->first();

            $activeOrg = $user->leagues()
                ->withPivot('role_id', 'mandate_status')
                ->where('leagues.id', $activeId)
                ->where(function ($query) use ($adminLeagueRole) {
                    // Accès OK si ce n'est pas le rôle admin, OU si c'est l'admin avec un mandat actif (1)
                    $query->where('league_users.role_id', '!=', $adminLeagueRole?->id)
                        ->orWhere('league_users.mandate_status', 1);
                })
                ->first();
        } elseif ($activeType === 'Federation') {
            // SÉCURITÉ : Même traitement restrictif pour la Fédération
            $adminFedRole = Role::where('name', 'admin')->first();

            $activeOrg = $user->federations()
                ->withPivot('role_id', 'mandate_status')
                ->where('federations.id', $activeId)
                ->where(function ($query) use ($adminFedRole) {
                    $query->where('federation_users.role_id', '!=', $adminFedRole?->id)
                        ->orWhere('federation_users.mandate_status', 1);
                })
                ->first();
        }

        // Si l'utilisateur n'est pas lié à l'organisation ou si son mandat a expiré / sauté (takeover)
        if (!$activeOrg) {
            return response()->json([
                'success' => false,
                'message' => 'Accès interdit. Vos droits pour cette structure ont expiré ou sont invalides.'
            ], 403);
        }

        // Extraire proprement le rôle via le pivot valide
        $roleId = $activeOrg->pivot->role_id;
        $userRole = Role::where('id', $roleId)->value('name');

        if (!$userRole) {
            return response()->json(['message' => 'Rôle utilisateur introuvable'], 403);
        }

        // Vérification finale des permissions de la route (Paramètres du Middleware)
        if (!empty($roles) && !in_array($userRole, $roles, true)) {
            return response()->json(['message' => "Accès interdit : rôle $userRole non autorisé"], 403);
        }

        // Injection des attributs sécurisés pour les contrôleurs
        $request->attributes->set('role', $userRole);
        $request->attributes->set('organisateur_id', $activeId);
        $request->attributes->set('organisateur_type', $activeType);
        $request->attributes->set('club_id', $activeType === 'Club' ? $activeId : null);
        $request->attributes->set('league_id', $activeType === 'Ligue' ? $activeId : null);
        $request->attributes->set('federation_id', $activeType === 'Federation' ? $activeId : null);

        return $next($request);
    }
}
