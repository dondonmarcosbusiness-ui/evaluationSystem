<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

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
                $fullname = $user->lastname ? $user->lastname.', ' : '';
                $fullname .= $user->firstname;
                if ($user->middlename) {
                    $fullname .= ' '.$user->middlename;
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
     * The email address that should be used for notifications.
     */
    public function getNotificationEmailAttribute()
    {
        return $this->google_email ?: $this->email;
    }

    /**
     * Route notification mail to the linked Google address when present.
     * Without this override Laravel addresses notifications to the plain
     * `email` column, which diverges from the window-notice job and can be
     * empty for Google-registered accounts.
     */
    public function routeNotificationForMail($notification): string
    {
        return (string) ($this->notification_email ?? '');
    }
}
