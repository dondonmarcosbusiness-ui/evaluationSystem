<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OfficeFeedback extends Model
{
    use HasUuids;
    protected $table = 'office_feedback';

    protected $fillable = [
        'office_id',
        'student_id',
        'visitor_type',
        'gender',
        'visitor_name',
        'student_number',
        'contact_number',
        'purpose_of_visit',
        'comments',
        'ip_address',
        'user_agent',
        'device_id',
        'device_type',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers()
    {
        return $this->hasMany(OfficeFeedbackAnswer::class, 'office_feedback_id');
    }

    public function scopeForOffice($query, $officeId)
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('submitted_at', [$from, $to]);
    }
}
