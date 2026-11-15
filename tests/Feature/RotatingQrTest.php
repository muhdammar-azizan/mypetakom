<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Support\RotatingCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotatingQrTest extends TestCase
{
    use RefreshDatabase;

    private function slot(bool $rotating = true, array $eventAttrs = []): AttendanceSlot
    {
        $event = Event::factory()->create($eventAttrs + ['latitude' => 3.5430000, 'longitude' => 103.4290000]);

        return AttendanceSlot::factory()->for($event)->create(['rotating_qr' => $rotating]);
    }

    private function member(): User
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        return $student;
    }

    private function submit(AttendanceSlot $slot, User $student, ?string $code)
    {
        return $this->from("/checkin/{$slot->qr_token}")->post("/checkin/{$slot->qr_token}", [
            'student_id' => $student->student_id,
            'password' => 'password',
            'latitude' => '3.5430000',
            'longitude' => '103.4290000',
            'code' => $code,
        ]);
    }

    // ---- The code itself ----

    public function test_code_is_stable_inside_a_window_and_changes_between_windows(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->startOfMinute());

        $first = RotatingCode::current($slot);
        $this->travel(RotatingCode::PERIOD - 1)->seconds();
        $this->assertSame($first, RotatingCode::current($slot));

        $this->travel(2)->seconds();
        $this->assertNotSame($first, RotatingCode::current($slot));
    }

    public function test_code_belongs_to_one_slot_only(): void
    {
        $a = $this->slot();
        $b = $this->slot();

        $this->assertNotSame(RotatingCode::current($a), RotatingCode::current($b));
        $this->assertFalse(RotatingCode::valid($b, RotatingCode::current($a)));
    }

    public function test_code_expires_after_the_grace_period(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->startOfMinute());
        $code = RotatingCode::current($slot);

        $this->travel(RotatingCode::PERIOD)->seconds();
        $this->assertTrue(RotatingCode::valid($slot, $code));            // one window old: still fine

        $this->travel(RotatingCode::PERIOD)->seconds();
        $this->assertFalse(RotatingCode::valid($slot, $code));           // two windows old: expired
        $this->assertTrue(RotatingCode::valid($slot, $code, RotatingCode::SUBMIT_GRACE));
        $this->assertFalse(RotatingCode::valid($slot, null));
        $this->assertFalse(RotatingCode::valid($slot, 'guess-guess-'));
    }

    // ---- Check-in page ----

    public function test_page_with_current_code_shows_the_form_and_carries_the_code(): void
    {
        $slot = $this->slot();
        $code = RotatingCode::current($slot);

        $this->get("/checkin/{$slot->qr_token}?c={$code}")->assertOk()
            ->assertSee('Check in')->assertSee('name="code" value="'.$code.'"', false)->assertDontSee('QR code has expired');
    }

    public function test_page_without_or_with_a_stale_code_says_expired(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->startOfMinute());
        $old = RotatingCode::current($slot);

        $this->get("/checkin/{$slot->qr_token}")->assertOk()->assertSee('This QR code has expired')->assertDontSee('name="student_id"', false);

        $this->travel(RotatingCode::PERIOD * 3)->seconds();
        $this->get("/checkin/{$slot->qr_token}?c={$old}")->assertSee('This QR code has expired');
    }

    public function test_static_slot_does_not_need_a_code(): void
    {
        $slot = $this->slot(false);

        $this->get("/checkin/{$slot->qr_token}")->assertOk()->assertDontSee('QR code has expired')->assertSee('name="student_id"', false);
    }

    // ---- Submitting ----

    public function test_check_in_works_with_a_valid_code_and_is_refused_without_one(): void
    {
        $slot = $this->slot();
        $student = $this->member();

        $this->submit($slot, $student, null)->assertSessionHasErrors('checkin');
        $this->submit($slot, $student, 'not-a-real-code')->assertSessionHasErrors('checkin');
        $this->assertSame(0, Attendance::count());

        $this->submit($slot, $student, RotatingCode::current($slot))->assertSessionHas('checkin');
        $this->assertSame(1, Attendance::count());
    }

    public function test_student_has_time_to_fill_the_form_but_not_forever(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->startOfMinute());
        $code = RotatingCode::current($slot);

        $this->travel(5)->minutes();
        $this->submit($slot, $this->member(), $code)->assertSessionHas('checkin');

        $this->travel(10)->minutes();
        $this->submit($slot, $this->member(), $code)->assertSessionHasErrors('checkin');
        $this->assertSame(1, Attendance::count());
    }

    public function test_static_slot_still_accepts_check_in_without_a_code(): void
    {
        $slot = $this->slot(false);

        $this->submit($slot, $this->member(), null)->assertSessionHas('checkin');
    }

    // ---- Advisor side ----

    public function test_qr_endpoint_returns_a_fresh_svg_for_staff_only(): void
    {
        $slot = $this->slot();
        $advisor = $slot->event->advisor;

        $response = $this->actingAs($advisor)->getJson("/attendance/{$slot->id}/qr")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonStructure(['svg', 'url', 'refresh_in']);
        $this->assertStringContainsString('<svg', $response->json('svg'));
        $this->assertStringContainsString('c='.RotatingCode::current($slot), $response->json('url'));
        $this->assertLessThanOrEqual(RotatingCode::PERIOD, $response->json('refresh_in'));

        $this->actingAs(User::factory()->coordinator()->create())->getJson("/attendance/{$slot->id}/qr")->assertOk();
        $this->actingAs(User::factory()->advisor()->create())->getJson("/attendance/{$slot->id}/qr")->assertForbidden();
        $this->actingAs($this->member())->getJson("/attendance/{$slot->id}/qr")->assertForbidden();
    }

    public function test_slot_page_shows_a_countdown_for_rotating_and_a_print_button_for_static(): void
    {
        $rotating = $this->slot();
        $this->actingAs($rotating->event->advisor)->get("/attendance/{$rotating->id}")
            ->assertOk()->assertSee('New code in')->assertDontSee('Print');

        $static = $this->slot(false);
        $this->actingAs($static->event->advisor)->get("/attendance/{$static->id}")
            ->assertOk()->assertSee('Print')->assertDontSee('New code in');
    }

    public function test_advisor_can_turn_rotation_on_and_off_in_the_form(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->create(['advisor_id' => $advisor->id]);
        $payload = ['event_id' => $event->id, 'title' => 'Session', 'opens_at' => '2026-12-01T08:00', 'closes_at' => '2026-12-01T10:00', 'radius_m' => 100];

        $this->actingAs($advisor)->get('/attendance/create')->assertSee('Rotating QR code')->assertSee('checked', false);

        $this->actingAs($advisor)->post('/attendance', $payload + ['rotating_qr' => '1']);
        $this->assertTrue(AttendanceSlot::latest('id')->first()->rotating_qr);

        $this->actingAs($advisor)->post('/attendance', $payload);
        $this->assertFalse(AttendanceSlot::latest('id')->first()->rotating_qr);
    }
}
