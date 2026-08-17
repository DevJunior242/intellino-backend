<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    private function engine(): Google2FA
    {
        return new Google2FA;
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::random(10) . '-' . Str::random(10))
            ->all();
    }

    /**
     * Étape 1 : génère un nouveau secret (pas encore actif tant qu'il n'est
     * pas confirmé par confirm()) et l'URL otpauth:// correspondante. Le
     * frontend la transforme en QR code lui-même : l'API ne rend aucune
     * image, elle ne fait que fournir la donnée.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            throw ValidationException::withMessages([
                'two_factor' => ["L'authentification à deux facteurs est déjà activée."],
            ]);
        }

        $secret = $this->engine()->generateSecretKey();

        // Écrase un secret précédemment généré mais jamais confirmé (ex :
        // l'utilisateur a quitté la page avant de scanner le QR code).
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $this->engine()->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret,
            ),
        ]);
    }

    /**
     * Étape 2 : confirme le secret généré par enable() avec un code TOTP
     * valide. Les codes de récupération ne sont générés qu'ici, et affichés
     * une seule fois dans cette réponse.
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->two_factor_secret || $user->hasEnabledTwoFactor()) {
            throw ValidationException::withMessages([
                'code' => ['Aucune activation en attente de confirmation.'],
            ]);
        }

        if (! $this->engine()->verifyKey($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Code invalide.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return response()->json([
            'message' => 'Authentification à deux facteurs activée.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Régénère les codes de récupération (les précédents deviennent
     * invalides) : mot de passe requis, comme pour disable().
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $validated = $request->validate(['password' => ['required']]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        if (! $user->hasEnabledTwoFactor()) {
            throw ValidationException::withMessages([
                'password' => ["L'authentification à deux facteurs n'est pas activée."],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    public function disable(Request $request)
    {
        $validated = $request->validate(['password' => ['required']]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => 'Authentification à deux facteurs désactivée.']);
    }

    /**
     * Étape 2 du login (voir LoginController::login) : consomme le jeton
     * temporaire émis à la place du token final quand la 2FA est active, avec
     * soit un code TOTP soit un code de récupération à usage unique. Renvoie
     * exactement la même forme de réponse que LoginController::login pour que
     * le frontend n'ait pas à distinguer les deux chemins.
     */
    public function challenge(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = Cache::get("2fa-challenge:{$validated['token']}");

        if (! $userId) {
            throw ValidationException::withMessages([
                'token' => ['Session de vérification expirée. Reconnectez-vous.'],
            ]);
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasEnabledTwoFactor()) {
            throw ValidationException::withMessages([
                'token' => ['Session de vérification invalide. Reconnectez-vous.'],
            ]);
        }

        if (! empty($validated['recovery_code'])) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $matchIndex = collect($codes)->search(
                fn ($stored) => hash_equals($stored, $validated['recovery_code'])
            );

            if ($matchIndex === false) {
                throw ValidationException::withMessages([
                    'recovery_code' => ['Code de récupération invalide.'],
                ]);
            }

            // Usage unique : on le retire de la liste dès qu'il sert.
            unset($codes[$matchIndex]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
        } elseif (! empty($validated['code'])) {
            if (! $this->engine()->verifyKey($user->two_factor_secret, $validated['code'])) {
                throw ValidationException::withMessages([
                    'code' => ['Code invalide.'],
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'code' => ['Un code est requis.'],
            ]);
        }

        // Jeton à usage unique : qu'il ait servi ou non à un second essai, il
        // ne doit pas rester valide pour de futures tentatives.
        Cache::forget("2fa-challenge:{$validated['token']}");

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json(array_merge(
            ['success' => true, 'token' => $token],
            LoginController::sessionPayload($user)
        ));
    }
}
