<?php

namespace Tests\Feature\JoinRequests;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestReceived;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationReleaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verifying_sends_each_pending_request_to_the_active_administrators_of_its_organization(): void
    {
        $user = User::factory()->create();
        $foodBank = Organization::factory()->active()->create();
        $riverCleanup = Organization::factory()->active()->create();
        $alreadyJoined = Organization::factory()->active()->create();
        Membership::factory()->for($foodBank)->for($user)->volunteer()->pending()->create();
        Membership::factory()->for($riverCleanup)->for($user)->volunteer()->pending()->create();
        Membership::factory()->for($alreadyJoined)->for($user)->volunteer()->active()->create();
        $foodBankAdministrators = [$this->administratorOf($foodBank), $this->administratorOf($foodBank)];
        $riverCleanupAdministrator = $this->administratorOf($riverCleanup);
        $this->administratorOf($alreadyJoined);
        Membership::factory()->for($foodBank)->volunteer()->active()->create();
        Membership::factory()->for($foodBank)->administrator()->inactive()->create();
        Notification::fake();

        event(new Verified($user));

        Notification::assertSentTimes(JoinRequestReceived::class, 3);
        Notification::assertSentTo(
            $foodBankAdministrators,
            JoinRequestReceived::class,
            fn (JoinRequestReceived $notification): bool => $notification->organization->is($foodBank)
                && $notification->requester->is($user),
        );
        Notification::assertSentTo(
            $riverCleanupAdministrator,
            JoinRequestReceived::class,
            fn (JoinRequestReceived $notification): bool => $notification->organization->is($riverCleanup),
        );
    }

    public function test_verifying_without_pending_requests_sends_nothing(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create();
        Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();
        $this->administratorOf($organization);
        Notification::fake();

        event(new Verified($user));

        Notification::assertNothingSent();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
