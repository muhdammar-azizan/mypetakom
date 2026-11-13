<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Coordinators see everything, advisors their own events, students events they are a committee member of. */
    public function view(User $user, Event $event): bool
    {
        return match (true) {
            $user->isCoordinator() => true,
            $user->isAdvisor() => $event->advisor_id === $user->id,
            default => $event->committees()->where('user_id', $user->id)->exists(),
        };
    }

    /** Only the advisor who owns the event can change it. */
    public function manage(User $user, Event $event): bool
    {
        return $user->isAdvisor() && $event->advisor_id === $user->id;
    }
}
