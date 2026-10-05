<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationRequested;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationRequestNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verified_requester_notifies_every_operator_straight_away(): void
    {
        $operators = User::factory()->count(2)->operator()->create();
        $bystander = User::factory()->create();
        $requester = User::factory()->create();
        Notification::fake();

        $this->actingAs($requester)->post(route('organization-requests.store'), [
            'organization_name' => 'Tool Library',
            'contact_email' => 'hello@toollibrary.test',
        ]);

        Notification::assertSentTo(
            $operators,
            OrganizationRequested::class,
            fn (OrganizationRequested $notification): bool => $notification->organization->name === 'Tool Library'
                && $notification->requester->is($requester),
        );
        Notification::assertNothingSentTo($bystander);
    }

    public function test_unverified_requester_notifies_no_operator(): void
    {
        $operator = User::factory()->operator()->create();
        Notification::fake();

        $this->post(route('organization-requests.store'), [
            'name' => 'Sam Starter',
            'email' => 'sam@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'adult_confirmation' => '1',
            'organization_name' => 'Tool Library',
            'contact_email' => 'hello@toollibrary.test',
        ]);

        Notification::assertNothingSentTo($operator);
    }

    public function test_verifying_sends_each_pending_organization_request_to_every_operator(): void
    {
        $operators = User::factory()->count(2)->operator()->create();
        $requester = User::factory()->create();
        $pending = Organization::factory()->pending()->for($requester, 'requester')->create();
        Organization::factory()->rejected()->for($requester, 'requester')->create();
        Organization::factory()->pending()->create();
        Notification::fake();

        event(new Verified($requester));

        Notification::assertSentTimes(OrganizationRequested::class, 2);
        Notification::assertSentTo(
            $operators,
            OrganizationRequested::class,
            fn (OrganizationRequested $notification): bool => $notification->organization->is($pending),
        );
    }
}
