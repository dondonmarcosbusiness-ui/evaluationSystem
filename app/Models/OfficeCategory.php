<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OfficeCategory extends Model
{
    use HasUuids;
    protected $table = 'office_categories';

    protected $fillable = [
        'category_name',
        'weight',
    ];

    public function questions()
    {
        return $this->hasMany(OfficeQuestion::class, 'category_id');
    }
}
