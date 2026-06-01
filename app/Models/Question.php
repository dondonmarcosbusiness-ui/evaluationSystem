<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Question extends Model
{
    use HasUuids;
    protected $table = 'evaluation_questions';

    protected $fillable = [
        'category_id',
        'question_text',
        'question_text_tl',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
