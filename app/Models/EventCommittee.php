<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommittee extends Model
{
    public const POSITIONS = [
        'main_committee' => 'Main Committee',
        'committee' => 'Committee',
    ];

    protected $fillable = ['event_id', 'user_id', 'position', 'position_title'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPositionLabelAttribute(): string
    {
        return self::POSITIONS[$this->position] ?? ucfirst($this->position);
    }
}
