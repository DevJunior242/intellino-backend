<?php

namespace App\Http\Controllers;


use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{


    public function getRoles(Request $request)
    {
        // 1. On récupère le type d'organisation active validé par ton middleware
        $organisateurType = $request->attributes->get('organisateur_type')
            ?? $request->input('organisateur_type');

        // 2. On définit dynamiquement les rôles autorisés selon le contexte
        $roleInclus = match (strtolower($organisateurType)) {
            'club' => [
                'instructeur',
                'secretaire',
            ],

            'ligue' => [
                'secretaire',
                'arbitre',
                'dtn',
                'instructeur',
            ],
            'federation' => [
                'dtn',
                'arbitre',
                'vice-president',
            ],
            default => [],
        };
        if (empty($roleInclus)) {
            return response()->json(['success' => true, 'roles' => []]);
        }
        // 3. Récupération des rôles filtrés
        $roles = Role::whereIn('name', $roleInclus)->get();


        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }
}
