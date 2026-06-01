<?php

namespace App\Enums;

enum EvaluateeType: string
{
    case FACULTY = 'faculty';
    case STAFF = 'staff';
    case REGISTRAR = 'registrar';
    case GUIDANCE = 'guidance';
    case LIBRARY = 'library';
    case IT = 'it';

    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function names(): array
    {
        return array_map(fn($case) => $case->name, self::cases());
    }

    public static function values(): array
    {
        return self::all();
    }
}
