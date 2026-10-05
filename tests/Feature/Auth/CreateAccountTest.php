<?php

namespace Tests\Feature\Auth;

use App\Actions\Accounts\CreateAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_account_with_a_lowercased_email_hashed_password_phone_and_adult_confirmation(): void
    {
        $this->travelTo('2026-10-05 12:00:00');

        $user = app(CreateAccount::class)->handle([
            'name' => 'Ada Lovelace',
            'email' => '  Ada@Example.TEST ',
            'password' => 'secret-password',
            'phone' => '+44 20 7946 0000',
        ], adultConfirmed: true, verified: false);

        $this->assertModelExists($user);
        $this->assertSame('ada@example.test', $user->email);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('+44 20 7946 0000', $user->phone);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('2026-10-05 12:00:00', $user->adult_confirmed_at->toDateTimeString());
    }

    public function test_refuses_to_create_an_account_without_adult_confirmation(): void
    {
        try {
            app(CreateAccount::class)->handle([
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.test',
                'password' => 'secret-password',
            ], adultConfirmed: false, verified: false);

            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['adult_confirmation' => ['You must confirm you are 18 or older. This platform is for adults only.']],
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount(User::class, 0);
    }

    public function test_unverified_account_dispatches_registered_and_stays_unverified(): void
    {
        Event::fake([Registered::class]);

        $user = app(CreateAccount::class)->handle([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'secret-password',
        ], adultConfirmed: true, verified: false);

        $this->assertFalse($user->hasVerifiedEmail());
        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
    }

    public function test_verified_account_is_marked_verified_and_does_not_dispatch_registered(): void
    {
        Event::fake([Registered::class]);

        $user = app(CreateAccount::class)->handle([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'secret-password',
        ], adultConfirmed: true, verified: true);

        $this->assertTrue($user->hasVerifiedEmail());
        Event::assertNotDispatched(Registered::class);
    }
}
