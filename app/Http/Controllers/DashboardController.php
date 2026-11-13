<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventCommittee;
use App\Models\Membership;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return match ($request->user()->role) {
            User::ROLE_COORDINATOR => $this->coordinator(),
            User::ROLE_ADVISOR => $this->advisor($request->user()),
            User::ROLE_STUDENT => $this->student($request->user()),
            default => view('dashboard.default'),
        };
    }

    private function student(User $student): View
    {
        $summary = MeritController::summary($student);
        $merits = $summary['merits'];
        $semesters = $summary['semesters'];

        // Points by event level
        $byLevel = $merits->groupBy(fn ($m) => $m->event->level)->map->sum('points');
        $levelLabels = array_values(Event::LEVELS);
        $levelValues = array_map(fn ($key) => (int) ($byLevel[$key] ?? 0), array_keys(Event::LEVELS));

        $claimCounts = MeritClaim::where('user_id', $student->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dashboard.student', [
            'membership' => $student->membership,
            'tiles' => [
                ['Total merit', $summary['total'], 'award'],
                ['Events with merit', $merits->count(), 'calendar'],
                ['Event check-ins', Attendance::where('user_id', $student->id)->count(), 'check'],
                ['Committee roles', EventCommittee::where('user_id', $student->id)->count(), 'users'],
            ],
            'semesterLabels' => $semesters->map(fn ($s) => $s['academic_year'].' S'.$s['semester'])->all(),
            'semesterValues' => $semesters->pluck('cumulative')->all(),
            'levelLabels' => $levelLabels,
            'levelValues' => $levelValues,
            'upcoming' => EventCommittee::with('event')->where('user_id', $student->id)
                ->whereHas('event', fn ($q) => $q->whereIn('status', ['active', 'postponed'])->where('starts_at', '>=', now()))
                ->get()->sortBy(fn ($c) => $c->event->starts_at)->take(4),
            'recentMerits' => $merits->take(5),
            'claimCounts' => [
                'draft' => $claimCounts[MeritClaim::DRAFT] ?? 0,
                'submitted' => $claimCounts[MeritClaim::SUBMITTED] ?? 0,
                'approved' => $claimCounts[MeritClaim::APPROVED] ?? 0,
                'rejected' => $claimCounts[MeritClaim::REJECTED] ?? 0,
            ],
        ]);
    }

    private function advisor(User $advisor): View
    {
        $events = Event::where('advisor_id', $advisor->id);

        $byLevel = (clone $events)->selectRaw('level, COUNT(*) as total')->groupBy('level')->pluck('total', 'level');
        $levelLabels = array_values(Event::LEVELS);
        $levelValues = array_map(fn ($key) => $byLevel[$key] ?? 0, array_keys(Event::LEVELS));

        $committeeSizes = (clone $events)->withCount('committees')->orderByDesc('committees_count')->limit(6)->get();

        $applicationCounts = MeritApplication::whereHas('event', fn ($q) => $q->where('advisor_id', $advisor->id))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dashboard.advisor', [
            'tiles' => [
                ['My events', (clone $events)->count(), 'calendar'],
                ['Active events', (clone $events)->where('status', 'active')->count(), 'check'],
                ['Committee members', \App\Models\EventCommittee::whereHas('event', fn ($q) => $q->where('advisor_id', $advisor->id))->count(), 'users'],
                ['Merit pending', $applicationCounts[MeritApplication::PENDING] ?? 0, 'file'],
            ],
            'levelLabels' => $levelLabels,
            'levelValues' => $levelValues,
            'committeeLabels' => $committeeSizes->pluck('title')->all(),
            'committeeValues' => $committeeSizes->pluck('committees_count')->all(),
            'upcoming' => (clone $events)->whereIn('status', ['active', 'postponed'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'applicationCounts' => [
                'pending' => $applicationCounts[MeritApplication::PENDING] ?? 0,
                'approved' => $applicationCounts[MeritApplication::APPROVED] ?? 0,
                'rejected' => $applicationCounts[MeritApplication::REJECTED] ?? 0,
            ],
        ]);
    }

    private function coordinator(): View
    {
        $statusCounts = Membership::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        // Applications per month for the last 6 months (zero-filled).
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $byMonth = Membership::where('created_at', '>=', $months->first())->pluck('created_at')
            ->countBy(fn ($d) => $d->format('Y-m'));

        // Approved members per programme (join memberships + users).
        $perProgramme = DB::table('memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.status', Membership::APPROVED)
            ->selectRaw("COALESCE(u.programme, 'Not set') as programme, COUNT(m.id) as total")
            ->groupBy('u.programme')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('dashboard.coordinator', [
            'tiles' => [
                ['Students', User::where('role', User::ROLE_STUDENT)->count(), 'users'],
                ['Approved members', $statusCounts[Membership::APPROVED] ?? 0, 'check'],
                ['Pending applications', $statusCounts[Membership::PENDING] ?? 0, 'clipboard'],
                ['Event advisors', User::where('role', User::ROLE_ADVISOR)->count(), 'award'],
                ['Events', Event::count(), 'calendar'],
                ['Merit applications pending', MeritApplication::where('status', MeritApplication::PENDING)->count(), 'file'],
            ],
            'statusCounts' => [
                Membership::PENDING => $statusCounts[Membership::PENDING] ?? 0,
                Membership::APPROVED => $statusCounts[Membership::APPROVED] ?? 0,
                Membership::REJECTED => $statusCounts[Membership::REJECTED] ?? 0,
            ],
            'monthLabels' => $months->map(fn ($m) => $m->format('M Y'))->all(),
            'monthValues' => $months->map(fn ($m) => $byMonth[$m->format('Y-m')] ?? 0)->all(),
            'programmeLabels' => $perProgramme->pluck('programme')->all(),
            'programmeValues' => $perProgramme->pluck('total')->all(),
            'pendingMerit' => MeritApplication::with('event.advisor')->where('status', MeritApplication::PENDING)->latest()->limit(5)->get(),
            'pending' => Membership::with('user')->where('status', Membership::PENDING)->latest()->limit(5)->get(),
        ]);
    }
}
