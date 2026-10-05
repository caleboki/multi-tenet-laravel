<?php

namespace Tests\Feature\Organizations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationApproved;
use App\Notifications\OrganizationRejected;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_approving_activates_the_organization_and_makes_the_requester_its_administrator(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $operator = User::factory()->operator()->create();
        $requester = User::factory()->create();
        $organization = Organization::factory()->pending()->for($requester, 'requester')->create(['name' => 'Tool Library']);
        Notification::fake();

        $response = $this->actingAs($operator)->post(route('operator.organizations.approve', $organization));

        $response->assertRedirect(route('operator.organizations.index'));
        $response->assertSessionHas('status', 'Approved Tool Library.');

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertSame(40, strlen($organization->signup_token));

        $membership = $requester->membershipIn($organization);
        $this->assertSame(MembershipRole::Administrator, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame('2026-10-05 12:00:00', $membership->joined_at->toDateTimeString());

        Notification::assertSentTo(
            $requester,
            OrganizationApproved::class,
            fn (OrganizationApproved $notification): bool => $notification->organization->is($organization),
        );
    }

    public function test_rejecting_records_the_reason_and_tells_the_requester(): void
    {
        $operator = User::factory()->operator()->create();
        $requester = User::factory()->create();
        $organization = Organization::factory()->pending()->for($requester, 'requester')->create(['name' => 'Tool Library']);
        Notification::fake();

        $response = $this->actingAs($operator)->post(route('operator.organizations.reject', $organization), [
            'reason' => 'We could not confirm the organization exists.',
        ]);

        $response->assertRedirect(route('operator.organizations.index'));
        $response->assertSessionHas('status', 'Rejected Tool Library.');

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Rejected, $organization->status);
        $this->assertSame('We could not confirm the organization exists.', $organization->rejection_reason);
        $this->assertSame(0, $organization->memberships()->count());

        Notification::assertSentTo(
            $requester,
            OrganizationRejected::class,
            fn (OrganizationRejected $notification): bool => $notification->organization->is($organization)
                && $notification->organization->rejection_reason === 'We could not confirm the organization exists.',
        );
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->pending()->create();
        Notification::fake();

        $response = $this->actingAs($operator)->post(route('operator.organizations.reject', $organization), ['reason' => '']);

        $response->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        $this->assertSame(OrganizationStatus::Pending, $organization->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_rejected_name_can_be_requested_again(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->pending()->create(['name' => 'Tool Library']);
        Notification::fake();
        $this->actingAs($operator)->post(route('operator.organizations.reject', $organization), ['reason' => 'Duplicate request.']);
        $newRequester = User::factory()->create();

        $response = $this->actingAs($newRequester)->post(route('organization-requests.store'), [
            'organization_name' => 'Tool Library',
            'contact_email' => 'hello@toollibrary.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(OrganizationStatus::Pending, Organization::whereBelongsTo($newRequester, 'requester')->sole()->status);
    }

    /**
     * @return array<string, array{string, Closure(): Organization}>
     */
    public static function reviewsOfOrganizationsThatAreNotPending(): array
    {
        return [
            'approve an active organization' => ['operator.organizations.approve', fn (): Organization => Organization::factory()->active()->create()],
            'approve a rejected organization' => ['operator.organizations.approve', fn (): Organization => Organization::factory()->rejected()->create()],
            'reject an active organization' => ['operator.organizations.reject', fn (): Organization => Organization::factory()->active()->create()],
        ];
    }

    /**
     * @param  Closure(): Organization  $createOrganization
     */
    #[DataProvider('reviewsOfOrganizationsThatAreNotPending')]
    public function test_reviewing_an_organization_that_is_not_pending_gets_409(string $routeName, Closure $createOrganization): void
    {
        $operator = User::factory()->operator()->create();
        $organization = $createOrganization();
        $status = $organization->status;
        Notification::fake();

        $response = $this->actingAs($operator)->post(route($routeName, $organization), ['reason' => 'Too late.']);

        $response->assertConflict();
        $this->assertSame($status, $organization->fresh()->status);
        $this->assertSame(0, $organization->memberships()->count());
        Notification::assertNothingSent();
    }
}
