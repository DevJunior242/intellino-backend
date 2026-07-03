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
        return $this->hasOrganisateurAccess($user, $examen);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Examen $examen): bool
    {
        return $this->hasOrganisateurAccess($user, $examen);
    }

    /**
     * L'examen appartient à un organisateur polymorphe (Club, Ligue ou
     * Fédération) — on vérifie que l'utilisateur a un rôle admin/instructeur
     * sur CETTE organisation précise, quel que soit son type.
     */
    private function hasOrganisateurAccess(User $user, Examen $examen): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $relation = match ($examen->organisateur_type) {
            'Club' => 'clubs',
            'Ligue' => 'leagues',
            'Federation' => 'federations',
            default => null,
        };

        if (!$relation || !$examen->organisateur_id) {
            return false;
        }

        return $user->$relation()
            ->where($relation . '.id', $examen->organisateur_id)
            ->whereIn($relation . '_users.role_id', Role::AccessRoles())
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

        // Un seul rôle "admin" existe en base (table roles), commun à
        // Club/Ligue/Fédération — la distinction se fait via organisateur_type,
        // pas via le nom du rôle. Les anciens noms "admin_club"/"admin_league"
        // ne correspondent à aucune ligne de la table roles.
        return $user->hasAccessTo(['instructeur', 'admin']);
    }
}
