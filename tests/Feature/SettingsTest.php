<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_needs_login_and_shows_role_specific_options(): void
    {
        $this->get('/settings')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/settings')->assertOk()
            ->assertSee('In-app notifications')->assertSee('Show me on the leaderboard')->assertSee('Change Password')->assertSee('Delete my account');

        $this->actingAs(User::factory()->advisor()->create())->get('/settings')->assertOk()->assertDontSee('Show me on the leaderboard');
    }

    public function test_password_card_now_lives_on_settings_not_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertDontSee('Update Password');
        $this->actingAs($user)->get('/settings')->assertSee('Update Password');
    }

    public function test_preferences_are_saved_and_unchecked_boxes_turn_off(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->patch('/settings/preferences', ['notify_in_app' => '1'])->assertRedirect();

        $student->refresh();
        $this->assertTrue($student->notify_in_app);
        $this->assertFalse($student->notify_email);
        $this->assertFalse($student->show_on_leaderboard);
    }

    public function test_staff_cannot_change_leaderboard_flag(): void
    {
        $advisor = User::factory()->advisor()->create();

        $this->actingAs($advisor)->patch('/settings/preferences', ['notify_in_app' => '1', 'show_on_leaderboard' => '0']);

        $this->assertTrue($advisor->refresh()->show_on_leaderboard);
    }

    public function test_notices_are_skipped_when_user_turned_them_off(): void
    {
        $quiet = User::factory()->create(['notify_in_app' => false]);
        $loud = User::factory()->create();

        Notice::send([$quiet, $loud], 'Hi', 'Body', '/dashboard');

        $this->assertSame(0, $quiet->notifications()->count());
        $this->assertSame(1, $loud->notifications()->count());
    }

    public function test_student_can_delete_own_account_with_password(): void
    {
        $student = User::factory()->create(['name' => 'Leaving Student']);
        Membership::factory()->for($student)->create();

        $this->actingAs($student)->delete('/settings/account', ['password' => 'password'])->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('memberships', ['user_id' => $student->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deleted', 'actor_name' => 'Leaving Student']);
    }

    public function test_wrong_password_keeps_the_account(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->delete('/settings/account', ['password' => 'nope'])->assertSessionHasErrorsIn('deleteAccount', 'password');

        $this->assertDatabaseHas('users', ['id' => $student->id]);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_coordinator_and_advisor_with_events_cannot_self_delete(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->delete('/settings/account', ['password' => 'password'])->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->assertDatabaseHas('users', ['id' => $coordinator->id]);

        $event = Event::factory()->create();
        $this->actingAs($event->advisor)->delete('/settings/account', ['password' => 'password'])->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $free = User::factory()->advisor()->create();
        $this->actingAs($free)->delete('/settings/account', ['password' => 'password'])->assertRedirect('/login');
        $this->assertDatabaseMissing('users', ['id' => $free->id]);
    }

    public function test_password_change_still_works_from_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings')->put('/password', [
            'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/settings');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_bell_menu_links_to_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee(route('settings.edit'), false);
    }
}
