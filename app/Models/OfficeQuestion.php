<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OfficeQuestion extends Model
{
    use HasUuids;
    protected $table = 'office_questions';

    protected $fillable = [
        'category_id',
        'question_text',
    ];

    public function category()
    {
        return $this->belongsTo(OfficeCategory::class, 'category_id');
    }
}
