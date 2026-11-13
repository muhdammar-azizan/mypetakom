<?php

namespace App\Http\Controllers;

use App\Models\Membership;
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
            default => view('dashboard.default'),
        };
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
            'pending' => Membership::with('user')->where('status', Membership::PENDING)->latest()->limit(5)->get(),
        ]);
    }
}
