<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Http\Exceptions\HttpResponseException;

class RoleSecurityService
{
    /**
     * Valide si l'utilisateur connecté a le droit d'attribuer un rôle spécifique dans un contexte donné.
     * Throws une exception HTTP 403 si l'action est interdite.
     */
    public function checkPermission(string $currentContextType, string $currentUserRole, string $roleTargetId): void
    {
        $roleTarget = Role::findOrFail($roleTargetId);
        $context = strtolower($currentContextType);

        // 1. Protection absolue du Super Admin
        if ($roleTarget->name === 'super_admin') {
            $this->deny("Action interdite. Ce rôle est réservé au propriétaire du SaaS.");
        }

        // 2. Sécurité au niveau CLUB
        if ($context === 'club') {
            // Un admin de club ne peut pas créer un autre admin ou un rôle fédéral (dtn)
            if (in_array($roleTarget->name, ['admin', 'dtn'])) {
                $this->deny("En tant qu'administrateur de club, vous ne pouvez pas attribuer ce rôle.");
            }
        }

        // 3. Sécurité au niveau LIGUE
        if ($context === 'ligue' || $context === 'league') {
            // Un admin de ligue ne peut pas nommer un autre admin de ligue ou un rôle fédéral (dtn)
            if (in_array($roleTarget->name, ['admin', 'dtn'])) {
                $this->deny("En tant qu'administrateur de ligue, vous ne pouvez pas attribuer ce rôle.");
            }
        }

        // 4. Sécurité au niveau FÉDÉRATION
        if ($context === 'federation') {
            // Par exemple, seul le super_admin peut créer un admin de fédération
            if ($roleTarget->name === 'admin' && $currentUserRole !== 'super_admin') {
                $this->deny("Seul le Super Administrateur peut nommer un nouvel administrateur fédéral.");
            }
        }
    }

    /**
     * Centralise la réponse d'erreur
     */
    private function deny(string $message): void
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], 403)
        );
    }
}
