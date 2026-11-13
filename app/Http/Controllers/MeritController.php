<?php

namespace App\Http\Controllers;

use App\Models\Merit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MeritController extends Controller
{
    /** The logged-in student's merit: cumulative per semester and every entry. */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('merit.index', ['user' => $user] + self::summary($user));
    }

    /** Public page behind the student QR code: identity and merit totals. */
    public function publicShow(string $token): View
    {
        $student = User::where('role', User::ROLE_STUDENT)->where('qr_token', $token)->firstOrFail();

        return view('merit.public', ['student' => $student] + self::summary($student));
    }

    /**
     * @return array{merits: Collection, semesters: Collection, total: int}
     */
    public static function summary(User $student): array
    {
        $merits = Merit::with('event')->where('user_id', $student->id)->get()
            ->sortByDesc(fn ($m) => $m->event->starts_at)->values();

        $running = 0;
        $semesters = $merits->groupBy(fn ($m) => $m->academic_year.'|'.$m->semester)
            ->map(fn ($group, $key) => [
                'academic_year' => explode('|', $key)[0],
                'semester' => (int) explode('|', $key)[1],
                'points' => $group->sum('points'),
                'events' => $group->count(),
            ])
            ->sortBy(fn ($s) => $s['academic_year'].$s['semester'])
            ->values()
            ->map(function ($s) use (&$running) {
                $running += $s['points'];

                return $s + ['cumulative' => $running];
            });

        return ['merits' => $merits, 'semesters' => $semesters, 'total' => (int) $merits->sum('points')];
    }
}
