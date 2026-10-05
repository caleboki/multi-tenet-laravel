<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unverified_user_sees_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('Verify your email address');
    }

    public function test_signed_link_verifies_the_email_and_dispatches_verified(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake([Verified::class]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($user));
    }

    public function test_link_with_wrong_hash_does_not_verify_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.test'),
        ]);

        $response = $this->actingAs($user)->get($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resending_the_verification_email_is_limited_to_six_per_minute(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        }

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }
}
