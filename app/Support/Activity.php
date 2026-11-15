<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Writes one line to the audit trail: who did what, to what, and when. */
class Activity
{
    /**
     * @param  string  $action  "group.verb", for example "membership.approved"
     * @param  Model|null  $subject  the record the action was done to
     */
    public static function log(string $action, string $description, ?Model $subject = null): void
    {
        $user = auth()->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name,
            'actor_role' => $user?->role,
            'action' => $action,
            'description' => mb_substr($description, 0, 500),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()->ip(),
        ]);
    }
}
