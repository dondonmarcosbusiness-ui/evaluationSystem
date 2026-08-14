<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OfficeFeedbackAnswer extends Model
{
    use HasUuids;
    protected $table = 'office_feedback_answers';

    protected $fillable = [
        'office_feedback_id',
        'office_question_id',
        'answer',
    ];

    protected $casts = [
        'answer' => 'boolean',
    ];

    public function feedback()
    {
        return $this->belongsTo(OfficeFeedback::class, 'office_feedback_id');
    }

    public function question()
    {
        return $this->belongsTo(OfficeQuestion::class, 'office_question_id');
    }
}
