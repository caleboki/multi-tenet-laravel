<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Sign in');
    }

    public function test_login_with_mixed_case_email_signs_in_the_lowercase_stored_user(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.test']);

        $response = $this->post(route('login.store'), [
            'email' => 'Ada@Example.TEST',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_and_user_stays_signed_out(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sixth_login_attempt_within_a_minute_returns_429(): void
    {
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'not-the-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
        }

        $response = $this->post(route('login.store'), $credentials);

        $response->assertTooManyRequests();
    }

    public function test_logout_signs_the_user_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guest_opening_dashboard_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
