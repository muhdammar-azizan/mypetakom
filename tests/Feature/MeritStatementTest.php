<?php

namespace Tests\Feature;

use App\Http\Controllers\MeritController;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Merit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeritStatementTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithMerit(array $userAttrs = []): User
    {
        $student = User::factory()->create($userAttrs + ['name' => 'Nur Aisyah', 'student_id' => 'CB11111', 'programme' => 'BCS (Software Engineering)']);
        Membership::factory()->approved()->for($student)->create();

        $a = Event::factory()->create(['title' => 'Hackathon Alpha', 'level' => 'national']);
        $b = Event::factory()->create(['title' => 'Workshop Beta', 'level' => 'umpsa']);
        Merit::create(['user_id' => $student->id, 'event_id' => $a->id, 'role' => 'committee', 'points' => 50, 'academic_year' => '2024/2025', 'semester' => 2, 'source' => 'committee']);
        Merit::create(['user_id' => $student->id, 'event_id' => $b->id, 'role' => 'participant', 'points' => 5, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);

        return $student;
    }

    private function html(User $student): string
    {
        return view('merit.statement', app(MeritController::class)->statementData($student))->render();
    }

    // ---- The download ----

    public function test_student_downloads_a_real_pdf_named_after_them(): void
    {
        $student = $this->studentWithMerit();

        $response = $this->actingAs($student)->get('/merit/statement')->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('merit-statement-CB11111-'.now()->format('Ymd').'.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    public function test_student_without_merit_still_gets_a_statement(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/merit/statement')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('No merit recorded yet.', $this->html($student));
        $this->assertStringContainsString('Not a member', $this->html($student));
    }

    public function test_only_students_can_download_and_guests_are_sent_to_login(): void
    {
        $this->get('/merit/statement')->assertRedirect('/login');
        $this->actingAs(User::factory()->advisor()->create())->get('/merit/statement')->assertForbidden();
        $this->actingAs(User::factory()->coordinator()->create())->get('/merit/statement')->assertForbidden();
    }

    public function test_the_merit_page_offers_the_download(): void
    {
        $this->actingAs($this->studentWithMerit())->get('/merit')->assertSee('Download statement (PDF)')->assertSee(route('merit.statement'), false);
    }

    // ---- What the statement says ----

    public function test_statement_lists_student_totals_years_semesters_and_events(): void
    {
        $student = $this->studentWithMerit();
        $html = $this->html($student);

        foreach (['Nur Aisyah', 'CB11111', 'BCS (Software Engineering)', 'Approved member', 'Hackathon Alpha', 'Workshop Beta', '2024/2025', '2025/2026', 'Semester 2', 'National', 'Committee', 'Attendance'] as $text) {
            $this->assertStringContainsString($text, $html, "missing: {$text}");
        }

        // Total 55; cumulative per semester 50 then 55.
        $this->assertMatchesRegularExpression('/class="value">55</', $html);
        $this->assertMatchesRegularExpression('/<strong>50<\/strong>/', $html);
        $this->assertMatchesRegularExpression('/<strong>55<\/strong>/', $html);
    }

    public function test_statement_only_contains_the_students_own_merit(): void
    {
        $mine = $this->studentWithMerit();
        $other = User::factory()->create(['name' => 'Someone Else']);
        $event = Event::factory()->create(['title' => 'Private Other Event']);
        Merit::create(['user_id' => $other->id, 'event_id' => $event->id, 'role' => 'participant', 'points' => 99, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);

        $html = $this->html($mine);

        $this->assertStringNotContainsString('Private Other Event', $html);
        $this->assertStringNotContainsString('Someone Else', $html);
    }

    public function test_statement_escapes_names_and_event_titles(): void
    {
        $student = $this->studentWithMerit(['name' => '<script>alert(1)</script> Hacker']);

        $html = $this->html($student);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_statement_has_logo_qr_and_verification_link_to_the_public_merit_page(): void
    {
        $student = $this->studentWithMerit();
        $html = $this->html($student);

        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);   // QR
        $this->assertStringContainsString('data:image/png;base64,', $html);       // logo
        $this->assertStringContainsString(route('merit.qr', $student->qr_token), $html);
    }

    // ---- Reference number ----

    public function test_reference_has_a_stable_format_and_changes_when_the_total_changes(): void
    {
        $student = $this->studentWithMerit();
        $controller = app(MeritController::class);

        $first = $controller->statementData($student)['reference'];
        $this->assertMatchesRegularExpression('/^MS-CB11111-'.now()->format('Ymd').'-[A-F0-9]{6}$/', $first);
        $this->assertSame($first, $controller->statementData($student)['reference']);

        Merit::create(['user_id' => $student->id, 'event_id' => Event::factory()->create()->id, 'role' => 'participant', 'points' => 30, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);
        $this->assertNotSame($first, $controller->statementData($student->fresh())['reference']);
    }

    public function test_membership_wording_follows_the_status(): void
    {
        $controller = app(MeritController::class);

        $pending = User::factory()->create();
        Membership::factory()->for($pending)->create();
        $this->assertSame('Application pending', $controller->statementData($pending)['membershipLabel']);

        $rejected = User::factory()->create();
        Membership::factory()->rejected()->for($rejected)->create();
        $this->assertSame('Application rejected', $controller->statementData($rejected)['membershipLabel']);
    }

    public function test_a_long_record_still_produces_a_pdf(): void
    {
        $student = $this->studentWithMerit();
        foreach (range(1, 40) as $i) {
            Merit::create(['user_id' => $student->id, 'event_id' => Event::factory()->create(['title' => "Extra Event {$i}"])->id, 'role' => 'participant', 'points' => 5, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);
        }

        $response = $this->actingAs($student)->get('/merit/statement')->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()));
    }
}
