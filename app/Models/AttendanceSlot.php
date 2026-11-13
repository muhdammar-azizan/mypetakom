<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttendanceSlot extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'title', 'opens_at', 'closes_at', 'radius_m'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (AttendanceSlot $slot) {
            $slot->qr_token ??= Str::random(40);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** upcoming | open | closed, based on the current time. */
    public function getWindowAttribute(): string
    {
        return match (true) {
            now()->lt($this->opens_at) => 'upcoming',
            now()->gt($this->closes_at) => 'closed',
            default => 'open',
        };
    }

    public function getWindowColorAttribute(): string
    {
        return ['upcoming' => 'gold', 'open' => 'green', 'closed' => 'gray'][$this->window];
    }

    public function getCheckinUrlAttribute(): string
    {
        return route('checkin.show', $this->qr_token);
    }
}
