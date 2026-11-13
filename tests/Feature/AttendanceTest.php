<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function openSlot(array $eventAttrs = [], array $slotAttrs = []): AttendanceSlot
    {
        $event = Event::factory()->create($eventAttrs + ['latitude' => 3.5430000, 'longitude' => 103.4290000]);

        return AttendanceSlot::factory()->for($event)->create($slotAttrs);
    }

    private function member(): User
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        return $student;
    }

    private function checkIn(AttendanceSlot $slot, User $student, array $override = [])
    {
        return $this->post("/checkin/{$slot->qr_token}", $override + [
            'student_id' => $student->student_id,
            'password' => 'password',
            'latitude' => '3.5430000',
            'longitude' => '103.4290000',
        ]);
    }

    public function test_geo_distance_is_accurate(): void
    {
        $this->assertSame(0, Geo::distanceMeters(3.543, 103.429, 3.543, 103.429));
        // 0.001 degree of latitude is about 111 m
        $this->assertEqualsWithDelta(111, Geo::distanceMeters(3.543, 103.429, 3.544, 103.429), 2);
    }

    // ---- Advisor: slots ----

    public function test_advisor_can_create_slot_with_qr_token(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create();

        $this->actingAs($advisor)->post('/attendance', [
            'event_id' => $event->id, 'title' => 'Day 1', 'opens_at' => '2026-12-01T08:00', 'closes_at' => '2026-12-01T10:00', 'radius_m' => 150,
        ])->assertSessionHasNoErrors();

        $slot = AttendanceSlot::first();
        $this->assertNotEmpty($slot->qr_token);
        $this->assertSame(150, $slot->radius_m);
    }

    public function test_slot_validation_and_ownership(): void
    {
        $advisor = User::factory()->advisor()->create();
        $mine = Event::factory()->for($advisor, 'advisor')->create();
        $theirs = Event::factory()->create();

        $this->actingAs($advisor)->post('/attendance', [
            'event_id' => $mine->id, 'title' => 'x', 'opens_at' => '2026-12-01T10:00', 'closes_at' => '2026-12-01T08:00', 'radius_m' => 5,
        ])->assertSessionHasErrors(['closes_at', 'radius_m']);

        $this->actingAs($advisor)->post('/attendance', [
            'event_id' => $theirs->id, 'title' => 'x', 'opens_at' => '2026-12-01T08:00', 'closes_at' => '2026-12-01T10:00', 'radius_m' => 100,
        ])->assertSessionHasErrors('event_id');
    }

    public function test_other_advisor_cannot_manage_slot_but_coordinator_can_view(): void
    {
        $slot = $this->openSlot();

        $this->actingAs(User::factory()->advisor()->create())->get("/attendance/{$slot->id}")->assertForbidden();
        $this->actingAs(User::factory()->advisor()->create())->delete("/attendance/{$slot->id}")->assertForbidden();
        $this->actingAs(User::factory()->coordinator()->create())->get("/attendance/{$slot->id}")->assertOk()->assertDontSee('Delete slot');
        $this->actingAs(User::factory()->create())->get('/attendance')->assertForbidden();
    }

    public function test_advisor_can_update_delete_slot_and_remove_a_check_in(): void
    {
        $advisor = User::factory()->advisor()->create();
        $slot = $this->openSlot(['advisor_id' => $advisor->id]);
        $student = $this->member();
        $record = Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $student->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 10, 'checked_in_at' => now()]);

        $this->actingAs($advisor)->put("/attendance/{$slot->id}", [
            'event_id' => $slot->event_id, 'title' => 'Renamed', 'opens_at' => '2026-12-01T08:00', 'closes_at' => '2026-12-01T10:00', 'radius_m' => 80,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $slot->refresh()->title);

        $this->actingAs($advisor)->delete("/attendance/{$slot->id}/records/{$record->id}");
        $this->assertSame(0, Attendance::count());

        $this->actingAs($advisor)->delete("/attendance/{$slot->id}");
        $this->assertNull($slot->fresh());
    }

    // ---- Student check-in ----

    public function test_checkin_page_is_public_and_shows_event_info(): void
    {
        $slot = $this->openSlot(['title' => 'Public Talk', 'description' => 'Bring your laptop.']);

        $this->get("/checkin/{$slot->qr_token}")->assertOk()->assertSee('Public Talk')->assertSee('Bring your laptop.')->assertSee('3.543');
        $this->get('/checkin/nope')->assertNotFound();
    }

    public function test_student_can_check_in_within_range(): void
    {
        $slot = $this->openSlot();
        $student = $this->member();

        $this->checkIn($slot, $student, ['latitude' => '3.5435000'])->assertSessionHas('checkin');

        $attendance = Attendance::first();
        $this->assertSame($student->id, $attendance->user_id);
        $this->assertLessThanOrEqual(100, $attendance->distance_m);
    }

    public function test_check_in_is_rejected_when_too_far(): void
    {
        $slot = $this->openSlot();

        $this->checkIn($slot, $this->member(), ['latitude' => '3.6000000'])->assertSessionHasErrors('checkin');

        $this->assertSame(0, Attendance::count());
    }

    public function test_wrong_password_unknown_student_and_non_student_are_rejected(): void
    {
        $slot = $this->openSlot();
        $student = $this->member();
        $advisor = User::factory()->advisor()->create(['student_id' => null]);

        $this->checkIn($slot, $student, ['password' => 'wrong'])->assertSessionHasErrors('checkin');
        $this->checkIn($slot, $student, ['student_id' => 'CB00000'])->assertSessionHasErrors('checkin');
        $this->checkIn($slot, $advisor, ['student_id' => 'S0001'])->assertSessionHasErrors('checkin');
        $this->assertSame(0, Attendance::count());
    }

    public function test_closed_and_upcoming_slots_reject_check_in(): void
    {
        $student = $this->member();
        $closed = $this->openSlot([], ['opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $upcoming = $this->openSlot([], ['opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $this->checkIn($closed, $student)->assertSessionHasErrors('checkin');
        $this->checkIn($upcoming, $student)->assertSessionHasErrors('checkin');
        $this->assertSame(0, Attendance::count());
    }

    public function test_cancelled_or_postponed_events_reject_check_in(): void
    {
        $student = $this->member();

        foreach (['cancelled', 'postponed'] as $status) {
            $slot = $this->openSlot(['status' => $status]);
            $this->checkIn($slot, $student)->assertSessionHasErrors('checkin');
        }

        $this->assertSame(0, Attendance::count());
    }

    public function test_only_approved_members_can_check_in(): void
    {
        $slot = $this->openSlot();
        $pending = User::factory()->create();
        Membership::factory()->for($pending)->create();

        $this->checkIn($slot, $pending)->assertSessionHasErrors('checkin');
        $this->checkIn($slot, User::factory()->create())->assertSessionHasErrors('checkin');
    }

    public function test_student_cannot_check_in_twice(): void
    {
        $slot = $this->openSlot();
        $student = $this->member();

        $this->checkIn($slot, $student)->assertSessionHas('checkin');
        $this->checkIn($slot, $student)->assertSessionHasErrors('checkin');

        $this->assertSame(1, Attendance::count());
    }

    public function test_deactivated_student_cannot_check_in(): void
    {
        $slot = $this->openSlot();
        $student = $this->member();
        $student->update(['deactivated_at' => now()]);

        $this->checkIn($slot, $student)->assertSessionHasErrors('checkin');
    }

    public function test_location_is_required(): void
    {
        $slot = $this->openSlot();

        $this->checkIn($slot, $this->member(), ['latitude' => '', 'longitude' => ''])->assertSessionHasErrors(['latitude', 'longitude']);
    }

    // ---- Coordinator dashboard and reports ----

    public function test_coordinator_gets_attendance_dashboard_with_charts(): void
    {
        $slot = $this->openSlot(['title' => 'Big Event']);
        Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $this->member()->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 20, 'checked_in_at' => now()]);

        $this->actingAs(User::factory()->coordinator()->create())->get('/attendance')
            ->assertOk()->assertSee('Check-ins per day')->assertSee('Big Event')->assertSee('data-chart="hbar"', false);
    }

    public function test_attendance_reports_are_listed(): void
    {
        $slot = $this->openSlot(['title' => 'Report Event']);
        Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $this->member()->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 20, 'checked_in_at' => now()]);

        $this->actingAs(User::factory()->coordinator()->create())->get('/reports')
            ->assertOk()->assertSee('Check-ins per attendance slot')->assertSee('Report Event');
    }
}
