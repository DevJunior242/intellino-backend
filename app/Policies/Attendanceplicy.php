<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Auth\Access\Response;

class Attendanceplicy
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
    public function view(User $user, Attendance $attendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->clubs()
            ->wherePivotIn('role', ['admind_club', 'instructeur'])
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = $attendance->club_id;

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
    public function delete(User $user, Attendance $attendance): bool
    {

        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = $attendance->club_id;

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
    public function restore(User $user, Attendance $attendance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return false;
    }
}
