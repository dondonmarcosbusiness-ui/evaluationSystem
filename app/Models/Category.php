<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;
    protected $table = 'evaluation_categories';

    protected $fillable = [
        'category_name',
        'category_name_tl',
        'weight',
        'academic_year',
        'semester',
        'evaluatee_type',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('evaluatee_type', $type);
    }

    public function scopeActive($query)
    {
        $settings = Setting::all()->pluck('value', 'key');
        $activeSemester = $settings->get('active_semester');
        $activeAcademicYear = $settings->get('active_academic_year');

        if ($activeSemester) {
            $query->where('semester', $activeSemester);
        }
        if ($activeAcademicYear) {
            $query->where('academic_year', $activeAcademicYear);
        }

        return $query;
    }
}
