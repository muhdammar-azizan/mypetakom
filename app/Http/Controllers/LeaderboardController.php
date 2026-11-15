<?php

namespace App\Http\Controllers;

use App\Models\Merit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    /** How many students the table shows. A student ranked lower still sees their own rank above it. */
    private const TOP = 20;

    /**
     * Student ranking by merit, for all time or one academic year / semester.
     * A student who turned off "Show me on the leaderboard" keeps their rank but appears as "Anonymous student",
     * except to themselves and to the coordinator.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'year' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['nullable', 'integer', 'between:1,3'],
        ]);

        $viewer = $request->user();

        $rows = Merit::query()
            ->join('users', 'users.id', '=', 'merits.user_id')
            ->where('users.role', User::ROLE_STUDENT)
            ->when($request->filled('year'), fn ($q) => $q->where('merits.academic_year', $request->year))
            ->when($request->filled('semester'), fn ($q) => $q->where('merits.semester', $request->semester))
            ->groupBy('users.id', 'users.name', 'users.programme', 'users.show_on_leaderboard')
            ->selectRaw('users.id, users.name, users.programme, users.show_on_leaderboard, SUM(merits.points) as points, COUNT(*) as events')
            ->orderByDesc('points')
            ->orderBy('users.name')
            ->get();

        // Students with the same points share a rank (1, 2, 2, 4).
        $rank = 0;
        $previous = null;
        $board = $rows->values()->map(function ($row, $index) use (&$rank, &$previous, $viewer) {
            $points = (int) $row->points;
            if ($points !== $previous) {
                $rank = $index + 1;
                $previous = $points;
            }

            $mine = (int) $row->id === $viewer->id;
            $hidden = ! $row->show_on_leaderboard;
            $visible = ! $hidden || $mine || $viewer->isCoordinator();

            return [
                'rank' => $rank,
                'name' => $visible ? $row->name : 'Anonymous student',
                'initials' => $visible ? $this->initials($row->name) : '?',
                'programme' => $visible ? $row->programme : null,
                'points' => $points,
                'events' => (int) $row->events,
                'mine' => $mine,
                'hidden' => $hidden,
            ];
        });

        $mine = $board->firstWhere('mine', true);

        return view('leaderboard.index', [
            'top' => $board->take(self::TOP),
            'mine' => $mine,
            'mineInTop' => $mine !== null && $board->take(self::TOP)->contains(fn ($r) => $r['mine']),
            'total' => $board->count(),
            'maxPoints' => $board->first()['points'] ?? 0,
            'years' => Merit::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'semesters' => Merit::query()->distinct()->orderBy('semester')->pluck('semester'),
            'top_limit' => self::TOP,
        ]);
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->filter()->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    }
}
