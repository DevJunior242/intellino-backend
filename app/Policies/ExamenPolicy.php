<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Examen;

class ExamenPolicy
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
    public function view(User $user, Examen $examen): bool
    {
        return false;
    }

    public function viewStats(User $user): bool
    {
        return $this->hasExamenRole($user);
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->hasExamenRole($user);
    }


    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Examen $examen): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $clubId = $examen->club_id;

        if (!$clubId) {
            return false;
        }

        return $user->clubs()
            ->where('clubs.id', $clubId)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Examen $examen): bool
    {

        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = $examen->club_id;
        if (!$clubId) {
            return false;
        }

        return $user->clubs()
            ->where('clubs.id', $clubId)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Examen $examen): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Examen $examen): bool
    {
        return false;
    }
    private function hasExamenRole(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasClubRole(['instructeur', 'admin_club']);
    }
}
