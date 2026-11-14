<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', ['sections' => $this->sections()]);
    }

    /** Download one report as a CSV file that opens in Excel. */
    public function csv(string $slug): StreamedResponse
    {
        $section = collect($this->sections())->first(fn ($s) => Str::slug($s['title']) === $slug);
        abort_unless($section, 404);

        return response()->streamDownload(function () use ($section) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // UTF-8 byte order mark, so Excel reads names correctly
            fputcsv($out, array_values($section['columns']));

            foreach ($section['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => self::safeCell($row->$key ?? ''), array_keys($section['columns'])));
            }

            fclose($out);
        }, Str::slug($section['title']).'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stop spreadsheet formulas: a cell starting with = + - @ is stored as plain text. */
    private static function safeCell(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }

    /**
     * Each report is a titled SQL query. Later modules append their own.
     *
     * @return list<array{module: string, title: string, description: string, sql: string, columns: array<string,string>, rows: array}>
     */
    private function sections(): array
    {
        return [
            $this->report(
                'Module 1 - Membership',
                'Memberships by status',
                'Single table: number of membership applications in each status.',
                'SELECT status, COUNT(*) AS total FROM memberships GROUP BY status ORDER BY total DESC',
                ['status' => 'Status', 'total' => 'Total'],
            ),
            $this->report(
                'Module 1 - Membership',
                'Approved members per programme',
                'Join (memberships + users): approved members grouped by programme.',
                "SELECT u.programme, COUNT(m.id) AS total FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.status = 'approved' GROUP BY u.programme ORDER BY total DESC",
                ['programme' => 'Programme', 'total' => 'Approved members'],
            ),
            $this->report(
                'Module 2 - Events',
                'Events by level',
                'Single table: number of events at each level.',
                'SELECT level, COUNT(*) AS total FROM events GROUP BY level ORDER BY total DESC',
                ['level' => 'Level', 'total' => 'Events'],
            ),
            $this->report(
                'Module 2 - Events',
                'Committee size per event',
                'Join (events + users + event_committees): each event with its advisor and number of committee members.',
                'SELECT e.title, u.name AS advisor, e.status, COUNT(c.id) AS committee FROM events e JOIN users u ON u.id = e.advisor_id LEFT JOIN event_committees c ON c.event_id = e.id GROUP BY e.id, e.title, u.name, e.status ORDER BY committee DESC',
                ['title' => 'Event', 'advisor' => 'Advisor', 'status' => 'Status', 'committee' => 'Committee members'],
            ),
            $this->report(
                'Module 2 - Merit applications',
                'Merit applications by status',
                'Single table: number of merit applications in each status.',
                'SELECT status, COUNT(*) AS total FROM merit_applications GROUP BY status ORDER BY total DESC',
                ['status' => 'Status', 'total' => 'Applications'],
            ),
            $this->report(
                'Module 2 - Merit applications',
                'Merit applications per advisor',
                'Join (merit_applications + events + users): applications and how many were approved, per advisor.',
                "SELECT u.name AS advisor, COUNT(a.id) AS applications, SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved FROM merit_applications a JOIN events e ON e.id = a.event_id JOIN users u ON u.id = e.advisor_id GROUP BY u.id, u.name ORDER BY applications DESC",
                ['advisor' => 'Advisor', 'applications' => 'Applications', 'approved' => 'Approved'],
            ),
            $this->report(
                'Module 3 - Attendance',
                'Check-ins per attendance slot',
                'Single table: number of students who checked in to each slot.',
                'SELECT attendance_slot_id AS slot_id, COUNT(*) AS total, ROUND(AVG(distance_m)) AS avg_distance FROM attendances GROUP BY attendance_slot_id ORDER BY total DESC',
                ['slot_id' => 'Slot #', 'total' => 'Check-ins', 'avg_distance' => 'Avg. distance (m)'],
            ),
            $this->report(
                'Module 3 - Attendance',
                'Attendance per event',
                'Join (events + attendance_slots + attendances): slots, check-ins and unique students for each event.',
                'SELECT e.title, COUNT(DISTINCT s.id) AS slots, COUNT(a.id) AS check_ins, COUNT(DISTINCT a.user_id) AS students FROM events e JOIN attendance_slots s ON s.event_id = e.id LEFT JOIN attendances a ON a.attendance_slot_id = s.id GROUP BY e.id, e.title ORDER BY check_ins DESC',
                ['title' => 'Event', 'slots' => 'Slots', 'check_ins' => 'Check-ins', 'students' => 'Unique students'],
            ),
            $this->report(
                'Module 4 - Merit',
                'Merit awarded per semester',
                'Single table: merit rows and total points for each academic year and semester.',
                'SELECT academic_year, semester, COUNT(*) AS entries, SUM(points) AS points FROM merits GROUP BY academic_year, semester ORDER BY academic_year DESC, semester DESC',
                ['academic_year' => 'Academic year', 'semester' => 'Semester', 'entries' => 'Entries', 'points' => 'Total points'],
            ),
            $this->report(
                'Module 4 - Merit',
                'Top students by merit',
                'Join (merits + users): the ten students with the most merit points.',
                'SELECT u.name, u.student_id, COUNT(m.id) AS events, SUM(m.points) AS points FROM merits m JOIN users u ON u.id = m.user_id GROUP BY u.id, u.name, u.student_id ORDER BY points DESC LIMIT 10',
                ['name' => 'Student', 'student_id' => 'Student ID', 'events' => 'Events', 'points' => 'Total points'],
            ),
            $this->report(
                'Module 4 - Merit claims',
                'Merit claims by status',
                'Single table: number of merit claims in each status.',
                'SELECT status, COUNT(*) AS total FROM merit_claims GROUP BY status ORDER BY total DESC',
                ['status' => 'Status', 'total' => 'Claims'],
            ),
            $this->report(
                'Module 4 - Merit claims',
                'Merit claims per advisor',
                'Join (merit_claims + events + users): claims received and approved for each event advisor.',
                "SELECT u.name AS advisor, COUNT(c.id) AS claims, SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END) AS approved FROM merit_claims c JOIN events e ON e.id = c.event_id JOIN users u ON u.id = e.advisor_id GROUP BY u.id, u.name ORDER BY claims DESC",
                ['advisor' => 'Advisor', 'claims' => 'Claims', 'approved' => 'Approved'],
            ),
        ];
    }

    private function report(string $module, string $title, string $description, string $sql, array $columns): array
    {
        return compact('module', 'title', 'description', 'sql', 'columns') + ['rows' => DB::select($sql)];
    }
}
