<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Enrollment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'subject_id',
        'instructor_id',
        'semester',
        'academic_year',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Faculty::class, 'instructor_id');
    }
}
