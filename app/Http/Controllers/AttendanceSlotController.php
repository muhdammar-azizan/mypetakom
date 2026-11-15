<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Services\MeritService;
use App\Support\Qr;
use App\Support\RotatingCode;
use Illuminate\Http\JsonResponse;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceSlotController extends Controller
{
    /** Advisors list their own slots; coordinators get the attendance dashboard. */
    public function index(Request $request): View
    {
        if ($request->user()->isCoordinator()) {
            return $this->dashboard();
        }

        $slots = AttendanceSlot::with('event')->withCount('attendances')
            ->whereHas('event', fn ($q) => $q->where('advisor_id', $request->user()->id))
            ->latest('opens_at')
            ->paginate(10);

        return view('attendance.index', ['slots' => $slots]);
    }

    public function create(Request $request): View
    {
        return view('attendance.form', [
            'slot' => new AttendanceSlot(['event_id' => $request->query('event'), 'radius_m' => 100]),
            'events' => $this->ownEvents($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slot = AttendanceSlot::create($this->validated($request));

        Activity::log('attendance.created', 'Created the attendance slot "'.$slot->title.'" for "'.$slot->event->title.'".', $slot);

        return redirect()->route('attendance.show', $slot)->with('toast', 'Attendance slot created.');
    }

    public function show(AttendanceSlot $slot): View
    {
        Gate::authorize('view', $slot);

        $slot->load('event.advisor', 'attendances.user');

        return view('attendance.show', [
            'slot' => $slot,
            'qrSvg' => Qr::svg($slot->scan_url, 400),
            'canManage' => Gate::allows('manage', $slot),
        ]);
    }

    /** Fresh QR for a rotating slot. The slot page asks for it each time the code changes. */
    public function qr(AttendanceSlot $slot): JsonResponse
    {
        Gate::authorize('view', $slot);

        return response()->json([
            'svg' => Qr::svg($slot->scan_url, 400),
            'url' => $slot->scan_url,
            'refresh_in' => RotatingCode::secondsLeft(),
        ])->header('Cache-Control', 'no-store');
    }

    public function edit(Request $request, AttendanceSlot $slot): View
    {
        Gate::authorize('manage', $slot);

        return view('attendance.form', ['slot' => $slot, 'events' => $this->ownEvents($request)]);
    }

    public function update(Request $request, AttendanceSlot $slot): RedirectResponse
    {
        Gate::authorize('manage', $slot);

        $slot->update($this->validated($request));

        return redirect()->route('attendance.show', $slot)->with('toast', 'Attendance slot updated.');
    }

    public function destroy(AttendanceSlot $slot): RedirectResponse
    {
        Gate::authorize('manage', $slot);

        Activity::log('attendance.deleted', 'Deleted the attendance slot "'.$slot->title.'" of "'.$slot->event->title.'".', $slot);
        $slot->delete();

        return redirect()->route('attendance.index')->with('toast', 'Attendance slot deleted.');
    }

    /** Remove one check-in record (e.g. a wrong or duplicate entry). */
    public function destroyRecord(AttendanceSlot $slot, Attendance $record): RedirectResponse
    {
        Gate::authorize('manage', $slot);
        abort_unless($record->attendance_slot_id === $slot->id, 404);

        Activity::log('attendance.removed', 'Removed the check-in of '.$record->user->name.' from "'.$slot->title.'" ('.$slot->event->title.').', $slot);
        $record->delete();
        MeritService::sync($slot->event);

        return back()->with('toast', 'Check-in removed.');
    }

    private function ownEvents(Request $request)
    {
        return Event::where('advisor_id', $request->user()->id)->where('status', '!=', 'cancelled')->orderByDesc('starts_at')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'event_id' => ['required', Rule::exists('events', 'id')->where('advisor_id', $request->user()->id)],
            'title' => ['required', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'radius_m' => ['required', 'integer', 'between:20,1000'],
        ]);

        $data['rotating_qr'] = $request->boolean('rotating_qr');

        return $data;
    }

    /** Module 3 dashboard for the coordinator. */
    private function dashboard(): View
    {
        $days = collect(range(13, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));
        $byDay = Attendance::where('checked_in_at', '>=', $days->first())->pluck('checked_in_at')
            ->countBy(fn ($d) => $d->format('Y-m-d'));

        $perEvent = DB::table('attendances as a')
            ->join('attendance_slots as s', 's.id', '=', 'a.attendance_slot_id')
            ->join('events as e', 'e.id', '=', 's.event_id')
            ->selectRaw('e.title, COUNT(a.id) as total')
            ->groupBy('e.id', 'e.title')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('attendance.dashboard', [
            'tiles' => [
                ['Attendance slots', AttendanceSlot::count(), 'clipboard'],
                ['Total check-ins', Attendance::count(), 'check'],
                ['Unique students', Attendance::distinct('user_id')->count('user_id'), 'users'],
                ['Slots open now', AttendanceSlot::where('opens_at', '<=', now())->where('closes_at', '>=', now())->count(), 'calendar'],
            ],
            'dayLabels' => $days->map(fn ($d) => $d->format('d M'))->all(),
            'dayValues' => $days->map(fn ($d) => $byDay[$d->format('Y-m-d')] ?? 0)->all(),
            'eventLabels' => $perEvent->pluck('title')->all(),
            'eventValues' => $perEvent->pluck('total')->all(),
            'recent' => Attendance::with('user', 'slot.event')->latest('checked_in_at')->limit(8)->get(),
            'slots' => AttendanceSlot::with('event.advisor')->withCount('attendances')->latest('opens_at')->paginate(10),
        ]);
    }
}
