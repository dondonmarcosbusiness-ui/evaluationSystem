<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class QrCode extends Model
{
    use HasUuids;
    protected $table = 'qr_codes';

    protected $fillable = [
        'office_id',
        'qr_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public static function generateToken(): string
    {
        return Str::random(32);
    }
}
