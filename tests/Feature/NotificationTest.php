<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_application_notifies_coordinators(): void
    {
        Storage::fake('local');
        $coordinator = User::factory()->coordinator()->create();
        $student = User::factory()->create();

        $this->actingAs($student)->post('/membership', ['student_card' => UploadedFile::fake()->image('card.png')])->assertRedirect();

        $this->assertSame('New membership application', $coordinator->notifications()->first()->data['title']);
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_student_is_told_when_membership_is_decided(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = User::factory()->create();
        $membership = Membership::factory()->for($student)->create();

        $this->actingAs($coordinator)->post(route('members.approve', $membership))->assertRedirect();

        $this->assertSame('Membership approved', $student->notifications()->first()->data['title']);
    }

    public function test_bell_shows_unread_count_and_read_marks_and_redirects(): void
    {
        $student = User::factory()->create();
        \App\Notifications\Notice::send($student, 'Hello', 'Body text', '/membership');

        $this->actingAs($student)->get('/dashboard')->assertSee('Notifications, 1 unread');

        $id = $student->notifications()->first()->id;
        $this->actingAs($student)->post("/notifications/{$id}/read")->assertRedirect('/membership');
        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_read_all_and_privacy(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        \App\Notifications\Notice::send($a, 'One', 'x', '/dashboard');
        \App\Notifications\Notice::send($a, 'Two', 'x', '/dashboard');
        \App\Notifications\Notice::send($b, 'Secret', 'x', '/dashboard');

        $this->actingAs($a)->get('/notifications')->assertOk()->assertSee('One')->assertDontSee('Secret');
        $this->actingAs($a)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $a->unreadNotifications()->count());

        $otherId = $b->notifications()->first()->id;
        $this->actingAs($a)->post("/notifications/{$otherId}/read")->assertNotFound();
    }

    public function test_error_pages_render(): void
    {
        $this->get('/nope-nothing-here')->assertNotFound()->assertSee('Page not found');
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden()->assertSee('Access denied');
    }
}
