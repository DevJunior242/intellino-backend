<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Student;

class StudentPolicy
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
    public function view(User $user, Student $student): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function viewStats(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $clubId = request()->attributes->get('organisateur_id');

        if (!$clubId) {
            return false;
        }

        return $user->clubs()
            ->where('clubs.id', $clubId)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Une Ligue/Fédération peut inscrire un athlète manuellement (sans
        // club, ou avec un club de son ressort) — CheckClubRole a déjà
        // vérifié le rôle admin/mandat pour cette organisation avant que la
        // requête n'arrive ici, donc pas de re-vérification supplémentaire.
        $activeType = request()->attributes->get('organisateur_type');
        if (in_array($activeType, ['Ligue', 'Federation'], true)) {
            return true;
        }

        $clubId = request()->attributes->get('club_id');

        if (!$clubId) {
            return false;
        }
        return $user->clubs()
            ->where('clubs.id', $clubId)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Student $student): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Student n'a pas de colonne club_id (relation many-to-many via club_students).
        $clubIds = $student->clubs()->wherePivot('is_active', true)->pluck('clubs.id');

        if ($clubIds->isEmpty()) {
            return false;
        }

        return $user->clubs()
            ->whereIn('clubs.id', $clubIds)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Student $student): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $clubIds = $student->clubs()->wherePivot('is_active', true)->pluck('clubs.id');

        if ($clubIds->isEmpty()) {
            return false;
        }

        return $user->clubs()
            ->whereIn('clubs.id', $clubIds)
            ->whereIn('club_users.role_id', Role::clubAdminRoles())
            ->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Student $student): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}
