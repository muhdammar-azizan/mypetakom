<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One place that proves each role can only open the pages meant for it. */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/membership' => ['student'],
        '/merit' => ['student'],
        '/my-events' => ['student'],
        '/merit-claims/create' => ['student'],
        '/merit-claims' => ['student', 'advisor'],
        '/events' => ['advisor', 'coordinator'],
        '/events/create' => ['advisor'],
        '/attendance' => ['advisor', 'coordinator'],
        '/attendance/create' => ['advisor'],
        '/merit-applications' => ['advisor', 'coordinator'],
        '/members' => ['coordinator'],
        '/users' => ['coordinator'],
        '/users/create' => ['coordinator'],
        '/reports' => ['coordinator'],
        '/activity' => ['coordinator'],
        '/members/export' => ['coordinator'],
        '/events/export' => ['advisor', 'coordinator'],
        '/dashboard' => ['student', 'advisor', 'coordinator'],
        '/profile' => ['student', 'advisor', 'coordinator'],
        '/notifications' => ['student', 'advisor', 'coordinator'],
        '/calendar' => ['student', 'advisor', 'coordinator'],
        '/leaderboard' => ['student', 'advisor', 'coordinator'],
        '/merit/statement' => ['student'],
        '/settings' => ['student', 'advisor', 'coordinator'],
    ];

    private function userFor(string $role): User
    {
        return match ($role) {
            'advisor' => User::factory()->advisor()->create(),
            'coordinator' => User::factory()->coordinator()->create(),
            default => User::factory()->create(),
        };
    }

    public function test_guests_are_sent_to_login_for_every_protected_page(): void
    {
        foreach (array_keys(self::PAGES) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_each_role_only_opens_its_own_pages(): void
    {
        foreach (['student', 'advisor', 'coordinator'] as $role) {
            $user = $this->userFor($role);

            foreach (self::PAGES as $url => $allowed) {
                $response = $this->actingAs($user)->get($url);
                in_array($role, $allowed, true)
                    ? $response->assertOk()
                    : $response->assertForbidden();
            }
        }
    }

    public function test_state_changing_routes_reject_the_wrong_role(): void
    {
        $student = $this->userFor('student');
        $advisor = $this->userFor('advisor');

        $this->actingAs($student)->post('/events', [])->assertForbidden();
        $this->actingAs($student)->post('/users', [])->assertForbidden();
        $this->actingAs($advisor)->post('/membership', [])->assertForbidden();
        $event = \App\Models\Event::factory()->create();
        $application = \App\Models\MeritApplication::create(['event_id' => $event->id, 'status' => 'pending']);
        $claim = \App\Models\MeritClaim::factory()->status('submitted')->create();

        $this->actingAs($advisor)->post("/merit-applications/{$application->id}/approve")->assertForbidden();
        $this->actingAs($student)->post("/merit-claims/{$claim->id}/approve")->assertForbidden();
    }

    public function test_public_pages_do_not_need_login_and_unknown_tokens_404(): void
    {
        $this->get('/e/unknown')->assertNotFound();
        $this->get('/qr/unknown')->assertNotFound();
        $this->get('/checkin/unknown')->assertNotFound();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'nobody@petakom.test', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'nobody@petakom.test', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many', session('errors')->first('login'));
    }
}
