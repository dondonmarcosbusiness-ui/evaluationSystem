<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Section extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'course_id'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function students()
    {
        return $this->hasMany(User::class, 'section_id');
    }
}
