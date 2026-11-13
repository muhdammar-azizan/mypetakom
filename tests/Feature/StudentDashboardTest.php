<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Merit;
use App\Models\MeritClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        return $student;
    }

    public function test_new_student_sees_apply_banner_and_empty_states(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertSee('You are not a Petakom member yet')
            ->assertSee('No merit yet. Join an event to start earning.');
    }

    public function test_pending_and_rejected_banners(): void
    {
        $pending = User::factory()->create();
        Membership::factory()->for($pending)->create();
        $this->actingAs($pending)->get('/dashboard')->assertSee('waiting for review');

        $rejected = User::factory()->create();
        Membership::factory()->rejected()->for($rejected)->create();
        $this->actingAs($rejected)->get('/dashboard')->assertSee('was rejected');
    }

    public function test_approved_member_has_no_banner(): void
    {
        $this->actingAs($this->member())->get('/dashboard')->assertOk()->assertDontSee('not a Petakom member')->assertDontSee('waiting for review');
    }

    public function test_dashboard_shows_merit_charts_and_totals(): void
    {
        $student = $this->member();
        $old = Event::factory()->create(['level' => 'national', 'starts_at' => now()->subYear(), 'ends_at' => now()->subYear()->addHour()]);
        $new = Event::factory()->create(['level' => 'umpsa']);
        Merit::create(['user_id' => $student->id, 'event_id' => $old->id, 'role' => 'committee', 'points' => 50, 'academic_year' => '2024/2025', 'semester' => 1, 'source' => 'committee']);
        Merit::create(['user_id' => $student->id, 'event_id' => $new->id, 'role' => 'participant', 'points' => 5, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);

        $response = $this->actingAs($student)->get('/dashboard')->assertOk();

        $response->assertSee('data-chart="line"', false)->assertSee('data-chart="hbar"', false);
        $response->assertSee('2024\/2025 S1', false)->assertSee('[50,55]', false);
        $response->assertSee('55');
    }

    public function test_dashboard_lists_upcoming_committee_events_and_claim_counts(): void
    {
        $student = $this->member();
        $event = Event::factory()->create(['title' => 'Upcoming Summit', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);
        $event->committees()->create(['user_id' => $student->id, 'position' => 'main_committee']);
        MeritClaim::factory()->for($student)->status('submitted')->create();

        $this->actingAs($student)->get('/dashboard')->assertSee('Upcoming Summit')->assertSee('Main Committee')->assertSee('View my claims');
    }

    public function test_check_in_count_is_shown(): void
    {
        $student = $this->member();
        $slot = AttendanceSlot::factory()->create();
        Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $student->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 10, 'checked_in_at' => now()]);

        $this->actingAs($student)->get('/dashboard')->assertSee('Event check-ins');
    }

    public function test_student_dashboard_is_private_to_the_student(): void
    {
        $student = $this->member();
        $other = Event::factory()->create(['title' => 'Other Student Event']);
        $other->committees()->create(['user_id' => User::factory()->create()->id, 'position' => 'committee']);

        $this->actingAs($student)->get('/dashboard')->assertDontSee('Other Student Event');
    }
}
