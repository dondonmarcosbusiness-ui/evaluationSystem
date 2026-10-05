<?php

namespace App\Support;

/**
 * Shared helpers for CSV downloads (account exports, reports).
 */
class Csv
{
    /**
     * Emit the UTF-8 BOM so Excel detects the encoding.
     * (Import endpoints strip it before parsing.)
     */
    public static function bom(): void
    {
        echo "\xEF\xBB\xBF";
    }

    /**
     * Neutralise spreadsheet formula injection — values starting with
     * = + - @ or a control character are prefixed with an apostrophe.
     */
    public static function safe(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
