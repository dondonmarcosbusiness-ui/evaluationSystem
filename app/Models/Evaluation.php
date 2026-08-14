<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Evaluation extends Model
{
    use HasUuids;
    protected $fillable = [
        'student_id',
        'faculty_id',
        'semester',
        'academic_year',
        'subject_code',
        'year_section',
        'comments',
        'submitted_at',
        'ai_analysis',
        'evaluatee_type',
        'evaluatee_id',
    ];

    protected $casts = [
        'ai_analysis' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('evaluatee_type', $type);
    }

    /**
     * Match faculty evaluations by evaluatee_id or legacy faculty_id column.
     */
    public function scopeForFacultyMember($query, string $facultyId)
    {
        return $query->where(function ($q) use ($facultyId) {
            $q->where('faculty_id', $facultyId)
                ->orWhere(function ($q2) use ($facultyId) {
                    $q2->where('evaluatee_type', 'faculty')
                        ->where('evaluatee_id', $facultyId);
                });
        });
    }

    public function getEvaluatee()
    {
        return match ($this->evaluatee_type) {
            'faculty' => Faculty::find($this->evaluatee_id) ?? Faculty::find($this->faculty_id),
            default => null,
        };
    }
}
