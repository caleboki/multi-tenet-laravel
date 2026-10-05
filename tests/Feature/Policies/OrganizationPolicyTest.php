<?php

namespace Tests\Feature\Policies;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_view_allows_an_active_member_of_an_active_organization(): void
    {
        [$user, $organization] = $this->memberOf(OrganizationStatus::Active, MembershipRole::Volunteer, MembershipStatus::Active);

        $result = Gate::forUser($user)->inspect('view', $organization);

        $this->assertTrue($result->allowed());
    }

    public function test_view_denies_an_active_member_of_a_suspended_organization_with_the_suspended_code(): void
    {
        [$user, $organization] = $this->memberOf(OrganizationStatus::Suspended, MembershipRole::Administrator, MembershipStatus::Active);

        $result = Gate::forUser($user)->inspect('view', $organization);

        $this->assertTrue($result->denied());
        $this->assertSame('organization-suspended', $result->code());
    }

    /**
     * @return array<string, array{OrganizationStatus, MembershipStatus}>
     */
    public static function combinationsDeniedAsNotFound(): array
    {
        return [
            'pending membership in active organization' => [OrganizationStatus::Active, MembershipStatus::Pending],
            'inactive membership in active organization' => [OrganizationStatus::Active, MembershipStatus::Inactive],
            'left membership in active organization' => [OrganizationStatus::Active, MembershipStatus::Left],
            'inactive membership in suspended organization' => [OrganizationStatus::Suspended, MembershipStatus::Inactive],
            'active membership in pending organization' => [OrganizationStatus::Pending, MembershipStatus::Active],
            'active membership in rejected organization' => [OrganizationStatus::Rejected, MembershipStatus::Active],
        ];
    }

    #[DataProvider('combinationsDeniedAsNotFound')]
    public function test_view_denies_as_not_found(OrganizationStatus $organizationStatus, MembershipStatus $membershipStatus): void
    {
        [$user, $organization] = $this->memberOf($organizationStatus, MembershipRole::Volunteer, $membershipStatus);

        $result = Gate::forUser($user)->inspect('view', $organization);

        $this->assertTrue($result->denied());
        $this->assertSame(404, $result->status());
    }

    public function test_view_denies_a_non_member_as_not_found(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create();

        $result = Gate::forUser($user)->inspect('view', $organization);

        $this->assertSame(404, $result->status());
    }

    public function test_manage_members_allows_only_an_active_administrator(): void
    {
        [$administrator, $organization] = $this->memberOf(OrganizationStatus::Active, MembershipRole::Administrator, MembershipStatus::Active);
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $inactiveAdministrator = User::factory()->create();
        Membership::factory()->for($organization)->for($inactiveAdministrator)->administrator()->inactive()->create();
        $outsider = User::factory()->create();

        $this->assertTrue(Gate::forUser($administrator)->allows('manageMembers', $organization));
        $this->assertFalse(Gate::forUser($volunteer)->allows('manageMembers', $organization));
        $this->assertFalse(Gate::forUser($inactiveAdministrator)->allows('manageMembers', $organization));
        $this->assertFalse(Gate::forUser($outsider)->allows('manageMembers', $organization));
    }

    /**
     * @return array{User, Organization}
     */
    private function memberOf(OrganizationStatus $organizationStatus, MembershipRole $role, MembershipStatus $membershipStatus): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['status' => $organizationStatus]);
        Membership::factory()->for($organization)->for($user)->create(['role' => $role, 'status' => $membershipStatus]);

        return [$user, $organization];
    }
}
