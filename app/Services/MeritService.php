<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Merit;
use App\Models\MeritClaim;

class MeritService
{
    public static function points(string $level, string $role): int
    {
        return (int) config("merit.scores.{$level}.{$role}", 0);
    }

    /**
     * Make the merit rows of an event match its current state. Safe to call any time:
     * when the merit application is approved (and the event not cancelled) every committee
     * member and every student who checked in holds merit; otherwise those rows are removed.
     * Merit granted through an approved claim is never touched.
     */
    public static function sync(Event $event): void
    {
        $event->load('meritApplication');

        $desired = collect();

        if ($event->meritApplication?->status === 'approved' && $event->status !== 'cancelled') {
            foreach ($event->committees()->get() as $c) {
                $desired[$c->user_id] = ['role' => $c->position, 'source' => 'committee'];
            }

            Attendance::whereHas('slot', fn ($q) => $q->where('event_id', $event->id))
                ->pluck('user_id')->unique()
                ->each(function ($userId) use ($desired) {
                    if (! $desired->has($userId)) {
                        $desired[$userId] = ['role' => 'participant', 'source' => 'attendance'];
                    }
                });
        }

        Merit::where('event_id', $event->id)->whereIn('source', ['committee', 'attendance'])
            ->whereNotIn('user_id', $desired->keys())->delete();

        $claimed = Merit::where('event_id', $event->id)->where('source', 'claim')->pluck('user_id');

        foreach ($desired as $userId => $d) {
            if ($claimed->contains($userId)) {
                continue;
            }

            Merit::updateOrCreate(['user_id' => $userId, 'event_id' => $event->id], [
                'role' => $d['role'],
                'points' => self::points($event->level, $d['role']),
                'academic_year' => $event->academic_year,
                'semester' => $event->semester,
                'source' => $d['source'],
            ]);
        }
    }

    /** Grant merit for an approved claim. */
    public static function awardClaim(MeritClaim $claim): Merit
    {
        $event = $claim->event;

        return Merit::updateOrCreate(['user_id' => $claim->user_id, 'event_id' => $event->id], [
            'role' => $claim->role,
            'points' => self::points($event->level, $claim->role),
            'academic_year' => $event->academic_year,
            'semester' => $event->semester,
            'source' => 'claim',
        ]);
    }
}
