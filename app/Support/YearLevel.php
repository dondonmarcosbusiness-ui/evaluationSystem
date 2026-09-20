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

    /**
     * Infer a year level from a section name like "4A", "4-B",
     * "BSIT 4A", "1-A", "Year 2 - B" or "3rd Year - A".
     *
     * Returns one of 1st/2nd/3rd/4th or null when no year digit found.
     */
    public static function fromSectionName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);

        // Explicit ordinal wins: "1st ...", "2nd ...", "3rd ...", "4th ...".
        if (preg_match('/([1-4])\s*(st|nd|rd|th)\b/i', $name, $m)) {
            return self::fromDigit($m[1]);
        }

        // Leading digit: "4A", "4-A", "4 A", "4th Year - A".
        if (preg_match('/^\s*([1-4])\b/', $name, $m)) {
            return self::fromDigit($m[1]);
        }

        // Embedded "<digit><letter>" token: "BSIT 4A", "BSIT-4B".
        if (preg_match('/\b([1-4])[A-Za-z]\b/', $name, $m)) {
            return self::fromDigit($m[1]);
        }

        // "Year 2", "Year 2 - B".
        if (preg_match('/\byears?\s*([1-4])\b/i', $name, $m)) {
            return self::fromDigit($m[1]);
        }

        // Spaced/dashed "<digit> - <letter>": "2 - B", "4 - A".
        if (preg_match('/\b([1-4])\s*[-_]\s*[A-Za-z]\b/', $name, $m)) {
            return self::fromDigit($m[1]);
        }

        return null;
    }

    protected static function fromDigit(string $digit): ?string
    {
        return match ($digit) {
            '1' => self::FIRST,
            '2' => self::SECOND,
            '3' => self::THIRD,
            '4' => self::FOURTH,
            default => null,
        };
    }
}