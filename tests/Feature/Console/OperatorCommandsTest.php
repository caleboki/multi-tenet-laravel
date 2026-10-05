<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperatorCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_grant_operator_lets_an_existing_user_operate_the_platform(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.test']);

        $this->artisan('app:grant-operator', ['email' => 'ADA@example.test'])
            ->expectsOutputToContain('ada@example.test can now operate the platform.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_platform_operator);
    }

    public function test_revoke_operator_removes_the_permission(): void
    {
        $user = User::factory()->operator()->create(['email' => 'ada@example.test']);

        $this->artisan('app:revoke-operator', ['email' => 'ada@example.test'])
            ->expectsOutputToContain('ada@example.test can no longer operate the platform.')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->is_platform_operator);
    }

    public function test_grant_operator_fails_for_an_unknown_email(): void
    {
        $this->artisan('app:grant-operator', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('No user has the email address nobody@example.test.')
            ->assertFailed();
    }

    public function test_revoke_operator_fails_for_an_unknown_email(): void
    {
        $this->artisan('app:revoke-operator', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('No user has the email address nobody@example.test.')
            ->assertFailed();
    }
}
