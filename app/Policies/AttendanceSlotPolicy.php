<?php

namespace App\Policies;

use App\Models\AttendanceSlot;
use App\Models\User;

class AttendanceSlotPolicy
{
    /** Coordinators see every slot; advisors only slots of their own events. */
    public function view(User $user, AttendanceSlot $slot): bool
    {
        return $user->isCoordinator() || $this->manage($user, $slot);
    }

    public function manage(User $user, AttendanceSlot $slot): bool
    {
        return $user->isAdvisor() && $slot->event->advisor_id === $user->id;
    }
}
