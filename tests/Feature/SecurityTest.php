<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_check_in_password_guessing_is_locked_per_student(): void
    {
        RateLimiter::clear('checkin-login:CB12345');
        $student = User::factory()->create(['student_id' => 'CB12345']);
        $slot = AttendanceSlot::factory()->create();
        $payload = ['student_id' => 'CB12345', 'password' => 'wrong-password', 'latitude' => 3.5, 'longitude' => 103.4];

        for ($i = 0; $i < 5; $i++) {
            $this->post("/checkin/{$slot->qr_token}", $payload)->assertSessionHasErrors('checkin');
        }

        // Even the right password is refused while locked.
        $this->post("/checkin/{$slot->qr_token}", ['password' => 'password'] + $payload)
            ->assertSessionHasErrors('checkin');
        $this->assertStringContainsString('Too many failed attempts', session('errors')->first('checkin'));
        $this->assertSame(0, Attendance::where('user_id', $student->id)->count());
    }

    public function test_public_registration_cannot_choose_a_role(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'student_id' => 'cb99999', 'programme' => 'BCS',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'coordinator',
        ]);

        $this->assertSame('student', User::where('email', 'sneaky@example.test')->value('role'));
    }

    public function test_profile_update_cannot_change_role(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->patch('/profile', ['name' => $student->name, 'email' => $student->email, 'role' => 'coordinator']);

        $this->assertSame('student', $student->fresh()->role);
    }
}
