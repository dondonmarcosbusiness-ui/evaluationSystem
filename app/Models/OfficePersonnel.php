<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OfficePersonnel extends Model
{
    use HasUuids;
    protected $table = 'office_personnel';

    protected $fillable = [
        'office_id',
        'user_id',
        'position',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
