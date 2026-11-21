<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_login_and_register(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('MyPetakom')
            ->assertSee('Persatuan Petakom')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertDontSee('Go to dashboard')
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }

    public function test_signed_in_users_get_a_dashboard_button_instead(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Go to dashboard')
            ->assertDontSee('Create a student account');
    }

    public function test_the_landing_page_shows_no_member_data(): void
    {
        $student = User::factory()->create(['name' => 'Secret Student Name']);

        $this->get('/')->assertOk()->assertDontSee($student->name)->assertDontSee($student->email);
    }
}
