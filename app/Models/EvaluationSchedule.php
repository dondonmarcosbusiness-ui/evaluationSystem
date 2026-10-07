<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Evaluation window for a department (or the institution-wide default when
 * `department` is NULL).
 *
 * Gating is resolved at request time by EvaluationScheduleService — the row
 * itself never stores an "is open" flag, so no scheduler is required for
 * correctness. `status` is:
 *   scheduled — open while now is within [starts_at, ends_at] (null = open bound)
 *   open      — manual override, force open regardless of the dates
 *   closed    — manual override, force closed regardless of the dates
 */
class EvaluationSchedule extends Model
{
    use HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
    ];

    protected $table = 'evaluation_schedules';

    protected $fillable = [
        'department',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** The institution-wide default row. */
    public function scopeGlobal($query)
    {
        return $query->whereNull('department');
    }

    /** The row that governs the given department (NULL = default row). */
    public function scopeForDepartment($query, ?string $department)
    {
        return $department === null
            ? $query->whereNull('department')
            : $query->where('department', $department);
    }

    public function isGlobal(): bool
    {
        return $this->department === null;
    }
}
