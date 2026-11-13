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
        ];
    }

    private function report(string $module, string $title, string $description, string $sql, array $columns): array
    {
        return compact('module', 'title', 'description', 'sql', 'columns') + ['rows' => DB::select($sql)];
    }
}
