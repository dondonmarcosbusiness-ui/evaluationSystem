<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) < 8) {
            $fail('Password must be at least 8 characters.');

            return;
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Password must contain at least one uppercase letter.');

            return;
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Password must contain at least one special character.');
        }
    }
}
