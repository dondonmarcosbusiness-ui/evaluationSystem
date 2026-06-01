<?php

namespace App\Rules;

use App\Enums\EvaluateeType;
use Illuminate\Contracts\Validation\Rule;

class ValidEvaluateeType implements Rule
{
    public function passes($attribute, $value): bool
    {
        return in_array($value, EvaluateeType::values());
    }

    public function message(): string
    {
        return 'The :attribute must be a valid evaluatee type.';
    }
}
