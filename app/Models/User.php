<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'student_id',
        'staff_id',
        'phone',
    ];

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADVISOR = 'advisor';
    public const ROLE_COORDINATOR = 'coordinator';

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->qr_token ??= Str::random(40);
        });
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ADVISOR => 'Event Advisor',
            self::ROLE_COORDINATOR => 'Petakom Coordinator',
            default => 'Student',
        };
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isAdvisor(): bool
    {
        return $this->role === self::ROLE_ADVISOR;
    }

    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
}
