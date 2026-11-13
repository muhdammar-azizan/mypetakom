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
        ];
    }

    private function report(string $module, string $title, string $description, string $sql, array $columns): array
    {
        return compact('module', 'title', 'description', 'sql', 'columns') + ['rows' => DB::select($sql)];
    }
}
