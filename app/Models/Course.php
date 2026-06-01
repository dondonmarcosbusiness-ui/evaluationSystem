<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Course extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'department', 'subjects', 'sections'];

    public function academic_subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function academic_sections()
    {
        return $this->hasMany(Section::class);
    }
}
