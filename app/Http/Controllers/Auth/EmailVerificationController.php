<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\BrevoService;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    public static function sendVerificationEmail(User $user, BrevoService $brevo): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $html = "
        <h2>Confirme ton adresse email</h2>
        <p>Clique sur le lien ci-dessous pour confirmer ton adresse email et activer ton compte Intellino :</p>
        <a href='{$verificationUrl}' style='
            background-color: #4F46E5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            margin-top: 16px;
        '>
            Confirmer mon email
        </a>
        <p style='margin-top: 24px;'>Ce lien expire dans 60 minutes.</p>
        ";

        $brevo->send(
            $user->email,
            $user->fullname ?? 'Utilisateur',
            'Confirme ton adresse email',
            $html
        );
    }

    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url') . '/login?verified=0');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/login?verified=1');
    }

    public function resend(Request $request, BrevoService $brevo)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email déjà vérifié',
            ]);
        }

        self::sendVerificationEmail($user, $brevo);

        return response()->json([
            'success' => true,
            'message' => 'Email de vérification renvoyé',
        ]);
    }
}
