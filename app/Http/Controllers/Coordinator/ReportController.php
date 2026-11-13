<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', ['sections' => $this->sections()]);
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
        ];
    }

    private function report(string $module, string $title, string $description, string $sql, array $columns): array
    {
        return compact('module', 'title', 'description', 'sql', 'columns') + ['rows' => DB::select($sql)];
    }
}
