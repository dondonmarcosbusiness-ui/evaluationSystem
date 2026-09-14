<?php

namespace App\Support;

class YearLevel
{
    public const FIRST = '1st';
    public const SECOND = '2nd';
    public const THIRD = '3rd';
    public const FOURTH = '4th';
    public const IRREGULAR = 'Irregular';

    public const VALUES = [
        self::FIRST,
        self::SECOND,
        self::THIRD,
        self::FOURTH,
        self::IRREGULAR,
    ];

    /**
     * Validation rule for year_level fields.
     * Existing untagged records use null (= visible under all years).
     */
    public static function rule(bool $required = false): string
    {
        return ($required ? 'required' : 'nullable')
            . '|string|in:' . implode(',', self::VALUES);
    }

    /**
     * Whether two year levels match for scoping.
     * Null (untagged) or Irregular on either side acts as a wildcard.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }

        if ($a === self::IRREGULAR || $b === self::IRREGULAR) {
            return true;
        }

        return $a === $b;
    }
}
