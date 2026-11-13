<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'student_card_path', 'status', 'remarks', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->student_card_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            default => 'gold',
        };
    }
}
