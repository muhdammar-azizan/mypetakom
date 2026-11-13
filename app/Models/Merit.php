<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Merit extends Model
{
    public const ROLES = [
        'main_committee' => 'Main Committee',
        'committee' => 'Committee',
        'participant' => 'Participant',
    ];

    protected $fillable = ['user_id', 'event_id', 'role', 'points', 'academic_year', 'semester', 'source'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }
}
