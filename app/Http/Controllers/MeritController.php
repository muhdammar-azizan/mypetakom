<?php

namespace App\Http\Controllers;

use App\Models\Merit;
use App\Models\User;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /** The logged-in student's merit as a PDF statement. */
    public function statement(Request $request): Response
    {
        $student = $request->user();

        return Pdf::loadView('merit.statement', $this->statementData($student))
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans'])
            ->download('merit-statement-'.$student->student_id.'-'.now()->format('Ymd').'.pdf');
    }

    /** Everything the statement page needs. Kept apart from the PDF step so it can be checked as plain HTML. */
    public function statementData(User $student): array
    {
        $summary = self::summary($student);
        $now = now();
        $logoFile = public_path('images/logo.png');
        $verifyUrl = route('merit.qr', $student->qr_token);
        $status = $student->membership?->status;

        return $summary + [
            'student' => $student,
            'generatedAt' => $now,
            // Short code that changes when the total changes, so an old printout can be told apart from a new one.
            'reference' => 'MS-'.$student->student_id.'-'.$now->format('Ymd').'-'
                .strtoupper(substr(hash_hmac('sha256', $student->id.'|'.$summary['total'].'|'.$now->toDateString(), (string) config('app.key')), 0, 6)),
            'membershipLabel' => match ($status) {
                'approved' => 'Approved member',
                'pending' => 'Application pending',
                'rejected' => 'Application rejected',
                default => 'Not a member',
            },
            'sourceLabels' => ['committee' => 'Committee', 'attendance' => 'Attendance', 'claim' => 'Approved claim'],
            'logo' => is_file($logoFile) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoFile)) : null,
            'qrImage' => 'data:image/svg+xml;base64,'.base64_encode(Qr::svg($verifyUrl, 240)),
            'verifyUrl' => $verifyUrl,
        ];
    }

    /** Public page behind the student QR code: identity and merit totals. */
    public function publicShow(string $token): View
    {
        $student = User::where('role', User::ROLE_STUDENT)->where('qr_token', $token)->firstOrFail();

        return view('merit.public', ['student' => $student] + self::summary($student));
    }

    /**
     * @return array{merits: Collection, semesters: Collection, years: Collection, total: int}
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

        // Total merit for each academic year (the QR page and merit page both show it).
        $years = $semesters->groupBy('academic_year')
            ->map(fn ($rows, $year) => [
                'academic_year' => $year,
                'points' => $rows->sum('points'),
                'events' => $rows->sum('events'),
                'semesters' => $rows->count(),
            ])
            ->sortKeysDesc()
            ->values();

        return ['merits' => $merits, 'semesters' => $semesters, 'years' => $years, 'total' => (int) $merits->sum('points')];
    }
}
