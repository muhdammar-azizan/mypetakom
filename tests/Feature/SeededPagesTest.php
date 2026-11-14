<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Membership;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders the main pages against the full demo data. Lazy loading is forbidden outside production
 * (see AppServiceProvider), so any N+1 query on these lists fails this test.
 */
class SeededPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function open(User $user, array $urls): void
    {
        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_coordinator_pages_render_with_demo_data(): void
    {
        $coordinator = User::where('email', 'coordinator@petakom.test')->first();
        $event = Event::first();

        $this->open($coordinator, [
            '/dashboard', '/members', '/users', '/reports', '/events', '/attendance', '/merit-applications', '/notifications',
            '/events/'.$event->id, '/members/'.Membership::first()->id, '/merit-applications/'.MeritApplication::first()->id,
        ]);
    }

    public function test_advisor_pages_render_with_demo_data(): void
    {
        $advisor = User::where('email', 'advisor@petakom.test')->first();
        $event = Event::where('advisor_id', $advisor->id)->first();

        $this->open($advisor, [
            '/dashboard', '/events', '/attendance', '/merit-applications', '/merit-claims',
            '/events/'.$event->id, '/events/'.$event->id.'/edit', '/merit-claims/'.MeritClaim::where('status', '!=', MeritClaim::DRAFT)->first()->id,
        ]);
    }

    public function test_students_see_their_pages_with_demo_data(): void
    {
        $member = Membership::where('status', 'approved')->first()->user;
        $this->open($member, ['/dashboard', '/merit', '/my-events', '/merit-claims', '/profile']);

        $demo = User::where('email', 'student@petakom.test')->first();
        $this->open($demo, ['/dashboard', '/membership', '/merit']);
    }
}
