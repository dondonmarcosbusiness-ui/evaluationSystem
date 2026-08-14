<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Office extends Model
{
    use HasUuids;
    protected $table = 'offices';

    protected $fillable = [
        'name',
        'description',
        'office_head',
        'location',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function personnel()
    {
        return $this->hasMany(OfficePersonnel::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(OfficeFeedback::class);
    }

    public function qrCode()
    {
        return $this->hasOne(QrCode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
