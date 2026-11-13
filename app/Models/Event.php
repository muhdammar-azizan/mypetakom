<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    public const LEVELS = [
        'international' => 'International',
        'national' => 'National',
        'state' => 'State',
        'district' => 'District',
        'umpsa' => 'UMPSA',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'postponed' => 'Postponed',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
    ];

    protected $fillable = [
        'advisor_id', 'title', 'description', 'level', 'venue', 'latitude', 'longitude',
        'starts_at', 'ends_at', 'academic_year', 'semester', 'status', 'approval_letter_path',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->qr_token ??= Str::random(40);
        });
    }

    /** Academic year the given date falls in (the year starts in September), e.g. "2025/2026". */
    public static function academicYearFor(?\DateTimeInterface $date = null): string
    {
        $date = $date ? \Illuminate\Support\Carbon::instance($date) : now();
        $start = $date->month >= 9 ? $date->year : $date->year - 1;

        return $start.'/'.($start + 1);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function committees(): HasMany
    {
        return $this->hasMany(EventCommittee::class);
    }

    public function meritApplication(): HasOne
    {
        return $this->hasOne(MeritApplication::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? ucfirst($this->level);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'postponed' => 'gold',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /** Public page that the event QR code points to. */
    public function getPublicUrlAttribute(): string
    {
        return route('events.public', $this->qr_token);
    }

    public function getMapUrlAttribute(): string
    {
        return "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map=17/{$this->latitude}/{$this->longitude}";
    }
}
