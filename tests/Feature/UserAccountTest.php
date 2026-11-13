<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::factory()->coordinator()->create();
    }

    public function test_coordinator_can_create_an_advisor_account(): void
    {
        $this->actingAs($this->coordinator())->post('/users', [
            'role' => 'advisor',
            'name' => 'New Advisor',
            'email' => 'adv@example.com',
            'staff_id' => 's1234',
            'password' => 'password-123',
        ])->assertSessionHasNoErrors()->assertRedirect('/users');

        $advisor = User::where('email', 'adv@example.com')->first();
        $this->assertSame('advisor', $advisor->role);
        $this->assertSame('S1234', $advisor->staff_id);
        $this->assertNull($advisor->student_id);
    }

    public function test_student_account_requires_student_id(): void
    {
        $this->actingAs($this->coordinator())->post('/users', [
            'role' => 'student',
            'name' => 'No ID',
            'email' => 'noid@example.com',
            'password' => 'password-123',
        ])->assertSessionHasErrors('student_id');
    }

    public function test_coordinator_can_edit_and_keep_password_when_blank(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($this->coordinator())->put("/users/{$user->id}", [
            'role' => 'student',
            'name' => 'Renamed',
            'email' => $user->email,
            'student_id' => $user->student_id,
            'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $user->refresh()->name);
        $this->assertSame($hash, $user->password);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['deactivated_at' => now()]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_deactivating_logs_out_an_active_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($this->coordinator())->patch("/users/{$user->id}/toggle");
        $this->assertNotNull($user->refresh()->deactivated_at);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_coordinator_cannot_deactivate_or_delete_self(): void
    {
        $me = $this->coordinator();

        $this->actingAs($me)->patch("/users/{$me->id}/toggle")->assertForbidden();
        $this->actingAs($me)->delete("/users/{$me->id}")->assertForbidden();
    }

    public function test_coordinator_can_delete_another_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->coordinator())->delete("/users/{$user->id}")->assertRedirect('/users');

        $this->assertNull($user->fresh());
    }

    public function test_non_coordinators_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
        $this->actingAs(User::factory()->advisor()->create())->post('/users', [])->assertForbidden();
    }
}
