<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\StudentPayment;

class StudentPaymentPolicy
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
    public function view(User $user, StudentPayment $studentPayment): bool
    {
        return false;
    }
    public function viewStats(User $user): bool
    {
        return $this->hasStudentPaymentRole($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return  $this->hasStudentPaymentRole($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StudentPayment $studentPayment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = $studentPayment->club_id;

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
    public function delete(User $user, StudentPayment $studentPayment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = $studentPayment->club_id;

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
    public function restore(User $user, StudentPayment $studentPayment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StudentPayment $studentPayment): bool
    {
        return false;
    }

    private function hasStudentPaymentRole(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessTo(['instructeur', 'secretaire', 'admin']);
    }
}
