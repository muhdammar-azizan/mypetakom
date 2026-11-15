<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Throwable;

class User extends Authenticatable implements MustVerifyEmail
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
        'programme',
        'emergency_name',
        'emergency_phone',
        'address',
        'alt_email',
        'avatar_path',
        'last_login_at',
        'deactivated_at',
        'notify_in_app',
        'notify_email',
        'show_on_leaderboard',
    ];

    /** Same defaults as the database, so a user created in this request already has them. */
    protected $attributes = [
        'notify_in_app' => true,
        'notify_email' => true,
        'show_on_leaderboard' => true,
    ];

    /**
     * A deactivated account gets no reset link. The caller shows the same message either way,
     * so nobody can tell from the page whether an account exists or is deactivated.
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->isDeactivated()) {
            return;
        }

        $this->notify(new ResetPassword($token));
    }

    /**
     * Mail trouble must not break signing up or saving a profile: report it and carry on.
     * The user can ask for the link again from the banner.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            parent::sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }
    }

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

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }

    /** The personal ID shown on the profile: student ID or staff ID. */
    public function getIdLabelAttribute(): string
    {
        return $this->isStudent() ? 'Student ID' : 'Staff ID';
    }

    public function getIdValueAttribute(): ?string
    {
        return $this->isStudent() ? $this->student_id : $this->staff_id;
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function merits(): HasMany
    {
        return $this->hasMany(Merit::class);
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
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
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'notify_in_app' => 'boolean',
            'notify_email' => 'boolean',
            'show_on_leaderboard' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
