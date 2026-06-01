<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Staff extends Model
{
    use HasUuids;
    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'department',
        'designation',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluatee_id');
    }
}
