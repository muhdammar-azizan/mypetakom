<?php

namespace App\Http\Controllers;

use App\Notifications\Notice;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Merit;
use App\Models\MeritClaim;
use App\Services\MeritService;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Support\DateFilter;

class MeritClaimController extends Controller
{
    /** Students see their own claims; advisors see submitted claims for their events. */
    public function index(Request $request): View
    {
        $user = $request->user();

        $base = MeritClaim::with('event', 'user')
            ->when($user->isStudent(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isAdvisor(), fn ($q) => $q->where('status', '!=', MeritClaim::DRAFT)
                ->whereHas('event', fn ($e) => $e->where('advisor_id', $user->id)));

        $counts = (clone $base)->get()->countBy('status');

        $term = '%'.$request->q.'%';

        $claims = (clone $base)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('event', fn ($e) => $e->where('title', 'like', $term))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('student_id', 'like', $term))));

        $claims = DateFilter::apply($claims, $request, 'created_at')->latest()->paginate(10)->withQueryString();

        return view('merit-claims.index', ['claims' => $claims, 'counts' => $counts]);
    }

    public function create(Request $request): View
    {
        return view('merit-claims.form', [
            'claim' => new MeritClaim(['event_id' => $request->query('event'), 'role' => 'participant']),
            'events' => $this->claimableEvents($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);

        if ($error = $this->eligibilityError($request, (int) $data['event_id'])) {
            return back()->withErrors(['event_id' => $error])->withInput();
        }

        $claim = MeritClaim::create([
            'user_id' => $request->user()->id,
            'event_id' => $data['event_id'],
            'role' => $data['role'],
            'reason' => $data['reason'] ?? null,
            'letter_path' => $request->file('letter')->store('merit-claim-letters'),
            'status' => MeritClaim::DRAFT,
        ]);

        return redirect()->route('merit-claims.show', $claim)->with('toast', 'Claim saved as a draft. Submit it when you are ready.');
    }

    public function show(MeritClaim $claim): View
    {
        Gate::authorize('view', $claim);

        return view('merit-claims.show', ['claim' => $claim->load('event.advisor', 'user', 'reviewer')]);
    }

    public function edit(Request $request, MeritClaim $claim): View
    {
        Gate::authorize('change', $claim);

        return view('merit-claims.form', ['claim' => $claim, 'events' => collect([$claim->event])]);
    }

    public function update(Request $request, MeritClaim $claim): RedirectResponse
    {
        Gate::authorize('change', $claim);

        $data = $this->validated($request, false);
        $update = ['role' => $data['role'], 'reason' => $data['reason'] ?? null];

        if ($request->hasFile('letter')) {
            Storage::delete($claim->letter_path);
            $update['letter_path'] = $request->file('letter')->store('merit-claim-letters');
        }

        $claim->update($update);

        return redirect()->route('merit-claims.show', $claim)->with('toast', 'Claim updated.');
    }

    public function destroy(MeritClaim $claim): RedirectResponse
    {
        Gate::authorize('change', $claim);

        Storage::delete($claim->letter_path);
        $claim->delete();

        return redirect()->route('merit-claims.index')->with('toast', 'Claim deleted.');
    }

    /** Send a draft (or previously rejected) claim to the advisor. It is locked from now on. */
    public function submit(MeritClaim $claim): RedirectResponse
    {
        Gate::authorize('change', $claim);

        $claim->update(['status' => MeritClaim::SUBMITTED, 'remarks' => null, 'reviewed_by' => null, 'reviewed_at' => null]);

        Notice::send($claim->event->advisor, 'New merit claim', $claim->user->name.' claimed merit for "'.$claim->event->title.'".', route('merit-claims.show', $claim));

        return redirect()->route('merit-claims.show', $claim)->with('toast', 'Claim submitted to the event advisor.');
    }

    public function approve(Request $request, MeritClaim $claim): RedirectResponse
    {
        Gate::authorize('review', $claim);

        $claim->update(['status' => MeritClaim::APPROVED, 'remarks' => null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        MeritService::awardClaim($claim->load('event'));

        Activity::log('merit_claim.approved', 'Approved the merit claim of '.$claim->user->name.' for "'.$claim->event->title.'" ('.$claim->points.' points).', $claim);
        Notice::send($claim->user, 'Merit claim approved', 'Merit for "'.$claim->event->title.'" was added to your account.', route('merit-claims.show', $claim));

        return redirect()->route('merit-claims.show', $claim)->with('toast', 'Claim approved. Merit has been awarded.');
    }

    public function reject(Request $request, MeritClaim $claim): RedirectResponse
    {
        Gate::authorize('review', $claim);

        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);

        $claim->update(['status' => MeritClaim::REJECTED, 'remarks' => $data['remarks'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        Activity::log('merit_claim.rejected', 'Rejected the merit claim of '.$claim->user->name.' for "'.$claim->event->title.'": '.$data['remarks'], $claim);
        Notice::send($claim->user, 'Merit claim rejected', $data['remarks'], route('merit-claims.show', $claim));

        return redirect()->route('merit-claims.show', $claim)->with('toast', 'Claim rejected.');
    }

    public function letter(MeritClaim $claim): BinaryFileResponse
    {
        Gate::authorize('view', $claim);
        abort_unless(Storage::exists($claim->letter_path), 404);

        return response()->file(Storage::path($claim->letter_path));
    }

    /** Events the student could claim merit for: not cancelled, and no merit or claim yet. */
    private function claimableEvents(Request $request)
    {
        $user = $request->user();

        return Event::where('status', '!=', 'cancelled')
            ->whereNotIn('id', Merit::where('user_id', $user->id)->pluck('event_id'))
            ->whereNotIn('id', MeritClaim::where('user_id', $user->id)->pluck('event_id'))
            ->orderByDesc('starts_at')
            ->get();
    }

    private function eligibilityError(Request $request, int $eventId): ?string
    {
        $user = $request->user();
        $event = Event::findOrFail($eventId);

        return match (true) {
            $user->membership?->status !== Membership::APPROVED => 'Only approved Petakom members can claim merit.',
            $event->status === 'cancelled' => 'You cannot claim merit for a cancelled event.',
            Merit::where('user_id', $user->id)->where('event_id', $eventId)->exists() => 'You already have merit for this event.',
            MeritClaim::where('user_id', $user->id)->where('event_id', $eventId)->exists() => 'You already have a claim for this event.',
            default => null,
        };
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'event_id' => [$creating ? 'required' : 'prohibited', Rule::exists('events', 'id')],
            'role' => ['required', Rule::in(array_keys(Merit::ROLES))],
            'reason' => ['nullable', 'string', 'max:1000'],
            'letter' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);
    }
}
