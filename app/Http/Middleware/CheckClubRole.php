<?php

namespace App\Http\Middleware;

use Closure;

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
        $clubId = $request->get('club_id') ?? $request->route('club_id');

        if (blank($clubId) || in_array($clubId, ['null', 'undefined'], true)) {
            $clubId = null;
        }

        if ($user?->isSuperAdmin()) {
            if (!empty($clubId) && $clubId !== 'null' && $clubId !== 'undefined') {
                if (!\App\Models\Club::where('id', $clubId)->exists()) {
                    return response()->json(['message' => 'Club introuvable'], 404);
                }
                $request->merge(['validated_club_id' => $clubId]);
            }

            $request->merge(['validated_role_name' => 'super_admin']);
            return $next($request);
        }
        // 2. Cas des autres rôles liés au club
        if (!$clubId) {
            return response()->json(['message' => 'Club ID manquant'], 404);
        }
        $club = $user->clubs()
            ->withPivot('role_id')
            ->where('clubs.id', $clubId)
            ->first();
        if (!$club) {
            return response()->json(['message' => 'Accès refusé au club'], 403);
        }

        $userRole = $club->pivot->role->name;

        if (!empty($roles) && !in_array($userRole, $roles)) {
            return response()->json(['message' => 'Rôle non autorisé'], 403);
        }

        $request->merge([
            'validated_club_id' => $clubId,
            'validated_role_name' => $userRole
        ]);

        return $next($request);
    }
}
