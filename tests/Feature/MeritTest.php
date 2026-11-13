<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Merit;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use App\Services\MeritService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeritTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function member(): User
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        return $student;
    }

    private function approvedEvent(string $level = 'national'): array
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create(['level' => $level, 'academic_year' => '2025/2026', 'semester' => 2]);
        $main = $this->member();
        $helper = $this->member();
        $event->committees()->create(['user_id' => $main->id, 'position' => 'main_committee']);
        $event->committees()->create(['user_id' => $helper->id, 'position' => 'committee']);
        MeritApplication::create(['event_id' => $event->id]);

        return [$event, $advisor, $main, $helper];
    }

    // ---- Scores and awarding ----

    public function test_scores_match_the_merit_table(): void
    {
        $this->assertSame(100, MeritService::points('international', 'main_committee'));
        $this->assertSame(70, MeritService::points('international', 'committee'));
        $this->assertSame(40, MeritService::points('national', 'participant'));
        $this->assertSame(40, MeritService::points('state', 'committee'));
        $this->assertSame(15, MeritService::points('district', 'participant'));
        $this->assertSame(30, MeritService::points('umpsa', 'main_committee'));
        $this->assertSame(5, MeritService::points('umpsa', 'participant'));
    }

    public function test_approving_merit_application_awards_committee_merit(): void
    {
        [$event, , $main, $helper] = $this->approvedEvent('national');

        $this->actingAs(User::factory()->coordinator()->create())->post('/merit-applications/'.$event->meritApplication->id.'/approve');

        $this->assertSame(80, Merit::where('user_id', $main->id)->value('points'));
        $this->assertSame(50, Merit::where('user_id', $helper->id)->value('points'));
        $this->assertSame('2025/2026', Merit::first()->academic_year);
        $this->assertSame(2, Merit::first()->semester);
    }

    public function test_no_merit_before_approval_and_removed_if_rejected_later(): void
    {
        [$event] = $this->approvedEvent();
        MeritService::sync($event);
        $this->assertSame(0, Merit::count());

        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->post("/merit-applications/{$event->meritApplication->id}/approve");
        $this->assertSame(2, Merit::count());

        $this->actingAs($coordinator)->post("/merit-applications/{$event->meritApplication->id}/reject", ['remarks' => 'Mistake']);
        $this->assertSame(0, Merit::count());
    }

    public function test_attendees_get_participant_merit_only_after_approval(): void
    {
        [$event, , $main] = $this->approvedEvent('state');
        $slot = AttendanceSlot::factory()->for($event)->create();
        $attendee = $this->member();

        $this->post("/checkin/{$slot->qr_token}", ['student_id' => $attendee->student_id, 'password' => 'password', 'latitude' => $event->latitude, 'longitude' => $event->longitude]);
        $this->assertSame(0, Merit::count());

        $event->meritApplication->update(['status' => 'approved']);
        MeritService::sync($event);

        $this->assertSame(30, Merit::where('user_id', $attendee->id)->value('points'));
        $this->assertSame('participant', Merit::where('user_id', $attendee->id)->value('role'));

        // A later check-in is rewarded immediately.
        $late = $this->member();
        $this->post("/checkin/{$slot->qr_token}", ['student_id' => $late->student_id, 'password' => 'password', 'latitude' => $event->latitude, 'longitude' => $event->longitude]);
        $this->assertSame(30, Merit::where('user_id', $late->id)->value('points'));
    }

    public function test_committee_role_wins_over_participant_and_is_not_duplicated(): void
    {
        [$event, , $main] = $this->approvedEvent('umpsa');
        $event->meritApplication->update(['status' => 'approved']);
        $slot = AttendanceSlot::factory()->for($event)->create();
        Attendance::create(['attendance_slot_id' => $slot->id, 'user_id' => $main->id, 'latitude' => 3.5, 'longitude' => 103.4, 'distance_m' => 5, 'checked_in_at' => now()]);

        MeritService::sync($event);

        $this->assertSame(1, Merit::where('user_id', $main->id)->count());
        $this->assertSame(30, Merit::where('user_id', $main->id)->value('points'));
    }

    public function test_committee_changes_after_approval_update_merit(): void
    {
        [$event, $advisor] = $this->approvedEvent('international');
        $event->meritApplication->update(['status' => 'approved']);
        $newMember = $this->member();

        $this->actingAs($advisor)->post("/events/{$event->id}/committee", ['student_id' => $newMember->student_id, 'position' => 'committee']);
        $this->assertSame(70, Merit::where('user_id', $newMember->id)->value('points'));

        $this->actingAs($advisor)->delete("/events/{$event->id}/committee/".$event->committees()->where('user_id', $newMember->id)->value('id'));
        $this->assertSame(0, Merit::where('user_id', $newMember->id)->count());
    }

    public function test_cancelling_the_event_removes_its_merit(): void
    {
        [$event, $advisor] = $this->approvedEvent();
        $event->meritApplication->update(['status' => 'approved']);
        MeritService::sync($event);
        $this->assertSame(2, Merit::count());

        $this->actingAs($advisor)->put("/events/{$event->id}", [
            'title' => $event->title, 'level' => 'national', 'venue' => 'V', 'latitude' => 3.5, 'longitude' => 103.4,
            'starts_at' => '2026-12-01T09:00', 'ends_at' => '2026-12-01T12:00', 'academic_year' => '2025/2026', 'semester' => 2, 'status' => 'cancelled',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Merit::count());
    }

    // ---- Student merit page and QR ----

    public function test_student_merit_page_shows_cumulative_per_semester(): void
    {
        $student = $this->member();
        $e1 = Event::factory()->create(['title' => 'Old Event', 'academic_year' => '2024/2025', 'semester' => 1, 'starts_at' => now()->subYear(), 'ends_at' => now()->subYear()->addHour()]);
        $e2 = Event::factory()->create(['title' => 'New Event', 'academic_year' => '2025/2026', 'semester' => 1]);
        Merit::create(['user_id' => $student->id, 'event_id' => $e1->id, 'role' => 'committee', 'points' => 50, 'academic_year' => '2024/2025', 'semester' => 1, 'source' => 'committee']);
        Merit::create(['user_id' => $student->id, 'event_id' => $e2->id, 'role' => 'participant', 'points' => 40, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'attendance']);

        $this->actingAs($student)->get('/merit')->assertOk()
            ->assertSee('Old Event')->assertSee('Cumulative 50')->assertSee('Cumulative 90');
    }

    public function test_public_qr_page_shows_student_and_total_merit(): void
    {
        $student = $this->member();
        $event = Event::factory()->create();
        Merit::create(['user_id' => $student->id, 'event_id' => $event->id, 'role' => 'committee', 'points' => 70, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'committee']);

        $this->get("/qr/{$student->qr_token}")->assertOk()->assertSee($student->name)->assertSee($student->student_id)->assertSee('70');
        $this->get('/qr/unknown')->assertNotFound();
        $this->get('/qr/'.User::factory()->advisor()->create()->qr_token)->assertNotFound();
    }

    // ---- Claims ----

    private function claimPayload(Event $event, array $o = []): array
    {
        return $o + ['event_id' => $event->id, 'role' => 'participant', 'reason' => 'I was there.', 'letter' => UploadedFile::fake()->create('l.pdf', 20, 'application/pdf')];
    }

    public function test_student_can_create_a_draft_claim_with_letter(): void
    {
        $student = $this->member();
        $event = Event::factory()->create();

        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($event))->assertSessionHasNoErrors();

        $claim = MeritClaim::first();
        $this->assertSame('pending', $claim->status);
        Storage::assertExists($claim->letter_path);
    }

    public function test_claim_requires_member_letter_and_no_duplicates(): void
    {
        $event = Event::factory()->create();
        $nonMember = User::factory()->create();
        $this->actingAs($nonMember)->post('/merit-claims', $this->claimPayload($event))->assertSessionHasErrors('event_id');

        $student = $this->member();
        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($event, ['letter' => null]))->assertSessionHasErrors('letter');

        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($event));
        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($event))->assertSessionHasErrors('event_id');
        $this->assertSame(1, MeritClaim::count());
    }

    public function test_cannot_claim_for_event_that_already_gave_merit_or_is_cancelled(): void
    {
        $student = $this->member();
        $has = Event::factory()->create();
        Merit::create(['user_id' => $student->id, 'event_id' => $has->id, 'role' => 'committee', 'points' => 20, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'committee']);
        $cancelled = Event::factory()->status('cancelled')->create();

        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($has))->assertSessionHasErrors('event_id');
        $this->actingAs($student)->post('/merit-claims', $this->claimPayload($cancelled))->assertSessionHasErrors('event_id');
    }

    public function test_draft_claim_can_be_edited_and_deleted(): void
    {
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->create();

        $this->actingAs($student)->put("/merit-claims/{$claim->id}", ['role' => 'committee', 'reason' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $claim->refresh()->reason);

        $this->actingAs($student)->delete("/merit-claims/{$claim->id}");
        $this->assertNull($claim->fresh());
    }

    public function test_submitted_claim_is_locked_for_edit_and_delete(): void
    {
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->status('submitted')->create();

        $this->actingAs($student)->put("/merit-claims/{$claim->id}", ['role' => 'committee'])->assertForbidden();
        $this->actingAs($student)->delete("/merit-claims/{$claim->id}")->assertForbidden();
        $this->actingAs($student)->get("/merit-claims/{$claim->id}/edit")->assertForbidden();
        $this->assertNotNull($claim->fresh());
    }

    public function test_student_submits_claim_then_it_is_locked(): void
    {
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->create();

        $this->actingAs($student)->post("/merit-claims/{$claim->id}/submit");
        $this->assertSame('submitted', $claim->refresh()->status);

        $this->actingAs($student)->post("/merit-claims/{$claim->id}/submit")->assertForbidden();
    }

    public function test_rejected_claim_can_be_fixed_and_resubmitted(): void
    {
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->status('rejected')->create(['remarks' => 'Unreadable']);

        $this->actingAs($student)->put("/merit-claims/{$claim->id}", ['role' => 'participant'])->assertSessionHasNoErrors();
        $this->actingAs($student)->post("/merit-claims/{$claim->id}/submit");

        $this->assertSame('submitted', $claim->refresh()->status);
        $this->assertNull($claim->remarks);
    }

    public function test_advisor_approves_claim_and_merit_is_awarded(): void
    {
        $advisor = User::factory()->advisor()->create();
        $event = Event::factory()->for($advisor, 'advisor')->create(['level' => 'state', 'academic_year' => '2025/2026', 'semester' => 1]);
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->for($event)->status('submitted')->create(['role' => 'committee']);

        $this->actingAs($advisor)->post("/merit-claims/{$claim->id}/approve")->assertRedirect();

        $this->assertSame('approved', $claim->refresh()->status);
        $merit = Merit::where('user_id', $student->id)->first();
        $this->assertSame(40, $merit->points);
        $this->assertSame('claim', $merit->source);
    }

    public function test_advisor_reject_needs_reason_and_gives_no_merit(): void
    {
        $advisor = User::factory()->advisor()->create();
        $claim = MeritClaim::factory()->for(Event::factory()->for($advisor, 'advisor'))->status('submitted')->create();

        $this->actingAs($advisor)->post("/merit-claims/{$claim->id}/reject", [])->assertSessionHasErrors('remarks');
        $this->actingAs($advisor)->post("/merit-claims/{$claim->id}/reject", ['remarks' => 'No proof']);

        $this->assertSame('rejected', $claim->refresh()->status);
        $this->assertSame(0, Merit::count());
    }

    public function test_only_the_events_advisor_can_review_and_drafts_are_hidden(): void
    {
        $other = User::factory()->advisor()->create();
        $claim = MeritClaim::factory()->status('submitted')->create();
        $draft = MeritClaim::factory()->create();

        $this->actingAs($other)->post("/merit-claims/{$claim->id}/approve")->assertForbidden();
        $this->actingAs($claim->event->advisor)->get("/merit-claims/{$draft->id}")->assertForbidden();
        $this->actingAs($claim->event->advisor)->get("/merit-claims/{$claim->id}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/merit-claims/{$claim->id}")->assertForbidden();
        $this->assertSame('submitted', $claim->refresh()->status);
    }

    public function test_students_cannot_approve_their_own_claim(): void
    {
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->status('submitted')->create();

        $this->actingAs($student)->post("/merit-claims/{$claim->id}/approve")->assertForbidden();
    }

    public function test_claim_letter_is_visible_to_owner_and_event_advisor_only(): void
    {
        Storage::put('merit-claim-letters/x.png', 'img');
        $student = $this->member();
        $claim = MeritClaim::factory()->for($student)->status('submitted')->create(['letter_path' => 'merit-claim-letters/x.png']);

        $this->actingAs($student)->get("/merit-claims/{$claim->id}/letter")->assertOk();
        $this->actingAs($claim->event->advisor)->get("/merit-claims/{$claim->id}/letter")->assertOk();
        $this->actingAs(User::factory()->create())->get("/merit-claims/{$claim->id}/letter")->assertForbidden();
    }

    public function test_claim_lists_are_scoped(): void
    {
        $student = $this->member();
        MeritClaim::factory()->for($student)->for(Event::factory()->state(['title' => 'My Claim Event']))->create();
        MeritClaim::factory()->for(Event::factory()->state(['title' => 'Someone Else Event']))->create();

        $this->actingAs($student)->get('/merit-claims')->assertSee('My Claim Event')->assertDontSee('Someone Else Event');
    }

    public function test_merit_reports_are_listed(): void
    {
        $student = $this->member();
        $event = Event::factory()->create();
        Merit::create(['user_id' => $student->id, 'event_id' => $event->id, 'role' => 'committee', 'points' => 70, 'academic_year' => '2025/2026', 'semester' => 1, 'source' => 'committee']);

        $this->actingAs(User::factory()->coordinator()->create())->get('/reports')
            ->assertOk()->assertSee('Merit awarded per semester')->assertSee('Top students by merit')->assertSee($student->student_id);
    }
}
