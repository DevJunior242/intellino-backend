<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SessionModel;
use Illuminate\Auth\Access\Response;

class SessionPolicy
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
    public function view(User $user, SessionModel $sessionModel): bool
    {
        return false;
    }

    public function viewStats(User $user): bool
    {
        return $this->hasSessionRole($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->hasSessionRole($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SessionModel $sessionModel): bool
    {
        return $this->hasSessionRole($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SessionModel $sessionModel): bool
    {
        return $this->hasSessionRole($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SessionModel $sessionModel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SessionModel $sessionModel): bool
    {
        return false;
    }
    private function hasSessionRole(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasClubRole(['instructeur', 'admin_club']);
    }
}
