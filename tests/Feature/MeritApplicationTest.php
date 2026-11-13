<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MeritApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeritApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithCommittee(User $advisor, array $attrs = []): Event
    {
        $event = Event::factory()->for($advisor, 'advisor')->create($attrs);
        $event->committees()->create(['user_id' => User::factory()->create()->id, 'position' => 'main_committee']);

        return $event;
    }

    public function test_advisor_can_apply_for_merit(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = $this->eventWithCommittee($advisor);

        $this->actingAs($advisor)->post("/events/{$event->id}/merit-application")->assertSessionHas('toast', 'Merit application submitted.');

        $this->assertSame('pending', $event->refresh()->meritApplication->status);
    }

    public function test_cannot_apply_without_committee_or_for_a_cancelled_event(): void
    {
        $advisor = User::factory()->advisor()->create();
        $empty = Event::factory()->for($advisor, 'advisor')->create();
        $cancelled = $this->eventWithCommittee($advisor, ['status' => 'cancelled']);

        $this->actingAs($advisor)->post("/events/{$empty->id}/merit-application");
        $this->actingAs($advisor)->post("/events/{$cancelled->id}/merit-application");

        $this->assertSame(0, MeritApplication::count());
    }

    public function test_cannot_apply_twice_but_can_reapply_after_rejection(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = $this->eventWithCommittee($advisor);
        $this->actingAs($advisor)->post("/events/{$event->id}/merit-application");
        $this->actingAs($advisor)->post("/events/{$event->id}/merit-application");
        $this->assertSame(1, MeritApplication::count());

        $event->meritApplication->update(['status' => 'rejected', 'remarks' => 'Missing letter']);
        $this->actingAs($advisor)->post("/events/{$event->id}/merit-application");

        $application = $event->meritApplication()->first();
        $this->assertSame('pending', $application->status);
        $this->assertNull($application->remarks);
        $this->assertSame(1, MeritApplication::count());
    }

    public function test_pending_application_can_be_withdrawn_but_not_an_approved_one(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = $this->eventWithCommittee($advisor);
        $app = MeritApplication::create(['event_id' => $event->id]);

        $this->actingAs($advisor)->delete("/events/{$event->id}/merit-application");
        $this->assertNull($app->fresh());

        $app = MeritApplication::create(['event_id' => $event->id, 'status' => 'approved']);
        $this->actingAs($advisor)->delete("/events/{$event->id}/merit-application")->assertForbidden();
        $this->assertNotNull($app->fresh());
    }

    public function test_another_advisor_cannot_apply_for_someone_elses_event(): void
    {
        $event = $this->eventWithCommittee(User::factory()->advisor()->create());

        $this->actingAs(User::factory()->advisor()->create())->post("/events/{$event->id}/merit-application")->assertForbidden();
    }

    public function test_coordinator_can_approve_and_reject_with_reason(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $a = MeritApplication::create(['event_id' => $this->eventWithCommittee(User::factory()->advisor()->create())->id]);
        $b = MeritApplication::create(['event_id' => $this->eventWithCommittee(User::factory()->advisor()->create())->id]);

        $this->actingAs($coordinator)->post("/merit-applications/{$a->id}/approve")->assertRedirect();
        $this->assertSame('approved', $a->refresh()->status);
        $this->assertSame($coordinator->id, $a->reviewed_by);

        $this->actingAs($coordinator)->post("/merit-applications/{$b->id}/reject", [])->assertSessionHasErrors('remarks');
        $this->actingAs($coordinator)->post("/merit-applications/{$b->id}/reject", ['remarks' => 'No letter'])->assertRedirect();
        $this->assertSame('rejected', $b->refresh()->status);
    }

    public function test_advisor_and_student_cannot_decide(): void
    {
        $advisor = User::factory()->advisor()->create();
        $app = MeritApplication::create(['event_id' => $this->eventWithCommittee($advisor)->id]);

        $this->actingAs($advisor)->post("/merit-applications/{$app->id}/approve")->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/merit-applications/{$app->id}/approve")->assertForbidden();
        $this->assertSame('pending', $app->refresh()->status);
    }

    public function test_listing_is_scoped_per_advisor(): void
    {
        $advisor = User::factory()->advisor()->create();
        $mine = $this->eventWithCommittee($advisor, ['title' => 'Mine Event']);
        $other = $this->eventWithCommittee(User::factory()->advisor()->create(), ['title' => 'Other Event']);
        MeritApplication::create(['event_id' => $mine->id]);
        $theirs = MeritApplication::create(['event_id' => $other->id]);

        $this->actingAs($advisor)->get('/merit-applications')->assertSee('Mine Event')->assertDontSee('Other Event');
        $this->actingAs($advisor)->get("/merit-applications/{$theirs->id}")->assertForbidden();
        $this->actingAs(User::factory()->coordinator()->create())->get('/merit-applications')->assertSee('Mine Event')->assertSee('Other Event');
        $this->actingAs(User::factory()->create())->get('/merit-applications')->assertForbidden();
    }

    public function test_review_page_shows_points_for_the_event_level(): void
    {
        $event = $this->eventWithCommittee(User::factory()->advisor()->create(), ['level' => 'international']);
        $app = MeritApplication::create(['event_id' => $event->id]);

        $this->actingAs(User::factory()->coordinator()->create())->get("/merit-applications/{$app->id}")
            ->assertOk()->assertSee('100')->assertSee('50 points');
    }

    public function test_advisor_dashboard_shows_own_stats(): void
    {
        $advisor = User::factory()->advisor()->create();
        $this->eventWithCommittee($advisor, ['title' => 'Upcoming Show', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);

        $this->actingAs($advisor)->get('/dashboard')->assertOk()
            ->assertSee('Upcoming Show')->assertSee('Events by level')->assertSee('data-chart="bar"', false);
    }

    public function test_coordinator_dashboard_lists_pending_merit_applications(): void
    {
        $event = $this->eventWithCommittee(User::factory()->advisor()->create(), ['title' => 'Queue Event']);
        MeritApplication::create(['event_id' => $event->id]);

        $this->actingAs(User::factory()->coordinator()->create())->get('/dashboard')->assertOk()
            ->assertSee('Merit applications waiting')->assertSee('Queue Event');
    }

    public function test_merit_reports_are_listed(): void
    {
        $event = $this->eventWithCommittee(User::factory()->advisor()->create(['name' => 'Advisor Zed']));
        MeritApplication::create(['event_id' => $event->id, 'status' => 'approved']);

        $this->actingAs(User::factory()->coordinator()->create())->get('/reports')
            ->assertOk()->assertSee('Merit applications by status')->assertSee('Advisor Zed');
    }
}
