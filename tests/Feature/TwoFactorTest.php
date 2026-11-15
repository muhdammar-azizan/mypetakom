<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google;

    private TwoFactor $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google = new Google2FA();
        $this->service = app(TwoFactor::class);
    }

    /** A code the app would show $steps windows (30 s each) from now. Later steps are newer than any code used before. */
    private function code(User $user, int $steps = 0): string
    {
        return $this->google->oathTotp($user->fresh()->two_factor_secret, $this->google->getTimestamp() + $steps);
    }

    /** @return array{0: User, 1: list<string>} a staff user with two-factor on, and the recovery codes */
    private function staffWithTwoFactor(string $role = 'advisor'): array
    {
        $user = $role === 'coordinator' ? User::factory()->coordinator()->create() : User::factory()->advisor()->create();
        $this->service->start($user);
        $codes = $this->service->confirm($user, $this->code($user));

        return [$user->fresh(), $codes];
    }

    private function loginWithPassword(User $user, bool $remember = false)
    {
        return $this->post('/login', ['login' => $user->email, 'password' => 'password'] + ($remember ? ['remember' => 'on'] : []));
    }

    // =====================================================================
    // Setting it up
    // =====================================================================

    public function test_only_staff_see_the_card_and_only_staff_can_use_the_routes(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertOk()->assertDontSee('Two-factor sign-in');
        $this->actingAs(User::factory()->advisor()->create())->get('/settings')->assertSee('Two-factor sign-in')->assertSee('Set up two-factor');
        $this->actingAs(User::factory()->coordinator()->create())->get('/settings')->assertSee('Set up two-factor');

        $student = User::factory()->create();
        $this->actingAs($student)->post('/settings/two-factor', ['password' => 'password'])->assertForbidden();
        $this->actingAs($student)->post('/settings/two-factor/confirm', ['code' => '123456'])->assertForbidden();
        $this->actingAs($student)->post('/settings/two-factor/recovery-codes', ['password' => 'password'])->assertForbidden();
        $this->actingAs($student)->delete('/settings/two-factor', ['password' => 'password'])->assertForbidden();
    }

    public function test_guests_cannot_reach_the_setup_routes(): void
    {
        $this->post('/settings/two-factor', [])->assertRedirect('/login');
        $this->delete('/settings/two-factor')->assertRedirect('/login');
    }

    public function test_starting_needs_the_password_and_makes_an_unconfirmed_secret(): void
    {
        $advisor = User::factory()->advisor()->create();

        $this->actingAs($advisor)->post('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertNull($advisor->fresh()->two_factor_secret);

        $this->actingAs($advisor)->post('/settings/two-factor', ['password' => 'password'])->assertRedirect('/settings');

        $advisor->refresh();
        $this->assertNotNull($advisor->two_factor_secret);
        $this->assertSame(32, strlen($advisor->two_factor_secret));
        $this->assertFalse($advisor->hasTwoFactorEnabled());   // not protected until the first code is right
    }

    public function test_the_setup_page_shows_the_qr_code_and_the_typed_key(): void
    {
        $advisor = User::factory()->advisor()->create();
        $this->service->start($advisor);
        $advisor->refresh();

        $html = $this->actingAs($advisor)->get('/settings')->assertOk()->assertSee('Turn on')->assertSee("Can't scan?", false)->getContent();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString(trim(chunk_split($advisor->two_factor_secret, 4, ' ')), $html);   // the key, in groups of four
    }

    public function test_the_otpauth_link_names_the_app_and_the_account(): void
    {
        $advisor = User::factory()->advisor()->create(['email' => 'dr.rahim@example.test']);
        $this->service->start($advisor);

        $url = $this->service->otpauthUrl($advisor->fresh());

        $this->assertStringStartsWith('otpauth://totp/MyPetakom:', $url);
        $this->assertStringContainsString('dr.rahim%40example.test', $url);
        $this->assertStringContainsString('secret='.$advisor->fresh()->two_factor_secret, $url);
    }

    public function test_a_wrong_first_code_does_not_turn_it_on(): void
    {
        [$advisor] = [User::factory()->advisor()->create()];
        $this->service->start($advisor);

        $this->actingAs($advisor)->post('/settings/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrorsIn('twoFactor', 'code');

        $this->assertFalse($advisor->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'user.two_factor_enabled']);
    }

    public function test_the_right_first_code_turns_it_on_and_shows_eight_recovery_codes_once(): void
    {
        $advisor = User::factory()->advisor()->create(['name' => 'Dr Rahim']);
        $this->service->start($advisor);

        $response = $this->actingAs($advisor)->post('/settings/two-factor/confirm', ['code' => $this->code($advisor)])->assertOk();

        $codes = $response->viewData('codes');
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $code);
            $response->assertSee($code);
        }
        $response->assertSee('shown only once');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->assertTrue($advisor->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.two_factor_enabled', 'user_id' => $advisor->id]);
    }

    public function test_confirming_twice_or_without_starting_does_nothing(): void
    {
        $advisor = User::factory()->advisor()->create();
        $this->actingAs($advisor)->post('/settings/two-factor/confirm', ['code' => '123456'])->assertRedirect('/settings');
        $this->assertFalse($advisor->fresh()->hasTwoFactorEnabled());

        [$on] = $this->staffWithTwoFactor();
        $secret = $on->two_factor_secret;
        $this->actingAs($on)->post('/settings/two-factor/confirm', ['code' => $this->code($on, 2)])->assertRedirect('/settings');
        $this->assertSame($secret, $on->fresh()->two_factor_secret);
    }

    public function test_starting_again_while_on_does_not_replace_the_secret(): void
    {
        [$on] = $this->staffWithTwoFactor();
        $secret = $on->two_factor_secret;

        $this->actingAs($on)->post('/settings/two-factor', ['password' => 'password'])->assertRedirect('/settings');

        $this->assertSame($secret, $on->fresh()->two_factor_secret);
        $this->assertTrue($on->fresh()->hasTwoFactorEnabled());
    }

    public function test_cancelling_a_setup_clears_it_but_never_turns_off_a_working_one(): void
    {
        $half = User::factory()->advisor()->create();
        $this->service->start($half);
        $this->actingAs($half)->post('/settings/two-factor/cancel')->assertRedirect('/settings');
        $this->assertNull($half->fresh()->two_factor_secret);

        [$on] = $this->staffWithTwoFactor();
        $this->actingAs($on)->post('/settings/two-factor/cancel');
        $this->assertTrue($on->fresh()->hasTwoFactorEnabled());
    }

    // =====================================================================
    // How it is stored
    // =====================================================================

    public function test_the_secret_is_encrypted_and_recovery_codes_are_hashed(): void
    {
        [$user, $codes] = $this->staffWithTwoFactor();

        $rawSecret = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $rawCodes = DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');

        $this->assertNotSame($user->two_factor_secret, $rawSecret);
        $this->assertStringNotContainsString($user->two_factor_secret, $rawSecret);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $rawCodes);
            $this->assertStringNotContainsString(str_replace('-', '', $code), $rawCodes);
        }
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_secret_and_codes_never_leave_the_model_when_it_is_serialised(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
        $this->assertStringNotContainsString($user->two_factor_secret, $user->toJson());
    }

    public function test_the_service_rejects_bad_input(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $this->assertFalse($this->service->verify($user, null));
        $this->assertFalse($this->service->verify($user, 'abcdef'));
        $this->assertFalse($this->service->verify($user, '12345'));
        $this->assertFalse($this->service->verify($user, '1234567'));
        $this->assertFalse($this->service->verify(User::factory()->advisor()->create(), '123456'));   // no secret at all
        $this->assertFalse($this->service->useRecoveryCode($user, ''));
    }

    // =====================================================================
    // Signing in
    // =====================================================================

    public function test_the_password_alone_does_not_log_in_an_account_with_two_factor(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $this->loginWithPassword($user)->assertRedirect('/two-factor-challenge');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertNull($user->fresh()->last_login_at);   // not a login yet
    }

    public function test_the_right_code_finishes_the_login(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->get('/two-factor-challenge')->assertOk()->assertSee('Two-factor check')->assertSee('Authentication code');
        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get('/dashboard')->assertOk();
    }

    public function test_spaces_in_the_code_are_fine(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);
        $code = $this->code($user, 1);

        $this->post('/two-factor-challenge', ['code' => substr($code, 0, 3).' '.substr($code, 3)])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_keeps_the_user_out(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk();   // still waiting for a code
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);
        $code = $this->code($user, 1);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/dashboard');

        $this->post('/logout');
        $this->loginWithPassword($user);

        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_code_used_to_turn_it_on_cannot_be_used_to_sign_in(): void
    {
        $user = User::factory()->advisor()->create();
        $this->service->start($user);
        $setupCode = $this->code($user);
        $this->service->confirm($user, $setupCode);
        $this->loginWithPassword($user);

        $this->post('/two-factor-challenge', ['code' => $setupCode])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_five_wrong_codes_send_the_user_back_to_the_password(): void
    {
        [$user] = $this->staffWithTwoFactor();
        RateLimiter::clear('two-factor:'.$user->id);
        $this->loginWithPassword($user);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect('/login')->assertSessionHasErrors('login');

        // Even the right code no longer works: the pending sign-in is gone.
        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_the_code_page_needs_a_password_step_first(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');
    }

    public function test_the_password_step_expires_after_ten_minutes(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->travel(11)->minutes();

        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_another_browser_session_cannot_use_the_pending_login(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->flushSession();

        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_an_account_deactivated_during_the_second_step_is_refused(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $user->update(['deactivated_at' => now()]);

        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_remember_me_still_works_after_the_second_step(): void
    {
        [$user] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user, remember: true);

        $this->post('/two-factor-challenge', ['code' => $this->code($user, 1)])->assertRedirect('/dashboard');

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_a_wrong_password_never_reaches_the_code_page(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $this->post('/login', ['login' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('login');

        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }

    public function test_accounts_without_two_factor_log_in_exactly_as_before(): void
    {
        $student = User::factory()->create();
        $advisor = User::factory()->advisor()->create();

        $this->post('/login', ['login' => $student->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($student);
        $this->post('/logout');
        $this->post('/login', ['login' => $advisor->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_staff_id_login_also_goes_through_the_code_step(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $this->post('/login', ['login' => strtolower($user->staff_id), 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    // =====================================================================
    // Recovery codes
    // =====================================================================

    public function test_a_recovery_code_signs_in_once(): void
    {
        [$user, $codes] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect('/dashboard')->assertSessionHas('toast', 'Recovery code used. 7 left.');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, $this->service->recoveryCodesLeft($user->fresh()));

        $this->post('/logout');
        $this->loginWithPassword($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_recovery_codes_ignore_case_hyphen_and_spaces(): void
    {
        [$user, $codes] = $this->staffWithTwoFactor();
        $this->loginWithPassword($user);

        $this->post('/two-factor-challenge', ['recovery_code' => '  '.strtoupper(str_replace('-', '', $codes[3])).' '])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_one_persons_recovery_code_does_not_work_for_another(): void
    {
        [, $codes] = $this->staffWithTwoFactor();
        [$other] = $this->staffWithTwoFactor('coordinator');
        $this->loginWithPassword($other);

        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_using_a_recovery_code_is_written_to_the_activity_log_and_warns_when_few_are_left(): void
    {
        [$user, $codes] = $this->staffWithTwoFactor();

        // Use six, leaving two.
        foreach (array_slice($codes, 0, 6) as $code) {
            $this->loginWithPassword($user);
            $this->post('/two-factor-challenge', ['recovery_code' => $code]);
            $this->post('/logout');
        }

        $this->loginWithPassword($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[6]])->assertSessionHas('toast', 'Recovery code used. Only 1 left: make new ones in Settings.');

        $this->assertSame(7, ActivityLog::where('action', 'user.two_factor_recovery_used')->count());
    }

    public function test_making_new_codes_needs_the_password_and_retires_the_old_ones(): void
    {
        [$user, $old] = $this->staffWithTwoFactor();

        $this->actingAs($user)->post('/settings/two-factor/recovery-codes', ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertSame(8, $this->service->recoveryCodesLeft($user->fresh()));

        $response = $this->actingAs($user)->post('/settings/two-factor/recovery-codes', ['password' => 'password'])->assertOk();
        $new = $response->viewData('codes');

        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($old, $new));
        $this->assertFalse($this->service->useRecoveryCode($user->fresh(), $old[0]));
        $this->assertTrue($this->service->useRecoveryCode($user->fresh(), $new[0]));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.two_factor_codes_renewed']);
    }

    // =====================================================================
    // Turning it off, and getting back in
    // =====================================================================

    public function test_turning_it_off_needs_the_password(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $this->actingAs($user)->delete('/settings/two-factor', ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->delete('/settings/two-factor', ['password' => 'password'])->assertRedirect('/settings');

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.two_factor_disabled', 'user_id' => $user->id]);

        auth()->logout();
        $this->loginWithPassword($user)->assertRedirect('/dashboard');   // password alone again
    }

    public function test_the_settings_page_reflects_each_state(): void
    {
        [$on] = $this->staffWithTwoFactor();

        $this->actingAs($on)->get('/settings')->assertSee('8 recovery codes left')->assertSee('Make new recovery codes')->assertSee('Turn off')->assertDontSee('Set up two-factor');
    }

    public function test_a_coordinator_can_reset_a_locked_out_colleague(): void
    {
        [$locked] = $this->staffWithTwoFactor();
        $coordinator = User::factory()->coordinator()->create();

        $this->actingAs($coordinator)->get("/users/{$locked->id}/edit")->assertSee('Reset two-factor');
        $this->actingAs($coordinator)->post("/users/{$locked->id}/two-factor/reset")->assertRedirect();

        $this->assertFalse($locked->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.two_factor_reset', 'user_id' => $coordinator->id]);

        auth()->logout();
        $this->loginWithPassword($locked)->assertRedirect('/dashboard');
    }

    public function test_the_reset_button_only_shows_when_it_is_on_and_only_coordinators_can_use_it(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $plain = User::factory()->advisor()->create();
        [$locked] = $this->staffWithTwoFactor();

        $this->actingAs($coordinator)->get("/users/{$plain->id}/edit")->assertDontSee('Reset two-factor');
        $this->actingAs($coordinator)->post("/users/{$plain->id}/two-factor/reset")->assertRedirect();
        $this->assertDatabaseMissing('activity_logs', ['action' => 'user.two_factor_reset']);   // nothing to reset: nothing logged

        $this->actingAs(User::factory()->advisor()->create())->post("/users/{$locked->id}/two-factor/reset")->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/users/{$locked->id}/two-factor/reset")->assertForbidden();
        $this->assertTrue($locked->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_artisan_command_turns_it_off_for_a_locked_out_coordinator(): void
    {
        [$user] = $this->staffWithTwoFactor('coordinator');

        $this->artisan('two-factor:disable', ['login' => strtoupper($user->email)])->expectsOutputToContain('is now off')->assertSuccessful();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->artisan('two-factor:disable', ['login' => $user->staff_id])->expectsOutputToContain('Nothing to do')->assertSuccessful();
        $this->artisan('two-factor:disable', ['login' => 'nobody@example.test'])->expectsOutputToContain('No account matches')->assertFailed();
    }

    public function test_profile_shows_the_state_for_staff_only(): void
    {
        [$on] = $this->staffWithTwoFactor();

        $this->actingAs($on)->get('/profile')->assertSee('Two-factor');
        $this->actingAs(User::factory()->advisor()->create())->get('/profile')->assertSee('Two-factor');
        $this->actingAs(User::factory()->create())->get('/profile')->assertDontSee('Two-factor');
    }

    public function test_activity_log_colours_for_the_new_actions(): void
    {
        $this->assertSame('green', (new ActivityLog(['action' => 'user.two_factor_enabled']))->color);
        $this->assertSame('red', (new ActivityLog(['action' => 'user.two_factor_disabled']))->color);
        $this->assertSame('red', (new ActivityLog(['action' => 'user.two_factor_reset']))->color);
    }

    public function test_the_panel_that_reopens_after_a_wrong_password_cannot_inject_script(): void
    {
        [$user] = $this->staffWithTwoFactor();

        $html = $this->actingAs($user)->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('twoFactor', new \Illuminate\Support\MessageBag(['password' => ['x']])), '_old_input' => ['panel' => "x'}; alert(1); ({'"]])
            ->get('/settings')->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
