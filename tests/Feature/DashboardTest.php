<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach ([User::factory(), User::factory()->advisor(), User::factory()->coordinator()] as $factory) {
            $this->actingAs($factory->create())->get('/dashboard')->assertOk();
        }
    }

    public function test_coordinator_dashboard_shows_membership_stats(): void
    {
        Membership::factory()->count(2)->approved()->create();
        Membership::factory()->for(User::factory()->state(['name' => 'Waiting Student']))->create();

        $this->actingAs(User::factory()->coordinator()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Pending applications')
            ->assertSee('Waiting Student')
            ->assertSee('data-chart="bar"', false)
            ->assertSee('BCS (Software Engineering)');
    }

    public function test_coordinator_dashboard_handles_empty_data(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->get('/dashboard')
            ->assertOk()->assertSee('No approved members yet.')->assertSee('No applications yet.');
    }

    public function test_students_do_not_see_coordinator_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('Pending applications');
    }
}
