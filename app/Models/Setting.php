<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasUuids;
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array'
    ];

    /**
     * Cached key => value collection. Avoids a DB round-trip on every request.
     */
    public static function cachedAll(): \Illuminate\Support\Collection
    {
        return Cache::remember('settings.all', 3600, function () {
            return static::all()->pluck('value', 'key');
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget('settings.all');
    }
}
