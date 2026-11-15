<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Membership;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::factory()->coordinator()->create(['name' => 'Coordinator One']);
    }

    public function test_membership_decisions_are_logged_with_actor_and_target(): void
    {
        $coordinator = $this->coordinator();
        $student = User::factory()->create(['name' => 'Aisyah', 'student_id' => 'CB11111']);
        $membership = Membership::factory()->for($student)->create();

        $this->actingAs($coordinator)->post(route('members.approve', $membership))->assertRedirect();

        $log = ActivityLog::first();
        $this->assertSame('membership.approved', $log->action);
        $this->assertSame($coordinator->id, $log->user_id);
        $this->assertSame('Coordinator One', $log->actor_name);
        $this->assertSame('coordinator', $log->actor_role);
        $this->assertStringContainsString('Aisyah', $log->description);
        $this->assertSame('Membership', $log->subject_type);
        $this->assertSame($membership->id, $log->subject_id);
    }

    public function test_rejection_keeps_the_reason(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs($this->coordinator())->post(route('members.reject', $membership), ['remarks' => 'Card is blurry'])->assertRedirect();

        $this->assertStringContainsString('Card is blurry', ActivityLog::where('action', 'membership.rejected')->value('description'));
    }

    public function test_merit_application_and_claim_decisions_are_logged(): void
    {
        $event = Event::factory()->create(['title' => 'Logged Event']);
        $application = MeritApplication::create(['event_id' => $event->id, 'status' => 'pending']);
        $this->actingAs($this->coordinator())->post(route('merit-applications.approve', $application))->assertRedirect();

        $claim = MeritClaim::factory()->status('submitted')->create();
        $advisor = $claim->event->advisor;
        $this->actingAs($advisor)->post(route('merit-claims.reject', $claim), ['remarks' => 'Wrong letter'])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'merit_application.approved', 'actor_role' => 'coordinator']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'merit_claim.rejected', 'user_id' => $advisor->id]);
    }

    public function test_user_account_changes_are_logged_and_name_survives_deletion(): void
    {
        $coordinator = $this->coordinator();
        $other = User::factory()->advisor()->create(['name' => 'Dr Temp']);

        $this->actingAs($coordinator)->patch(route('users.toggle', $other))->assertRedirect();
        $this->actingAs($coordinator)->delete(route('users.destroy', $other))->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deactivated']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted']);

        // The actor is deleted later: the log still shows who did it.
        $actor = User::factory()->coordinator()->create(['name' => 'Gone Soon']);
        $this->actingAs($actor)->post(route('members.approve', Membership::factory()->create()));
        $actor->delete();

        $log = ActivityLog::where('actor_name', 'Gone Soon')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    public function test_color_for_deactivated_is_red_not_green(): void
    {
        $this->assertSame('red', (new ActivityLog(['action' => 'user.deactivated']))->color);
        $this->assertSame('green', (new ActivityLog(['action' => 'user.activated']))->color);
        $this->assertSame('brand', (new ActivityLog(['action' => 'event.updated']))->color);
    }

    public function test_only_coordinator_can_open_the_log(): void
    {
        $this->get('/activity')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/activity')->assertForbidden();
        $this->actingAs(User::factory()->advisor()->create())->get('/activity')->assertForbidden();
        $this->actingAs($this->coordinator())->get('/activity')->assertOk()->assertSee('No activity found.');
    }

    public function test_log_page_lists_and_filters_entries(): void
    {
        $coordinator = $this->coordinator();
        ActivityLog::create(['action' => 'event.created', 'description' => 'Created the event "Alpha Fest".', 'actor_name' => 'X']);
        ActivityLog::create(['action' => 'user.deleted', 'description' => 'Deleted the account of Budi.', 'actor_name' => 'Y']);

        $this->actingAs($coordinator)->get('/activity')->assertOk()->assertSee('Alpha Fest')->assertSee('Budi');
        $this->actingAs($coordinator)->get('/activity?group=event')->assertSee('Alpha Fest')->assertDontSee('Budi');
        $this->actingAs($coordinator)->get('/activity?q=Budi')->assertSee('Budi')->assertDontSee('Alpha Fest');
        $this->actingAs($coordinator)->get('/activity?from='.now()->addDay()->toDateString())->assertSee('No activity found.');
        $this->actingAs($coordinator)->get('/activity?from=2026-05-02&to=2026-05-01')->assertSessionHasErrors('to');
    }

    public function test_event_lifecycle_is_logged_by_the_advisor(): void
    {
        $event = Event::factory()->create(['title' => 'To Remove']);

        $this->actingAs($event->advisor)->delete(route('events.destroy', $event))->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.deleted', 'user_id' => $event->advisor_id]);
    }
}
