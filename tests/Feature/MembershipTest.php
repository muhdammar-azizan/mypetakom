<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_student_can_apply_with_student_card(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->post('/membership', ['student_card' => UploadedFile::fake()->image('card.png')])
            ->assertSessionHasNoErrors()->assertRedirect('/membership');

        $membership = $student->refresh()->membership;
        $this->assertSame('pending', $membership->status);
        Storage::assertExists($membership->student_card_path);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/membership', ['student_card' => UploadedFile::fake()->create('card.exe', 10)])
            ->assertSessionHasErrors('student_card');
    }

    public function test_rejected_student_can_resubmit_and_replace_the_card(): void
    {
        $student = User::factory()->create();
        Storage::put('student-cards/old.png', 'x');
        Membership::factory()->rejected()->for($student)->create(['student_card_path' => 'student-cards/old.png']);

        $this->actingAs($student)->post('/membership', ['student_card' => UploadedFile::fake()->image('new.png')]);

        $membership = $student->refresh()->membership;
        $this->assertSame('pending', $membership->status);
        $this->assertNull($membership->remarks);
        Storage::assertMissing('student-cards/old.png');
    }

    public function test_approved_membership_cannot_be_cancelled(): void
    {
        $student = User::factory()->create();
        Membership::factory()->approved()->for($student)->create();

        $this->actingAs($student)->delete('/membership')->assertForbidden();
    }

    public function test_pending_application_can_be_cancelled(): void
    {
        $student = User::factory()->create();
        Membership::factory()->for($student)->create();

        $this->actingAs($student)->delete('/membership')->assertRedirect('/membership');

        $this->assertNull($student->refresh()->membership);
    }

    public function test_coordinator_can_approve_and_reject_with_reason(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $a = Membership::factory()->create();
        $b = Membership::factory()->create();

        $this->actingAs($coordinator)->post("/members/{$a->id}/approve")->assertRedirect();
        $this->assertSame('approved', $a->refresh()->status);
        $this->assertSame($coordinator->id, $a->reviewed_by);

        $this->actingAs($coordinator)->post("/members/{$b->id}/reject", [])->assertSessionHasErrors('remarks');
        $this->actingAs($coordinator)->post("/members/{$b->id}/reject", ['remarks' => 'Blurry'])->assertRedirect();
        $this->assertSame('rejected', $b->refresh()->status);
        $this->assertSame('Blurry', $b->remarks);
    }

    public function test_members_list_can_be_filtered(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        Membership::factory()->for(User::factory()->state(['name' => 'Alice Pending']))->create();
        Membership::factory()->approved()->for(User::factory()->state(['name' => 'Bob Approved']))->create();

        $this->actingAs($coordinator)->get('/members?status=approved')
            ->assertSee('Bob Approved')->assertDontSee('Alice Pending');
    }

    public function test_only_coordinator_can_open_member_pages(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs(User::factory()->create())->get('/members')->assertForbidden();
        $this->actingAs(User::factory()->advisor()->create())->get("/members/{$membership->id}")->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/members')->assertRedirect('/login');
    }

    public function test_student_card_is_visible_only_to_owner_and_coordinator(): void
    {
        $owner = User::factory()->create();
        Storage::put('student-cards/c.png', 'img');
        $membership = Membership::factory()->for($owner)->create(['student_card_path' => 'student-cards/c.png']);

        $this->actingAs($owner)->get("/membership-cards/{$membership->id}")->assertOk();
        $this->actingAs(User::factory()->coordinator()->create())->get("/membership-cards/{$membership->id}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/membership-cards/{$membership->id}")->assertForbidden();
    }

    public function test_student_cannot_open_coordinator_reports(): void
    {
        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
    }

    public function test_reports_show_membership_data(): void
    {
        Membership::factory()->approved()->for(User::factory()->state(['programme' => 'BCS (Cyber Security)']))->create();

        $this->actingAs(User::factory()->coordinator()->create())->get('/reports')
            ->assertOk()->assertSee('BCS (Cyber Security)')->assertSee('Memberships by status');
    }
}
