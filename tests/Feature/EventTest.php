<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCommittee;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Hack Night',
            'description' => 'Overnight coding.',
            'level' => 'umpsa',
            'venue' => 'Dewan Utama',
            'latitude' => '3.5430000',
            'longitude' => '103.4290000',
            'starts_at' => '2026-12-01T09:00',
            'ends_at' => '2026-12-01T17:00',
            'academic_year' => '2026/2027',
            'semester' => 1,
            'approval_letter' => UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf'),
        ], $override);
    }

    private function member(): User
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        return $student;
    }

    public function test_advisor_can_create_event_with_letter_and_qr_token(): void
    {
        $advisor = User::factory()->advisor()->create();

        $this->actingAs($advisor)->post('/events', $this->payload())->assertSessionHasNoErrors();

        $event = Event::first();
        $this->assertSame($advisor->id, $event->advisor_id);
        $this->assertSame('active', $event->status);
        $this->assertNotEmpty($event->qr_token);
        Storage::assertExists($event->approval_letter_path);
    }

    public function test_event_requires_letter_valid_coordinates_and_end_after_start(): void
    {
        $this->actingAs(User::factory()->advisor()->create())->post('/events', $this->payload([
            'approval_letter' => null, 'latitude' => '123', 'ends_at' => '2026-11-30T09:00',
        ]))->assertSessionHasErrors(['approval_letter', 'latitude', 'ends_at']);
    }

    public function test_advisor_can_postpone_an_event_and_students_see_the_status(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create();
        $student = $this->member();
        $event->committees()->create(['user_id' => $student->id, 'position' => 'committee']);

        $this->actingAs($advisor)->put("/events/{$event->id}", $this->payload(['status' => 'postponed', 'approval_letter' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('postponed', $event->refresh()->status);
        $this->actingAs($student)->get('/my-events')->assertSee('Postponed')->assertSee($event->title);
    }

    public function test_advisor_cannot_touch_another_advisors_event(): void
    {
        $event = Event::factory()->create();
        $other = User::factory()->advisor()->create();

        $this->actingAs($other)->get("/events/{$event->id}/edit")->assertForbidden();
        $this->actingAs($other)->delete("/events/{$event->id}")->assertForbidden();
        $this->actingAs($other)->get("/events/{$event->id}")->assertForbidden();
    }

    public function test_advisor_list_only_shows_own_events_but_coordinator_sees_all(): void
    {
        $advisor = User::factory()->advisor()->create();
        Event::factory()->for($advisor, 'advisor')->create(['title' => 'Mine Event']);
        Event::factory()->create(['title' => 'Other Event']);

        $this->actingAs($advisor)->get('/events')->assertSee('Mine Event')->assertDontSee('Other Event');
        $this->actingAs(User::factory()->coordinator()->create())->get('/events')->assertSee('Mine Event')->assertSee('Other Event');
    }

    public function test_students_cannot_open_event_management(): void
    {
        $student = User::factory()->create();
        $event = Event::factory()->create();

        $this->actingAs($student)->get('/events')->assertForbidden();
        $this->actingAs($student)->get('/events/create')->assertForbidden();
        $this->actingAs($student)->get("/events/{$event->id}")->assertForbidden();
    }

    public function test_committee_student_can_view_event_but_not_the_letter(): void
    {
        $event = Event::factory()->create();
        $student = $this->member();
        $event->committees()->create(['user_id' => $student->id, 'position' => 'main_committee']);

        $this->actingAs($student)->get("/events/{$event->id}")->assertOk()->assertDontSee('Open letter');
        $this->actingAs($student)->get("/events/{$event->id}/letter")->assertForbidden();
    }

    public function test_letter_is_served_to_owner_and_coordinator_only(): void
    {
        Storage::put('approval-letters/l.png', 'x');
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create(['approval_letter_path' => 'approval-letters/l.png']);

        $this->actingAs($advisor)->get("/events/{$event->id}/letter")->assertOk();
        $this->actingAs(User::factory()->coordinator()->create())->get("/events/{$event->id}/letter")->assertOk();
        $this->actingAs(User::factory()->advisor()->create())->get("/events/{$event->id}/letter")->assertForbidden();
    }

    public function test_advisor_can_add_and_remove_committee_members(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create();
        $student = $this->member();

        $this->actingAs($advisor)->post("/events/{$event->id}/committee", [
            'student_id' => strtolower($student->student_id), 'position' => 'main_committee', 'position_title' => 'Director',
        ])->assertSessionHasNoErrors();

        $member = EventCommittee::first();
        $this->assertSame('Director', $member->position_title);

        $this->actingAs($advisor)->post("/events/{$event->id}/committee", ['student_id' => $student->student_id, 'position' => 'committee'])
            ->assertSessionHasErrors('student_id');

        $this->actingAs($advisor)->delete("/events/{$event->id}/committee/{$member->id}");
        $this->assertSame(0, EventCommittee::count());
    }

    public function test_only_approved_members_can_join_a_committee(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create();
        $nonMember = User::factory()->create();

        $this->actingAs($advisor)->post("/events/{$event->id}/committee", ['student_id' => $nonMember->student_id, 'position' => 'committee'])
            ->assertSessionHasErrors('student_id');
        $this->actingAs($advisor)->post("/events/{$event->id}/committee", ['student_id' => 'NOPE', 'position' => 'committee'])
            ->assertSessionHasErrors('student_id');
    }

    public function test_public_event_page_works_without_login(): void
    {
        $event = Event::factory()->status('cancelled')->create(['title' => 'Public Talk']);

        $this->get("/e/{$event->qr_token}")->assertOk()->assertSee('Public Talk')->assertSee('cancelled');
        $this->get('/e/does-not-exist')->assertNotFound();
    }

    public function test_advisor_can_delete_event(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create();

        $this->actingAs($advisor)->delete("/events/{$event->id}")->assertRedirect('/events');

        $this->assertNull($event->fresh());
    }

    public function test_event_reports_are_listed_for_coordinator(): void
    {
        Event::factory()->create(['title' => 'Report Event', 'level' => 'state']);

        $this->actingAs(User::factory()->coordinator()->create())->get('/reports')
            ->assertOk()->assertSee('Events by level')->assertSee('Report Event');
    }

    public function test_academic_year_starts_in_september(): void
    {
        $this->assertSame('2025/2026', Event::academicYearFor(new \DateTime('2025-09-01')));
        $this->assertSame('2025/2026', Event::academicYearFor(new \DateTime('2026-03-15')));
    }
}
