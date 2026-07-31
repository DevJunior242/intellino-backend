<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Note Kata valide selon WKF Kata Competition Rules Art. 5.4.1 : 0.0
 * (disqualification) ou une valeur entre 5.0 et 10.0 par pas de 0.1.
 */
class NoteKata implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value)) {
            $fail('La note doit être un nombre.');
            return;
        }

        $valeur = (float) $value;

        if ($valeur === 0.0) {
            return; // disqualification
        }

        if ($valeur < 5.0 || $valeur > 10.0) {
            $fail('La note doit être 0 (disqualification) ou comprise entre 5.0 et 10.0.');
            return;
        }

        if (abs(round($valeur, 1) - $valeur) > 0.0001) {
            $fail('La note doit être donnée par pas de 0.1.');
        }
    }
}
