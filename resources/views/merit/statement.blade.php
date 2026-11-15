<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Merit Statement - {{ $student->name }}</title>
    <style>
        @page { margin: 28mm 16mm 22mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #26251f; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0; color: #153680; letter-spacing: 0.5px; }
        h2 { font-size: 11.5px; margin: 18px 0 6px; color: #153680; border-bottom: 1px solid #c9d3ea; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #eef2fa; color: #3b4a73; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; padding: 5px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e8e8ec; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .muted { color: #6b6a64; }
        .head td { border: 0; padding: 0; vertical-align: middle; }
        .total { background: #153680; color: #fff; padding: 10px 14px; }
        .total .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #c9d3ea; }
        .total .value { font-size: 26px; font-weight: bold; }
        .info td { border: 0; padding: 2px 0; }
        .info td.k { width: 28%; color: #6b6a64; }
        .verify { margin-top: 22px; border: 1px solid #c9d3ea; padding: 10px; page-break-inside: avoid; }
        .verify td { border: 0; vertical-align: middle; }
        .small { font-size: 8.5px; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8px; color: #6b6a64; border-top: 1px solid #e8e8ec; padding-top: 4px; }
        .footer .page:after { content: "Page " counter(page); }
    </style>
</head>
<body>

    <div class="footer">
        <table style="width: 100%;"><tr>
            <td style="border: 0; padding: 0;">{{ $student->name }} ({{ $student->student_id }}) &middot; {{ $reference }}</td>
            <td style="border: 0; padding: 0; text-align: right;"><span class="page"></span></td>
        </tr></table>
    </div>

    <table class="head">
        <tr>
            <td style="width: 58px;">
                @if ($logo)<img src="{{ $logo }}" alt="" style="width: 50px; height: 50px;">@endif
            </td>
            <td>
                <h1>MERIT STATEMENT</h1>
                <div class="muted">Persatuan Petakom, Faculty of Computing, UMPSA</div>
            </td>
            <td style="text-align: right;" class="small muted">
                Reference<br>
                <strong style="color: #26251f;">{{ $reference }}</strong><br>
                Generated {{ $generatedAt->format('d M Y, h:i A') }}
            </td>
        </tr>
    </table>

    <h2>Student</h2>
    <table class="info">
        <tr><td class="k">Name</td><td><strong>{{ $student->name }}</strong></td></tr>
        <tr><td class="k">Student ID</td><td>{{ $student->student_id }}</td></tr>
        @if ($student->programme)<tr><td class="k">Programme</td><td>{{ $student->programme }}</td></tr>@endif
        <tr><td class="k">Email</td><td>{{ $student->email }}</td></tr>
        <tr><td class="k">Petakom membership</td><td>{{ $membershipLabel }}</td></tr>
    </table>

    <div class="total" style="margin-top: 14px;">
        <table style="width: 100%;">
            <tr>
                <td style="border: 0; padding: 0;">
                    <div class="label">Total merit</div>
                    <div class="value">{{ $total }}</div>
                </td>
                <td style="border: 0; padding: 0; text-align: right;" class="small">
                    {{ $merits->count() }} {{ \Illuminate\Support\Str::plural('event', $merits->count()) }}<br>
                    {{ $semesters->count() }} {{ \Illuminate\Support\Str::plural('semester', $semesters->count()) }}
                </td>
            </tr>
        </table>
    </div>

    <h2>Merit per academic year</h2>
    <table>
        <thead><tr><th>Academic year</th><th class="num">Events</th><th class="num">Points</th></tr></thead>
        <tbody>
            @forelse ($years as $y)
                <tr><td>{{ $y['academic_year'] }}</td><td class="num">{{ $y['events'] }}</td><td class="num"><strong>{{ $y['points'] }}</strong></td></tr>
            @empty
                <tr><td colspan="3" class="muted">No merit recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Cumulative merit per semester</h2>
    <table>
        <thead><tr><th>Academic year</th><th>Semester</th><th class="num">Events</th><th class="num">Points</th><th class="num">Cumulative</th></tr></thead>
        <tbody>
            @forelse ($semesters as $s)
                <tr>
                    <td>{{ $s['academic_year'] }}</td><td>Semester {{ $s['semester'] }}</td>
                    <td class="num">{{ $s['events'] }}</td><td class="num">{{ $s['points'] }}</td><td class="num"><strong>{{ $s['cumulative'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No merit recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Merit record</h2>
    <table>
        <thead><tr><th style="width: 13%;">Date</th><th style="width: 33%;">Event</th><th style="width: 13%;">Level</th><th style="width: 16%;">Role</th><th style="width: 16%;">Source</th><th class="num" style="width: 9%;">Points</th></tr></thead>
        <tbody>
            @forelse ($merits as $m)
                <tr>
                    <td>{{ $m->event->starts_at->format('d M Y') }}</td>
                    <td>{{ $m->event->title }}<div class="muted small">{{ $m->academic_year }} Semester {{ $m->semester }}</div></td>
                    <td>{{ $m->event->level_label }}</td>
                    <td>{{ $m->role_label }}</td>
                    <td>{{ $sourceLabels[$m->source] ?? $m->source }}</td>
                    <td class="num">{{ $m->points }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Nothing here yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="verify">
        <table>
            <tr>
                <td style="width: 90px;"><img src="{{ $qrImage }}" alt="Verification QR code" style="width: 80px; height: 80px;"></td>
                <td class="small">
                    <strong>Verify this statement</strong><br>
                    Scan the QR code to open the live merit page of this student in MyPetakom and compare the total.<br>
                    <span class="muted">{{ $verifyUrl }}</span><br><br>
                    <span class="muted">This statement was generated by MyPetakom on {{ $generatedAt->format('d M Y, h:i A') }}. It shows the merit recorded at that time.</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
