<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * One row per authentication attempt (password or Google).
 * Read via LoginLogController (permission.manage only).
 */
class LoginLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'login_logs';

    protected $fillable = [
        'user_id',
        'login_identifier',
        'status',
        'reason',
        'driver',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
