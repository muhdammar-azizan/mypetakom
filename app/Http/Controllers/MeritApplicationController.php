<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MeritApplication;
use App\Services\MeritService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MeritApplicationController extends Controller
{
    /** Advisors see applications for their own events; coordinators see all. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $applications = MeritApplication::with('event.advisor')
            ->whereHas('event', fn ($q) => $user->isAdvisor() ? $q->where('advisor_id', $user->id) : $q)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('merit-applications.index', [
            'applications' => $applications,
            'counts' => MeritApplication::whereHas('event', fn ($q) => $user->isAdvisor() ? $q->where('advisor_id', $user->id) : $q)
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Request $request, MeritApplication $application): View
    {
        $event = $application->event;
        abort_unless($request->user()->isCoordinator() || Gate::allows('manage', $event), 403);

        $event->load('advisor', 'committees.user');
        $scores = config('merit.scores.'.$event->level);

        return view('merit-applications.show', [
            'application' => $application->load('reviewer'),
            'event' => $event,
            'scores' => $scores,
        ]);
    }

    /** Advisor applies for merit on an event, or applies again after a rejection. */
    public function store(Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        if ($event->status === 'cancelled') {
            return back()->with('toast', 'A cancelled event cannot apply for merit.');
        }

        if ($event->committees()->doesntExist()) {
            return back()->with('toast', 'Add at least one committee member before applying for merit.');
        }

        $existing = $event->meritApplication;

        if ($existing && $existing->status !== MeritApplication::REJECTED) {
            return back()->with('toast', 'A merit application already exists for this event.');
        }

        MeritApplication::updateOrCreate(['event_id' => $event->id], [
            'status' => MeritApplication::PENDING,
            'remarks' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with('toast', 'Merit application submitted.');
    }

    /** Withdraw an application that is still pending. */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        $application = $event->meritApplication;
        abort_unless($application && $application->status === MeritApplication::PENDING, 403);

        $application->delete();

        return back()->with('toast', 'Merit application withdrawn.');
    }

    public function approve(Request $request, MeritApplication $application): RedirectResponse
    {
        $application->update([
            'status' => MeritApplication::APPROVED,
            'remarks' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        MeritService::sync($application->event);

        return redirect()->route('merit-applications.show', $application)->with('toast', 'Merit application approved. Merit awarded to the committee and participants.');
    }

    public function reject(Request $request, MeritApplication $application): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);

        $application->update([
            'status' => MeritApplication::REJECTED,
            'remarks' => $data['remarks'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        MeritService::sync($application->event);

        return redirect()->route('merit-applications.show', $application)->with('toast', 'Merit application rejected.');
    }
}
