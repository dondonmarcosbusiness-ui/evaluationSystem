<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Faculty;
use App\Models\Evaluation;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'firstname',
        'lastname',
        'middlename',
        'email',
        'id_number',
        'google_id',
        'google_email',
        'is_google_linked',
        'password',
        'role',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            if ($user->firstname || $user->lastname) {
                $fullname = $user->lastname ? $user->lastname . ', ' : '';
                $fullname .= $user->firstname;
                if ($user->middlename) {
                    $fullname .= ' ' . $user->middlename;
                }
                $user->name = trim($fullname);
            }
        });
    }

    public function student()
    {
        return $this->hasOne(\App\Models\Student::class);
    }

    public function faculty()
    {
        return $this->hasOne(Faculty::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'student_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_google_linked' => 'boolean',
        ];
    }

    /**
     * Get the email address that should be used for notifications.
     */
    public function getNotificationEmailAttribute()
    {
        return $this->google_email ?: $this->email;
    }
}
