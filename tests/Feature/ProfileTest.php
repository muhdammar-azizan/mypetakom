<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_for_every_role(): void
    {
        foreach ([User::factory(), User::factory()->advisor(), User::factory()->coordinator()] as $factory) {
            $this->actingAs($factory->create())->get('/profile')->assertOk();
        }
    }

    public function test_student_sees_merit_qr_but_staff_does_not(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertSee('My Merit QR');
        $this->actingAs(User::factory()->advisor()->create())->get('/profile')->assertDontSee('My Merit QR');
    }

    public function test_student_can_update_contact_details_but_not_locked_fields(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'programme' => 'BCS']);
        $email = $user->email;

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'programme' => 'Other',
            'phone' => '011-2345 6789',
            'emergency_name' => 'Parent',
            'emergency_phone' => '013-987 6543',
            'address' => 'Kuantan, Pahang',
            'alt_email' => 'me@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('011-2345 6789', $user->phone);
        $this->assertSame('Parent', $user->emergency_name);
        $this->assertSame('Original Name', $user->name);
        $this->assertSame($email, $user->email);
        $this->assertSame('BCS', $user->programme);
    }

    public function test_staff_can_update_name_email_and_department(): void
    {
        $user = User::factory()->coordinator()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '09-424 1122',
            'programme' => 'Faculty of Computing',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Faculty of Computing', $user->programme);
    }

    public function test_password_can_be_changed_with_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_avatar_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png')])
            ->assertSessionHasNoErrors();

        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->delete('/profile/avatar');

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_avatar_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_last_login_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->refresh()->last_login_at);
    }
}
