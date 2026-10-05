<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_page_shows_the_users_details_with_the_email_read_only(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+44 20 7946 0000',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertSee('value="Ada Lovelace"', false);
        $response->assertSee('value="+44 20 7946 0000"', false);
        $response->assertSeeText('ada@example.test');
        $response->assertDontSee('name="email"', false);
    }

    public function test_updates_name_and_phone_and_ignores_the_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.test']);

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('user-profile-information.update'), [
            'name' => 'Ada King',
            'phone' => '0123 456 789',
            'email' => 'someone-else@example.test',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'profile-information-updated');

        $user->refresh();
        $this->assertSame('Ada King', $user->name);
        $this->assertSame('0123 456 789', $user->phone);
        $this->assertSame('ada@example.test', $user->email);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_rejects_a_missing_name_and_an_invalid_phone(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'phone' => null]);

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('user-profile-information.update'), [
            'name' => '',
            'phone' => 'call me',
        ]);

        $response->assertSessionHasErrorsIn('updateProfileInformation', [
            'name' => 'The name field is required.',
            'phone' => 'The phone number may contain only digits, spaces, and + ( ) -.',
        ]);
        $user->refresh();
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNull($user->phone);
    }

    public function test_changes_the_password_when_the_current_password_is_correct(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ]);

        $response->assertSessionHas('status', 'password-updated');
        $this->assertTrue(Hash::check('new-secret-password', $user->fresh()->password));
    }

    public function test_refuses_to_change_the_password_without_the_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', [
            'current_password' => 'The provided password does not match your current password.',
        ]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
