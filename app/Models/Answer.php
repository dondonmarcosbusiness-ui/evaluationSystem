<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Answer extends Model
{
    use HasUuids;
    protected $table = 'evaluation_answers';

    protected $fillable = [
        'evaluation_id',
        'question_id',
        'rating',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
