<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Role;

class CheckClubRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();

        $activeId = $request->input('organisateur_id')
            ?? $request->input('club_id')
            ?? $request->input('league_id');

        $activeType = $request->query('organisateur_type')
            ?? $request->input('organisateur_type')
            ?? $request->attributes->get('organisateur_type');
        if (blank($activeId) || in_array($activeId, ['null', 'undefined'], true)) {
            $activeId = null;
        }

        if ($user?->isSuperAdmin()) {
            if ($activeId) {
                // Vérification dynamique de l'existence selon le type
                $modelClass = ($activeType === 'Ligue') ? \App\Models\League::class : \App\Models\Club::class;

                if (!$modelClass::where('id', $activeId)->exists()) {
                    return response()->json(['message' => ucfirst($activeType) . ' introuvable'], 404);
                }
            }


            $request->attributes->set('organisateur_id', $activeId);
            $request->attributes->set('organisateur_type', $activeType);
            $request->attributes->set('club_id', $activeId);
            $request->attributes->set('league_id', $activeId);
            //assign role super_admin
            $request->attributes->set('role', 'super_admin');
            return $next($request);
        }
        // 2. Cas des autres rôles liés au club
        if (!$activeId) {
            return response()->json(['message' => 'Humm, nous sommes desolés, veuillez nous contacter: support@intellino.bf'], 422);
        }
        $club = $user->clubs()
            ->withPivot('role_id')
            ->where('clubs.id', $activeId)
            ->first();
        $league = $user->leagues()
            ->withPivot('role_id')
            ->where('leagues.id', $activeId)
            ->first();
        if ($activeType === 'Club') {

            $activeOrg = $user->clubs()
                ->where('clubs.id', $activeId)
                ->first();
        } elseif ($activeType === 'Ligue') {

            $activeOrg = $user->leagues()
                ->where('leagues.id', $activeId)
                ->first();
        }
        // 1. Identifier l'organisation active 
        $activeOrg = $club ?: $league;

        // 2. Récupérer le nom du rôle manuellement via l'ID stocké dans le pivot
        // On accède à l'ID du rôle qui est dans la table pivot
        $roleId = $activeOrg->pivot->role_id;

        // 3. On va chercher le nom du rôle directement dans la table roles
        $userRole = Role::where('id', $roleId)->value('name');

        // 4. Sécurité si le rôle n'existe pas
        if (!$userRole) {
            return response()->json(['message' => 'Rôle utilisateur introuvable'], 403);
        }

        // 5. Vérification des permissions
        if (!empty($roles) && !in_array($userRole, $roles)) {
            return response()->json(['message' => "Accès interdit : rôle $userRole non autorisé"], 403);
        }

        $request->attributes->set('role', $userRole);
        $request->attributes->set('organisateur_id', $activeId);
        $request->attributes->set('organisateur_type', $activeType);
        $request->attributes->set('club_id', $request->input('club_id'));
        $request->attributes->set('league_id', $request->input('league_id'));
        return $next($request);
    }
}
