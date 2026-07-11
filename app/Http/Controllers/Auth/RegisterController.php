<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\BrevoService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;

class RegisterController extends Controller
{

    public function register(RegisterRequest $request, BrevoService $brevo)
    {
        $user = User::create([
             'fullname' => $request->fullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        EmailVerificationController::sendVerificationEmail($user, $brevo);

        // $token = $user->createToken('authToken')->plainTextToken;
        return response()->json([
            'message' => 'Votre compte a été créé avec succès. Un email de confirmation vous a été envoyé, merci de vérifier votre boîte de réception.',
            'user' => $user,
            // 'token' => $token,

        ], 201);
    }
}
