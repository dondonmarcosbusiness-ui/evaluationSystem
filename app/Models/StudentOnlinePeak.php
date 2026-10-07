<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Daily high-water mark of concurrently online students.
 * Written by OnlinePresenceService (dashboard fetch + online-peak:sample).
 */
class StudentOnlinePeak extends Model
{
    use HasUuids;

    protected $table = 'student_online_peaks';

    protected $fillable = [
        'date',
        'peak',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'peak' => 'integer',
    ];
}
