<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_pages_have_skip_link_and_main_landmark(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertSee('Skip to content')->assertSee('id="main"', false);
    }

    public function test_filter_controls_have_accessible_names(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get('/members')->assertSee('aria-label="Filter by status"', false)->assertSee('aria-label="Search name, student ID or programme"', false);
        $this->actingAs($coordinator)->get('/users')->assertSee('aria-label="Filter by role"', false);
        $this->actingAs($coordinator)->get('/events')->assertSee('aria-label="Filter by level"', false);
    }
}
