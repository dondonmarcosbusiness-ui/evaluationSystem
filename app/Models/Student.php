<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Student extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'course',
        'section',
        'section_id',
        'student_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id', 'user_id');
    }

    public function section_relationship()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
