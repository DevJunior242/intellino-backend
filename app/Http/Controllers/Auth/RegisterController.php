<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;

class RegisterController extends Controller
{

    public function register(RegisterRequest $request)
    {
        $user = User::create([
             'fullname' => $request->fullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);


        // $token = $user->createToken('authToken')->plainTextToken;
        return response()->json([
            'message' => 'votre compte a ete cree avec succes',
            'user' => $user,
            // 'token' => $token,

        ], 201);
    }
}
