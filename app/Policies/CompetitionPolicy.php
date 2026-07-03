<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Competition;
use Illuminate\Auth\Access\Response;

class CompetitionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Competition $competition): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->hasAdminRole($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Competition $competition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $orgId = $competition?->evenement?->organisateur_id;
        if (!$orgId) {
            return false;
        }

        return $user->leagues()
            ->where('leagues.id', $orgId)
            ->whereIn('league_users.role_id', Role::leagueAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Competition $competition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $orgId = $competition?->evenement?->organisateur_id;
        if (!$orgId) {
            return false;
        }

        return $user->leagues()
            ->where('leagues.id', $orgId)
            ->whereIn('league_users.role_id', Role::leagueAdminRoles())
            ->exists();
    }

    public function open(User $user, Competition $competition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $orgId = $competition?->evenement?->organisateur_id;
        if (!$orgId) {
            return false;
        }

        return $user->leagues()
            ->where('leagues.id', $orgId)
            ->whereIn('league_users.role_id', Role::leagueAdminRoles())
            ->exists();
    }
    public function close(User $user, Competition $competition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $orgId = $competition?->evenement?->organisateur_id;
        if (!$orgId) {
            return false;
        }

        return $user->leagues()
            ->where('leagues.id', $orgId)
            ->whereIn('league_users.role_id', Role::leagueAdminRoles())
            ->exists();
    }
    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Competition $competition): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Competition $competition): bool
    {
        return false;
    }

    private function hasAdminRole(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasLeagueRole(['admin', 'arbitre']);
    }
}
