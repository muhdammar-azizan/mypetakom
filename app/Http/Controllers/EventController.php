<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCommittee;
use App\Models\Membership;
use App\Models\User;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventController extends Controller
{
    /** Advisors see their own events; coordinators see all events. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $events = Event::with('advisor')->withCount('committees')
            ->when($user->isAdvisor(), fn ($q) => $q->where('advisor_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->level))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$request->q.'%')
                ->orWhere('venue', 'like', '%'.$request->q.'%')))
            ->orderByDesc('starts_at')
            ->paginate(10)
            ->withQueryString();

        return view('events.index', ['events' => $events]);
    }

    public function create(): View
    {
        return view('events.form', ['event' => new Event([
            'level' => 'umpsa',
            'status' => 'active',
            'academic_year' => Event::academicYearFor(),
            'semester' => 1,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['approval_letter_path'] = $request->file('approval_letter')->store('approval-letters');
        unset($data['approval_letter']);

        $event = Event::create($data + ['advisor_id' => $request->user()->id, 'status' => 'active']);

        return redirect()->route('events.show', $event)->with('toast', 'Event created.');
    }

    public function show(Event $event): View
    {
        Gate::authorize('view', $event);

        $event->load('advisor', 'committees.user', 'meritApplication');

        return view('events.show', [
            'event' => $event,
            'qrSvg' => Qr::svg($event->public_url, 400),
            'canManage' => Gate::allows('manage', $event),
        ]);
    }

    public function edit(Event $event): View
    {
        Gate::authorize('manage', $event);

        return view('events.form', ['event' => $event]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        $data = $this->validated($request, false);

        if ($request->hasFile('approval_letter')) {
            Storage::delete($event->approval_letter_path);
            $data['approval_letter_path'] = $request->file('approval_letter')->store('approval-letters');
        }
        unset($data['approval_letter']);

        $event->update($data);

        return redirect()->route('events.show', $event)->with('toast', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        Storage::delete($event->approval_letter_path);
        $event->delete();

        return redirect()->route('events.index')->with('toast', 'Event deleted.');
    }

    /** Approval letter, visible to the owning advisor and coordinators. */
    public function letter(Request $request, Event $event): BinaryFileResponse
    {
        abort_unless($request->user()->isCoordinator() || Gate::allows('manage', $event), 403);
        abort_unless(Storage::exists($event->approval_letter_path), 404);

        return response()->file(Storage::path($event->approval_letter_path));
    }

    public function addCommittee(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        $data = $request->validate([
            'student_id' => ['required', 'string'],
            'position' => ['required', Rule::in(array_keys(EventCommittee::POSITIONS))],
            'position_title' => ['nullable', 'string', 'max:100'],
        ]);

        $student = User::where('role', User::ROLE_STUDENT)->where('student_id', strtoupper($data['student_id']))->first();

        if (! $student) {
            return back()->withErrors(['student_id' => 'No student found with that ID.'])->withInput();
        }

        if ($student->membership?->status !== Membership::APPROVED) {
            return back()->withErrors(['student_id' => 'Only approved Petakom members can join a committee.'])->withInput();
        }

        if ($event->committees()->where('user_id', $student->id)->exists()) {
            return back()->withErrors(['student_id' => 'This student is already in the committee.'])->withInput();
        }

        $event->committees()->create([
            'user_id' => $student->id,
            'position' => $data['position'],
            'position_title' => $data['position_title'] ?? null,
        ]);

        return back()->with('toast', 'Committee member added.');
    }

    public function removeCommittee(Event $event, EventCommittee $member): RedirectResponse
    {
        Gate::authorize('manage', $event);
        abort_unless($member->event_id === $event->id, 404);

        $member->delete();

        return back()->with('toast', 'Committee member removed.');
    }

    /** Events a student is a committee member of. */
    public function mine(Request $request): View
    {
        $committees = EventCommittee::with('event.advisor')
            ->where('user_id', $request->user()->id)
            ->get()
            ->sortByDesc(fn ($c) => $c->event->starts_at);

        return view('events.mine', ['committees' => $committees]);
    }

    /** Public event information page that the event QR code points to. */
    public function publicShow(string $token): View
    {
        return view('events.public', ['event' => Event::with('advisor')->where('qr_token', $token)->firstOrFail()]);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(Event::LEVELS))],
            'venue' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'integer', 'between:1,3'],
            'status' => [$creating ? 'prohibited' : 'required', Rule::in(array_keys(Event::STATUSES))],
            'approval_letter' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);
    }
}
