<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row in the audit trail. Rows are only ever added, never edited. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Filter groups shown on the log page: key is the action prefix. */
    public const GROUPS = [
        'membership' => 'Membership',
        'event' => 'Events',
        'attendance' => 'Attendance',
        'merit_application' => 'Merit applications',
        'merit_claim' => 'Merit claims',
        'user' => 'User accounts',
    ];

    protected $fillable = ['user_id', 'actor_name', 'actor_role', 'action', 'description', 'subject_type', 'subject_id', 'ip_address'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getGroupAttribute(): string
    {
        return explode('.', $this->action)[0];
    }

    public function getGroupLabelAttribute(): string
    {
        return self::GROUPS[$this->group] ?? ucfirst($this->group);
    }

    /** Words after the dot, e.g. "membership.approved" gives "Approved". */
    public function getActionLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', explode('.', $this->action, 2)[1] ?? $this->action));
    }

    /** Badge colour: green for approvals, red for rejections and deletions. */
    public function getColorAttribute(): string
    {
        return match (true) {
            str_ends_with($this->action, 'rejected'), str_ends_with($this->action, 'deleted'), str_ends_with($this->action, 'deactivated'), str_ends_with($this->action, 'removed'), str_ends_with($this->action, 'disabled'), str_ends_with($this->action, 'reset') => 'red',
            str_ends_with($this->action, 'approved'), str_ends_with($this->action, 'created'), str_ends_with($this->action, 'activated'), str_ends_with($this->action, 'added'), str_ends_with($this->action, 'enabled') => 'green',
            default => 'brand',
        };
    }
}
