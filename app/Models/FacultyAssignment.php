<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FacultyAssignment extends Model
{
    use HasUuids;
    protected $fillable = [
        'faculty_id',
        'subject_id',
        'section_id',
        'academic_year',
        'semester'
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
