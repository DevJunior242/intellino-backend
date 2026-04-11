<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function roles(Request $request)
    {


        $isSuperAdmin = ($request->validated_role_name === 'super_admin');
        $clubId = $request->validated_club_id;
        if ($isSuperAdmin) {
            $club = Club::find($clubId);
            if (!$club) {
                return response()->json(['message' => 'Club sélectionné invalide'], 422);
            }
        }

        Log::info('club_id', ['clubId' => $clubId]);

        $roleOut = ['super_admin', 'admin_club', 'admin_league', 'arbitre_league', 'secretaire_league', 'instructeur_league', 'student'];

        $roles = Role::whereNotIn('name', $roleOut)->get();
        return response()->json(['success' => true, 'roles' => $roles]);
    }
}
