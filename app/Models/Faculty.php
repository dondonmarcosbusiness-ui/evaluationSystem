<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Faculty extends Model
{
    use HasUuids;
    protected $table = 'faculty';

    protected $fillable = [
        'user_id',
        'department',
        'course',
        'position',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluatee_id');
    }

    public function assignments()
    {
        return $this->hasMany(FacultyAssignment::class);
    }
}
