<?php

namespace App\Http\Controllers\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRegisReq;
use Illuminate\Support\Facades\Hash;

class AdminRegisterController extends Controller
{

    public function register(AdminRegisReq $request)
    {
        $user = auth()->user();
        $role = $request->attributes->get('role');
        if ($role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits pour effectuer cette action',
            ], 403);
        }
        $selectedRole = Role::find($request->role_id);
        if (in_array($selectedRole->name, ['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce rôle ne peut pas être attribué par un admin de club',
            ], 403);
        }

        $user = User::create([
            "club_id" => $user->club_id,
            'role_id' => $request->role_id,
            'fullname' => $request->fullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);


        return response()->json([
            'success' => true,
            'message' => 'vous avez inscrit un nouvel utilisateur avec succès',
            'user' => $user,


        ], 200);
    }
}
