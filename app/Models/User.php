<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_notify_status',
        'fcm_token',
        'role_id',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_copy',
        'remember_token',
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
        ];
    }

    /** Set the password (hashed for the login) and keep an encrypted copy the managers can show */
    public function setPasswordWithCopy(string $plain): void
    {
        $this->password = \Illuminate\Support\Facades\Hash::make($plain);
        $this->password_copy = \Illuminate\Support\Facades\Crypt::encryptString($plain);
    }

    /** The password in clear, or null when it was set before copies were kept */
    public function passwordCopy(): ?string
    {
        if (!$this->password_copy) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($this->password_copy);
        } catch (\Throwable) {
            return null; // encrypted with another APP_KEY
        }
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function individual()
    {
        return $this->hasOne(Individual::class);
    }

    // Helper methods to check user type through individual
    public function isPlayer(): bool
    {
        return $this->individual && $this->individual->type === 'player';
    }

    public function isCoach(): bool
    {
        return $this->individual && $this->individual->type === 'coach';
    }

    public function isEmployee(): bool
    {
        return $this->individual && $this->individual->type === 'employee';
    }
}
