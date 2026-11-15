<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    private const SAME_MESSAGE = 'If an account matches, we have sent a link to its e-mail address. Check your inbox and spam folder.';

    private function mailDown(): void
    {
        Event::listen(NotificationSending::class, fn () => throw new RuntimeException('smtp is down'));
    }

    // =====================================================================
    // Notices: in-app and e-mail follow the Settings
    // =====================================================================

    public function test_channels_follow_the_two_settings(): void
    {
        $cases = [
            [true, true, ['database', 'mail']],
            [true, false, ['database']],
            [false, true, ['mail']],
            [false, false, []],
        ];

        foreach ($cases as [$inApp, $email, $expected]) {
            $user = User::factory()->make(['notify_in_app' => $inApp, 'notify_email' => $email]);

            $this->assertSame($expected, (new Notice('T', 'B', '/x'))->via($user));
        }
    }

    public function test_send_uses_the_users_channels_and_skips_users_with_both_off(): void
    {
        Notification::fake();
        $both = User::factory()->create();
        $mailOnly = User::factory()->create(['notify_in_app' => false]);
        $none = User::factory()->create(['notify_in_app' => false, 'notify_email' => false]);

        Notice::send([$both, $mailOnly, $none], 'Hello', 'Body', '/dashboard');

        Notification::assertSentTo($both, Notice::class, fn ($n, array $channels) => $channels === ['database', 'mail']);
        Notification::assertSentTo($mailOnly, Notice::class, fn ($n, array $channels) => $channels === ['mail']);
        Notification::assertNotSentTo($none, Notice::class);
    }

    public function test_the_notice_e_mail_has_title_body_button_and_the_settings_link(): void
    {
        $user = User::factory()->create(['name' => 'Aisyah']);

        $mail = (new Notice('Membership approved', 'You are now a member.', 'https://petakom.test/membership'))->toMail($user);

        $this->assertSame('Membership approved', $mail->subject);
        $this->assertStringContainsString('Aisyah', $mail->greeting);
        $this->assertSame('Open in MyPetakom', $mail->actionText);
        $this->assertSame('https://petakom.test/membership', $mail->actionUrl);
        $this->assertStringContainsString('You are now a member', implode(' ', $mail->introLines));
        $this->assertStringContainsString(route('settings.edit'), implode(' ', $mail->outroLines));
    }

    public function test_text_typed_by_users_cannot_become_links_or_html_in_the_e_mail(): void
    {
        $user = User::factory()->create(['name' => '[Click me](http://evil.test)']);

        $html = (string) (new Notice('Title', '<script>alert(1)</script> [Pay now](http://evil.test/pay)', 'https://petakom.test/x'))->toMail($user)->render();

        $this->assertStringNotContainsString('href="http://evil.test', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('https://petakom.test/x', $html);   // the real button still works
    }

    public function test_a_real_action_sends_the_e_mail_and_the_bell_notice(): void
    {
        Notification::fake();
        $student = User::factory()->create();
        $membership = Membership::factory()->for($student)->create();

        $this->actingAs(User::factory()->coordinator()->create())->post(route('members.approve', $membership))->assertRedirect();

        Notification::assertSentTo($student, Notice::class, fn ($n, array $channels) => $n->title === 'Membership approved' && in_array('mail', $channels, true) && in_array('database', $channels, true));
    }

    public function test_turning_e_mail_off_stops_only_the_e_mail(): void
    {
        Notification::fake();
        $student = User::factory()->create(['notify_email' => false]);
        $membership = Membership::factory()->for($student)->create();

        $this->actingAs(User::factory()->coordinator()->create())->post(route('members.approve', $membership));

        Notification::assertSentTo($student, Notice::class, fn ($n, array $channels) => $channels === ['database']);
    }

    public function test_a_mail_server_failure_never_breaks_the_action(): void
    {
        $student = User::factory()->create();
        $membership = Membership::factory()->for($student)->create();
        $this->mailDown();

        $this->actingAs(User::factory()->coordinator()->create())->post(route('members.approve', $membership))
            ->assertRedirect()->assertSessionHas('toast', 'Membership approved.');

        $this->assertSame('approved', $membership->fresh()->status);
    }

    // =====================================================================
    // Forgot / reset password
    // =====================================================================

    public function test_forgot_password_page_asks_for_id_or_email(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot password?')->assertSee('Student ID / Staff ID / Email');
    }

    public function test_a_reset_link_is_sent_for_email_student_id_or_staff_id(): void
    {
        Notification::fake();
        $student = User::factory()->create(['email' => 'aisyah@example.test', 'student_id' => 'CB11111']);
        $staff = User::factory()->advisor()->create(['staff_id' => 'S0042']);

        $this->from('/forgot-password')->post('/forgot-password', ['login' => 'AISYAH@example.test'])->assertSessionHas('status', self::SAME_MESSAGE);
        $this->from('/forgot-password')->post('/forgot-password', ['login' => 'cb11111']);
        $this->from('/forgot-password')->post('/forgot-password', ['login' => 's0042']);

        Notification::assertSentTo($student, ResetPassword::class);
        Notification::assertSentTo($staff, ResetPassword::class);
        Notification::assertCount(3 - 1);   // the second request for the same student is throttled by the broker (one link per minute)
    }

    public function test_the_answer_is_identical_for_unknown_and_deactivated_accounts(): void
    {
        Notification::fake();
        $deactivated = User::factory()->create(['email' => 'gone@example.test', 'deactivated_at' => now()]);

        $this->from('/forgot-password')->post('/forgot-password', ['login' => 'nobody@example.test'])
            ->assertSessionHas('status', self::SAME_MESSAGE)->assertSessionHasNoErrors();
        $this->from('/forgot-password')->post('/forgot-password', ['login' => 'CB00000'])
            ->assertSessionHas('status', self::SAME_MESSAGE)->assertSessionHasNoErrors();
        $this->from('/forgot-password')->post('/forgot-password', ['login' => 'gone@example.test'])
            ->assertSessionHas('status', self::SAME_MESSAGE)->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertNotNull($deactivated);
    }

    public function test_forgot_password_needs_a_value_and_is_rate_limited(): void
    {
        $this->post('/forgot-password', [])->assertSessionHasErrors('login');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['login' => 'x'.$i.'@example.test']);
        }

        $this->post('/forgot-password', ['login' => 'x@example.test'])->assertStatus(429);
    }

    public function test_a_failing_mail_server_still_gets_the_same_answer(): void
    {
        $user = User::factory()->create(['email' => 'aisyah@example.test']);
        $this->mailDown();

        $this->from('/forgot-password')->post('/forgot-password', ['login' => $user->email])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', self::SAME_MESSAGE);
    }

    public function test_the_reset_e_mail_is_branded_and_links_to_the_reset_page(): void
    {
        Notification::fake();
        $user = User::factory()->create(['name' => 'Aisyah', 'email' => 'aisyah@example.test']);

        $this->post('/forgot-password', ['login' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);
            $this->assertSame('Reset your MyPetakom password', $mail->subject);
            $this->assertStringContainsString('/reset-password/'.$n->token, $mail->actionUrl);
            $this->assertStringContainsString('email=aisyah%40example.test', $mail->actionUrl);
            $this->assertStringContainsString('ignore this e-mail', implode(' ', $mail->outroLines));

            $html = (string) $mail->render();
            $this->assertStringContainsString('MyPetakom', $html);
            $this->assertStringNotContainsString('laravel.com/img', $html);   // no Laravel logo in our header

            return true;
        });
    }

    public function test_the_whole_reset_flow_ends_with_a_working_new_password(): void
    {
        $user = User::factory()->create(['email' => 'aisyah@example.test', 'student_id' => 'CB11111']);
        $token = Password::createToken($user);

        $this->get("/reset-password/{$token}?email=aisyah%40example.test")->assertOk()->assertSee('Choose a new password');

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'a-brand-new-pass-1', 'password_confirmation' => 'a-brand-new-pass-1',
        ])->assertRedirect('/login');

        $this->post('/login', ['login' => 'CB11111', 'password' => 'a-brand-new-pass-1'])->assertRedirect('/dashboard');
    }

    public function test_a_bad_token_does_not_reset_anything(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'wrong', 'email' => $user->email, 'password' => 'a-brand-new-pass-1', 'password_confirmation' => 'a-brand-new-pass-1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(\Hash::check('password', $user->fresh()->password));
    }

    // =====================================================================
    // E-mail verification (a reminder, never a lock)
    // =====================================================================

    public function test_signing_up_sends_a_verification_e_mail_and_still_logs_the_student_in(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'New Student', 'email' => 'new@example.test', 'student_id' => 'cb90000', 'programme' => 'BCS',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'new@example.test')->first();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_mail_failure_does_not_break_signing_up(): void
    {
        $this->mailDown();

        $this->post('/register', [
            'name' => 'New Student', 'email' => 'new@example.test', 'student_id' => 'cb90000', 'programme' => 'BCS',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);
    }

    public function test_unverified_users_see_a_banner_but_can_use_the_whole_app(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Your e-mail address is not verified.')->assertSee('Send verification link');
        $this->actingAs($user)->get('/calendar')->assertOk();
        $this->actingAs($user)->get('/settings')->assertOk();

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('not verified');
    }

    public function test_resending_the_link_works_and_is_skipped_for_verified_users(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/dashboard')->post('/email/verification-notification')
            ->assertRedirect('/dashboard')->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);

        $verified = User::factory()->create();
        $this->actingAs($verified)->post('/email/verification-notification')->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_the_banner_confirms_that_a_link_was_sent(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withSession(['status' => 'verification-link-sent'])->get('/dashboard')->assertSee('Link sent. Check your inbox.');
    }

    public function test_the_signed_link_verifies_the_address_and_shows_a_thank_you(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get($response->headers->get('Location'))->assertOk()->assertSee('E-mail verified. Thank you.')->assertDontSee('not verified');
    }

    public function test_a_wrong_or_unsigned_link_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $bad = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('someone-else@example.test')]);
        $this->actingAs($user)->get($bad)->assertForbidden();
        $this->actingAs($user)->get("/verify-email/{$user->id}/".sha1($user->email))->assertForbidden();   // not signed

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_verify_page_is_restyled_and_names_the_address(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'aisyah@example.test']);

        $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('Verify your e-mail')->assertSee('aisyah@example.test')->assertSee('Continue to dashboard');
    }

    public function test_staff_changing_their_e_mail_must_verify_the_new_address(): void
    {
        Notification::fake();
        $advisor = User::factory()->advisor()->create(['email' => 'old@example.test']);

        $this->actingAs($advisor)->patch('/profile', ['name' => $advisor->name, 'email' => 'new@example.test'])->assertRedirect();

        $advisor->refresh();
        $this->assertSame('new@example.test', $advisor->email);
        $this->assertFalse($advisor->hasVerifiedEmail());
        Notification::assertSentTo($advisor, VerifyEmail::class);
    }

    public function test_saving_a_profile_without_changing_the_e_mail_keeps_it_verified(): void
    {
        Notification::fake();
        $advisor = User::factory()->advisor()->create();

        $this->actingAs($advisor)->patch('/profile', ['name' => 'Renamed', 'email' => $advisor->email]);

        $this->assertTrue($advisor->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_accounts_made_by_the_coordinator_start_unverified(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->post('/users', [
            'role' => 'advisor', 'name' => 'New Advisor', 'email' => 'adv@example.test', 'staff_id' => 'S9000', 'password' => 'Password123!',
        ])->assertRedirect();

        $this->assertFalse(User::where('email', 'adv@example.test')->first()->hasVerifiedEmail());
    }

    public function test_profile_shows_the_verification_status(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertSee('Verified');
        $this->actingAs(User::factory()->unverified()->create())->get('/profile')->assertSee('Not verified');
    }

    // =====================================================================
    // Mail look
    // =====================================================================

    public function test_the_mail_layout_uses_our_name_and_colours(): void
    {
        $user = User::factory()->create();

        $html = (string) (new Notice('Hi', 'Body', 'https://petakom.test/x'))->toMail($user)->render();

        $this->assertStringContainsString('MyPetakom', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
        $this->assertStringContainsString('#1f48a0', $html);   // brand blue on the button
    }
}
