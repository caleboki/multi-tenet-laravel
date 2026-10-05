<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationSuspended;
use Closure;
use Database\Factories\MembershipFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuspendOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_suspending_shuts_members_out_and_tells_the_active_administrators(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        $administrator = $this->memberOf($organization, Membership::factory()->administrator()->active());
        $volunteer = $this->memberOf($organization, Membership::factory()->volunteer()->active());
        $this->memberOf($organization, Membership::factory()->administrator()->inactive());
        Notification::fake();

        $response = $this->actingAs($operator)->post(route('operator.organizations.suspend', $organization));

        $response->assertRedirect(route('operator.organizations.show', $organization));
        $response->assertSessionHas('status', 'Suspended River Cleanup.');
        $this->assertSame(OrganizationStatus::Suspended, $organization->fresh()->status);

        Notification::assertSentTimes(OrganizationSuspended::class, 1);
        Notification::assertSentTo(
            $administrator,
            OrganizationSuspended::class,
            fn (OrganizationSuspended $notification): bool => $notification->organization->is($organization),
        );

        $this->actingAs($volunteer)->get(route('orgs.show', $organization))
            ->assertForbidden()
            ->assertSeeText('River Cleanup is suspended');
    }

    public function test_reinstating_gives_members_their_access_back(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->suspended()->create(['name' => 'River Cleanup']);
        $volunteer = $this->memberOf($organization, Membership::factory()->volunteer()->active());
        Notification::fake();

        $response = $this->actingAs($operator)->post(route('operator.organizations.reinstate', $organization));

        $response->assertRedirect(route('operator.organizations.show', $organization));
        $response->assertSessionHas('status', 'Reinstated River Cleanup.');
        $this->assertSame(OrganizationStatus::Active, $organization->fresh()->status);
        Notification::assertNothingSent();

        $this->actingAs($volunteer)->get(route('orgs.show', $organization))->assertOk();
    }

    public function test_member_of_a_suspended_organization_keeps_access_to_their_other_organizations(): void
    {
        $operator = User::factory()->operator()->create();
        $suspended = Organization::factory()->active()->create();
        $other = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $member = User::factory()->create();
        Membership::factory()->for($suspended)->for($member)->administrator()->active()->create();
        Membership::factory()->for($other)->for($member)->volunteer()->active()->create();
        Notification::fake();
        $this->actingAs($operator)->post(route('operator.organizations.suspend', $suspended));

        $response = $this->actingAs($member)->get(route('orgs.show', $other));

        $response->assertSeeText('Food Bank North');
    }

    /**
     * @return array<string, array{string, Closure(): Organization}>
     */
    public static function changesThatDoNotApply(): array
    {
        return [
            'suspend a pending organization' => ['operator.organizations.suspend', fn (): Organization => Organization::factory()->pending()->create()],
            'suspend a suspended organization' => ['operator.organizations.suspend', fn (): Organization => Organization::factory()->suspended()->create()],
            'reinstate an active organization' => ['operator.organizations.reinstate', fn (): Organization => Organization::factory()->active()->create()],
            'reinstate a rejected organization' => ['operator.organizations.reinstate', fn (): Organization => Organization::factory()->rejected()->create()],
        ];
    }

    /**
     * @param  Closure(): Organization  $createOrganization
     */
    #[DataProvider('changesThatDoNotApply')]
    public function test_change_that_does_not_apply_to_the_current_status_gets_409(string $routeName, Closure $createOrganization): void
    {
        $operator = User::factory()->operator()->create();
        $organization = $createOrganization();
        $status = $organization->status;
        Notification::fake();

        $response = $this->actingAs($operator)->post(route($routeName, $organization));

        $response->assertConflict();
        $this->assertSame($status, $organization->fresh()->status);
        Notification::assertNothingSent();
    }

    private function memberOf(Organization $organization, MembershipFactory $membership): User
    {
        $user = User::factory()->create();
        $membership->for($organization)->for($user)->create();

        return $user;
    }
}
