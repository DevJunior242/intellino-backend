<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail())) {
            return response()->json([
                'success' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Merci de confirmer ton adresse email avant de continuer.',
            ], 422);
        }

        return $next($request);
    }
}
