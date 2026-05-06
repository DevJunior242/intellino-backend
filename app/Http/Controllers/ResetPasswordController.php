<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
 class ResetPasswordController extends Controller
{
     // public function forgotPassword(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);

    //     try {


    //         $status = Password::sendResetLink(
    //             $request->only('email')
    //         );


    //         return $status === Password::ResetLinkSent
    //             ? response()->json([
    //                 'success' => true,
    //                 'message' => 'Email envoyé !'
    //             ], 200)
    //             : response()->json([
    //                 'success' => false,
    //                 'message' => 'Email introuvable'
    //             ], 422);
    //     } catch (\Throwable $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur SMTP',
    //             'value' => $e->getMessage()

    //         ], 500);
    //     }
    // }


    public function forgotPassword(Request $request, \App\Services\BrevoService $brevo)
    {
        $request->validate(['email' => 'required|email']);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email introuvable'
            ], 422);
        }

        //  Générer token
        $token = Str::random(64);

        //  Sauvegarder
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => bcrypt($token),
             ]
        );

        //  Lien reset
        $resetUrl = config('app.frontend_url') . "/reset-password?token=$token&email=" . urlencode($user->email);

        // Email HTML
        $html = "
        <h2>Réinitialisation du mot de passe</h2>
        <p>Clique ici pour réinitialiser ton mot de passe :</p>
        <a href='$resetUrl'>Réinitialiser</a>
    ";

        //  Envoi Brevo
        $brevo->send(
            $user->email,
            $user->fullname ?? 'Utilisateur',
            'Réinitialisation du mot de passe',
            $html
        );

        return response()->json([
            'success' => true,
            'message' => 'Email envoyé !'
        ]);
    }
    public function sentToken(Request $request, string $token)
    {
        $email = $request->query('email');
        return redirect(config('app.frontend_url') . "/reset-password/$token?email=$email");
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PasswordReset
            ? response()->json([
                'success' => true,
                'message' => __('passwords.reset')
            ], 200)
            :  response()->json([
                'success' => false,
                'message' => __('passwords.token')
            ], 422);
    }
}
