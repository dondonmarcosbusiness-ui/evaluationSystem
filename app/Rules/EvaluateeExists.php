<?php

namespace App\Rules;

use App\Enums\EvaluateeType;
use App\Models\Faculty;
use App\Models\Staff;
use Illuminate\Contracts\Validation\Rule;

class EvaluateeExists implements Rule
{
    private $evaluateeType;

    public function __construct($evaluateeType)
    {
        $this->evaluateeType = $evaluateeType;
    }

    public function passes($attribute, $value): bool
    {
        return match ($this->evaluateeType) {
            EvaluateeType::FACULTY->value => Faculty::where('id', $value)->exists(),
            EvaluateeType::STAFF->value => Staff::where('id', $value)->exists(),
            default => false,
        };
    }

    public function message(): string
    {
        return "The :attribute does not exist in the {$this->evaluateeType} table.";
    }
}
