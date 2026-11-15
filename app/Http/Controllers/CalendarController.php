<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCommittee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Month calendar of events.
     * Coordinators see every event, advisors their own, students every event (and which ones they help organise).
     */
    public function index(Request $request): View
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $user = $request->user();
        $month = $request->filled('month')
            ? Carbon::createFromFormat('!Y-m', $request->month)
            : now()->startOfMonth();

        // Whole weeks, Monday to Sunday, so the grid has no gaps.
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = Event::query()
            ->when($user->isAdvisor(), fn ($q) => $q->where('advisor_id', $user->id))
            ->where('starts_at', '<=', $gridEnd)
            ->where('ends_at', '>=', $gridStart)
            ->orderBy('starts_at')
            ->get();

        // Students open the full event page only for events they are a committee member of.
        $committeeIds = $user->isStudent()
            ? EventCommittee::where('user_id', $user->id)->pluck('event_id')->all()
            : [];

        $urls = $events->mapWithKeys(fn (Event $e) => [
            $e->id => ($user->isStudent() && ! in_array($e->id, $committeeIds, true))
                ? route('events.public', $e->qr_token)
                : route('events.show', $e),
        ]);

        // Put each event on every day it covers (inside the grid).
        $byDay = [];
        foreach ($events as $event) {
            // Copies only: Carbon's max()/min() can hand back the very object that is passed in.
            $day = $event->starts_at->copy()->startOfDay();
            if ($day->lt($gridStart)) {
                $day = $gridStart->copy();
            }

            $last = $event->ends_at->copy()->startOfDay();
            if ($last->gt($gridEnd)) {
                $last = $gridEnd->copy()->startOfDay();
            }

            for (; $day->lte($last); $day->addDay()) {
                $byDay[$day->toDateString()][] = $event;
            }
        }

        $weeks = [];
        $position = 0;
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $weeks[intdiv($position++, 7)][] = [
                'date' => $day->copy(),
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isToday(),
                'events' => $byDay[$day->toDateString()] ?? [],
            ];
        }

        return view('calendar.index', [
            'month' => $month,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'weeks' => $weeks,
            // Events that touch the month itself, for the list shown on small screens.
            'agenda' => $events->filter(fn (Event $e) => $e->starts_at->lte($month->copy()->endOfMonth()) && $e->ends_at->gte($month)),
            'urls' => $urls,
            'committeeIds' => $committeeIds,
        ]);
    }
}
