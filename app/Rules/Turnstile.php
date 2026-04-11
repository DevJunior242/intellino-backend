<?php

namespace App\Rules;

use Closure;
use App\Services\TurnstileService;
use Illuminate\Contracts\Validation\ValidationRule;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $turnstile = app(TurnstileService::class);

        if(!$value || !$turnstile->verify($value)) {
            $fail('La verification de securité a échouée');
        }
    }
}
