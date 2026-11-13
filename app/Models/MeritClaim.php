<?php

namespace App\Models;

use App\Services\MeritService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeritClaim extends Model
{
    use HasFactory;

    /** pending = draft the student can still change; submitted = waiting for the advisor. */
    public const DRAFT = 'pending';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'event_id', 'role', 'reason', 'letter_path', 'status', 'remarks', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The student can edit or delete a claim only while it is not Submitted (or already approved). */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::SUBMITTED, self::APPROVED], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === self::DRAFT ? 'Draft' : ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::SUBMITTED => 'gold',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            default => 'gray',
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return Merit::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function getPointsAttribute(): int
    {
        return MeritService::points($this->event->level, $this->role);
    }
}
