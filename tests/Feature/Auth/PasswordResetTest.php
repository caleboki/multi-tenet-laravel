<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENERIC_STATUS = 'If an account exists for that email, we have sent a password reset link.';

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Forgot your password?');
    }

    public function test_reset_link_request_for_existing_account_sends_notification_and_shows_generic_message(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $response = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', self::GENERIC_STATUS);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_request_for_unknown_email_shows_the_same_message_and_sends_nothing(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nobody@example.test']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', self::GENERIC_STATUS);
        $response->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_reset_password_page_renders_with_the_token(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'some-token', 'email' => 'ada@example.test']));

        $response->assertOk();
        $response->assertSee('some-token');
    }

    public function test_valid_token_sets_the_new_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }
}
