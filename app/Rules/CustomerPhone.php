<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CustomerPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $fail('Entrez un numéro de téléphone valide, par exemple +243 812 345 678.');
        }
    }
}
