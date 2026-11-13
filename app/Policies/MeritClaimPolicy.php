<?php

namespace App\Policies;

use App\Models\MeritClaim;
use App\Models\User;

class MeritClaimPolicy
{
    /** Owner; the advisor of the event (once submitted); or a coordinator. */
    public function view(User $user, MeritClaim $claim): bool
    {
        return $claim->user_id === $user->id
            || $user->isCoordinator()
            || ($this->advisorOf($user, $claim) && $claim->status !== MeritClaim::DRAFT);
    }

    /** Edit, delete and submit: owner only, and never while Submitted or Approved. */
    public function change(User $user, MeritClaim $claim): bool
    {
        return $claim->user_id === $user->id && ! $claim->isLocked();
    }

    /** Only the advisor of the event, and only for submitted claims. */
    public function review(User $user, MeritClaim $claim): bool
    {
        return $this->advisorOf($user, $claim) && $claim->status === MeritClaim::SUBMITTED;
    }

    private function advisorOf(User $user, MeritClaim $claim): bool
    {
        return $user->isAdvisor() && $claim->event->advisor_id === $user->id;
    }
}
