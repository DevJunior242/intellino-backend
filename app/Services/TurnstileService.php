<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function verify(string $token, $remoteip = null): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret'   => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $remoteip,
                ]);
            if (!$response->json('success')) {
                Log::error('Turnstile Fail:', ['error-codes' => $response->json('error-codes')]);
            }
            Log::info('turnstile services', ['config' => config('services.turnstile')]);
            return $response->json('success') ?? false;
         } catch (\Exception $e) {
            Log::error("Turnstile Error: " . $e->getMessage());
            return false;
        }
    }
}
