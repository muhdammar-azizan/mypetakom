<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Search, date filters and CSV exports on the list pages. */
class ListToolsTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::factory()->coordinator()->create();
    }

    // ---- Shared CSV helper ----

    public function test_csv_helper_neutralises_formulas_and_keeps_normal_text(): void
    {
        $this->assertSame("'=SUM(A1)", Csv::safeCell('=SUM(A1)'));
        $this->assertSame("'+1", Csv::safeCell('+1'));
        $this->assertSame("'-1", Csv::safeCell('-1'));
        $this->assertSame("'@cmd", Csv::safeCell('@cmd'));
        $this->assertSame('Aisyah', Csv::safeCell('Aisyah'));
        $this->assertSame('', Csv::safeCell(null));
        $this->assertSame('0', Csv::safeCell(0));
    }

    // ---- Members ----

    public function test_members_can_be_filtered_by_application_date(): void
    {
        $old = User::factory()->create(['name' => 'Old Applicant']);
        $new = User::factory()->create(['name' => 'New Applicant']);
        Membership::factory()->for($old)->create(['created_at' => now()->subMonths(3)]);
        Membership::factory()->for($new)->create(['created_at' => now()]);
        $coordinator = $this->coordinator();

        $from = now()->subWeek()->toDateString();
        $this->actingAs($coordinator)->get("/members?from={$from}")->assertSee('New Applicant')->assertDontSee('Old Applicant');
        $to = now()->subMonths(2)->toDateString();
        $this->actingAs($coordinator)->get("/members?to={$to}")->assertSee('Old Applicant')->assertDontSee('New Applicant');
        $this->actingAs($coordinator)->get('/members?from=2026-05-02&to=2026-05-01')->assertSessionHasErrors('to');
    }

    public function test_members_csv_follows_the_filters_and_is_safe(): void
    {
        $approved = User::factory()->create(['name' => '=EVIL()', 'student_id' => 'CB10001', 'programme' => 'BCS']);
        $pending = User::factory()->create(['name' => 'Pending Person']);
        Membership::factory()->approved()->for($approved)->create();
        Membership::factory()->for($pending)->create();

        $response = $this->actingAs($this->coordinator())->get('/members/export?status=approved');

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}Name,\"Student ID\",Programme,Email,Status", $csv);
        $this->assertStringContainsString("'=EVIL()", $csv);
        $this->assertStringContainsString('CB10001', $csv);
        $this->assertStringNotContainsString('Pending Person', $csv);
    }

    public function test_members_export_is_coordinator_only(): void
    {
        $this->get('/members/export')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/members/export')->assertForbidden();
        $this->actingAs(User::factory()->advisor()->create())->get('/members/export')->assertForbidden();
    }

    // ---- Events ----

    public function test_events_can_be_filtered_by_date_and_exported(): void
    {
        $advisor = User::factory()->advisor()->create();
        Event::factory()->create(['advisor_id' => $advisor->id, 'title' => 'Past Gathering', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonths(2)->addHour()]);
        Event::factory()->create(['advisor_id' => $advisor->id, 'title' => 'Coming Gathering', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHour()]);

        $from = now()->toDateString();
        $this->actingAs($advisor)->get("/events?from={$from}")->assertSee('Coming Gathering')->assertDontSee('Past Gathering');

        $csv = $this->actingAs($advisor)->get("/events/export?from={$from}")->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('Coming Gathering', $csv);
        $this->assertStringNotContainsString('Past Gathering', $csv);
        $this->assertStringStartsWith("\u{FEFF}Title,Level,Venue,Starts,Ends", $csv);
    }

    public function test_an_advisor_only_exports_their_own_events_but_coordinator_gets_all(): void
    {
        $mine = Event::factory()->create(['title' => 'Mine Event']);
        Event::factory()->create(['title' => 'Someone Elses Event']);

        $csv = $this->actingAs($mine->advisor)->get('/events/export')->streamedContent();
        $this->assertStringContainsString('Mine Event', $csv);
        $this->assertStringNotContainsString('Someone Elses Event', $csv);

        $all = $this->actingAs($this->coordinator())->get('/events/export')->streamedContent();
        $this->assertStringContainsString('Mine Event', $all);
        $this->assertStringContainsString('Someone Elses Event', $all);

        $this->actingAs(User::factory()->create())->get('/events/export')->assertForbidden();
    }

    // ---- Merit claims ----

    public function test_claims_can_be_searched_by_event_and_student_and_filtered_by_date(): void
    {
        $event = Event::factory()->create(['title' => 'Hackathon Alpha']);
        $other = Event::factory()->create(['title' => 'Quiet Seminar', 'advisor_id' => $event->advisor_id]);
        $a = User::factory()->create(['name' => 'Siti Claimer', 'student_id' => 'CB20001']);
        $b = User::factory()->create(['name' => 'Budi Claimer', 'student_id' => 'CB20002']);
        MeritClaim::factory()->status('submitted')->create(['event_id' => $event->id, 'user_id' => $a->id, 'created_at' => now()]);
        MeritClaim::factory()->status('submitted')->create(['event_id' => $other->id, 'user_id' => $b->id, 'created_at' => now()->subMonths(3)]);
        $advisor = $event->advisor;

        $this->actingAs($advisor)->get('/merit-claims?q=Hackathon')->assertSee('Siti Claimer')->assertDontSee('Budi Claimer');
        $this->actingAs($advisor)->get('/merit-claims?q=CB20002')->assertSee('Budi Claimer')->assertDontSee('Siti Claimer');
        $from = now()->subWeek()->toDateString();
        $this->actingAs($advisor)->get("/merit-claims?from={$from}")->assertSee('Siti Claimer')->assertDontSee('Budi Claimer');
    }

    public function test_a_student_search_still_only_shows_their_own_claims(): void
    {
        $mine = MeritClaim::factory()->create();
        $theirs = MeritClaim::factory()->create();

        $this->actingAs($mine->user)->get('/merit-claims?q='.urlencode($theirs->event->title))->assertDontSee($theirs->user->name);
    }

    // ---- Merit applications ----

    public function test_applications_can_be_searched_by_event_or_advisor_and_filtered_by_date(): void
    {
        $e1 = Event::factory()->create(['title' => 'Robotics Day']);
        $e2 = Event::factory()->create(['title' => 'Debate Night']);
        MeritApplication::create(['event_id' => $e1->id, 'status' => 'pending']);
        $old = MeritApplication::create(['event_id' => $e2->id, 'status' => 'pending']);
        $old->forceFill(['created_at' => now()->subMonths(4)])->save();
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->get('/merit-applications?q=Robotics')->assertSee('Robotics Day')->assertDontSee('Debate Night');
        $this->actingAs($coordinator)->get('/merit-applications?q='.urlencode($e2->advisor->name))->assertSee('Debate Night');
        $from = now()->subWeek()->toDateString();
        $this->actingAs($coordinator)->get("/merit-applications?from={$from}")->assertSee('Robotics Day')->assertDontSee('Debate Night');
    }

    // ---- Attendance ----

    public function test_advisor_can_search_and_date_filter_their_slots(): void
    {
        $event = Event::factory()->create(['title' => 'Orientation']);
        AttendanceSlot::factory()->for($event)->create(['title' => 'Morning Roll', 'opens_at' => now()->addDays(2), 'closes_at' => now()->addDays(2)->addHour()]);
        AttendanceSlot::factory()->for($event)->create(['title' => 'Ancient Roll', 'opens_at' => now()->subMonths(5), 'closes_at' => now()->subMonths(5)->addHour()]);
        $advisor = $event->advisor;

        $this->actingAs($advisor)->get('/attendance?q=Morning')->assertSee('Morning Roll')->assertDontSee('Ancient Roll');
        $this->actingAs($advisor)->get('/attendance?q=Orientation')->assertSee('Morning Roll')->assertSee('Ancient Roll');
        $from = now()->toDateString();
        $this->actingAs($advisor)->get("/attendance?from={$from}")->assertSee('Morning Roll')->assertDontSee('Ancient Roll');
    }

    public function test_slot_check_ins_can_be_exported(): void
    {
        $slot = AttendanceSlot::factory()->create(['title' => 'Main Gate']);
        $student = User::factory()->create(['name' => 'Checked In Student', 'student_id' => 'CB30001']);
        Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $student->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 12, 'checked_in_at' => now()]);

        $response = $this->actingAs($slot->event->advisor)->get("/attendance/{$slot->id}/csv")->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CB30001', $csv);
        $this->assertStringContainsString('Checked In Student', $csv);
        $this->assertStringStartsWith("\u{FEFF}\"Student ID\",Name,Programme", $csv);

        $this->actingAs($this->coordinator())->get("/attendance/{$slot->id}/csv")->assertOk();
    }

    public function test_slot_csv_is_not_open_to_other_advisors_or_students(): void
    {
        $slot = AttendanceSlot::factory()->create();

        $this->actingAs(User::factory()->advisor()->create())->get("/attendance/{$slot->id}/csv")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/attendance/{$slot->id}/csv")->assertForbidden();
    }

    public function test_slot_csv_needs_login(): void
    {
        $slot = AttendanceSlot::factory()->create();

        $this->get("/attendance/{$slot->id}/csv")->assertRedirect('/login');
    }

    public function test_export_buttons_are_on_the_pages(): void
    {
        $slot = AttendanceSlot::factory()->create();

        $this->actingAs($this->coordinator())->get('/members')->assertSee('Export CSV')->assertSee('aria-label="From date"', false);
        $this->actingAs($slot->event->advisor)->get('/events')->assertSee('Export CSV');
        $this->actingAs($slot->event->advisor)->get("/attendance/{$slot->id}")->assertSee('Export CSV');
    }
}
