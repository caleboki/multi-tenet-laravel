<?php

namespace Tests\Feature\Membership;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnsureActiveMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $organization = Organization::factory()->active()->create();

        $response = $this->get(route('orgs.show', $organization));

        $response->assertRedirect(route('login'));
    }

    public function test_unverified_member_is_redirected_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();
        $organization = Organization::factory()->active()->create();
        Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_non_member_gets_404(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{MembershipStatus}>
     */
    public static function membershipStatusesWithoutAccess(): array
    {
        return [
            'pending approval' => [MembershipStatus::Pending],
            'inactive' => [MembershipStatus::Inactive],
            'left' => [MembershipStatus::Left],
        ];
    }

    #[DataProvider('membershipStatusesWithoutAccess')]
    public function test_member_without_an_active_membership_gets_404(MembershipStatus $status): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create();
        Membership::factory()->for($organization)->for($user)->volunteer()->create(['status' => $status]);

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{OrganizationStatus}>
     */
    public static function organizationStatusesWithoutAccess(): array
    {
        return [
            'pending' => [OrganizationStatus::Pending],
            'rejected' => [OrganizationStatus::Rejected],
        ];
    }

    #[DataProvider('organizationStatusesWithoutAccess')]
    public function test_active_member_of_an_organization_that_is_not_open_gets_404(OrganizationStatus $status): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['status' => $status]);
        Membership::factory()->for($organization)->for($user)->administrator()->active()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertNotFound();
    }

    public function test_active_member_of_a_suspended_organization_gets_403_with_the_suspended_page(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->suspended()->create(['name' => 'River Cleanup']);
        Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertForbidden();
        $response->assertSee('River Cleanup is suspended');
    }

    public function test_active_member_of_an_active_organization_sees_its_name_and_their_role(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertOk();
        $response->assertSee('Food Bank North');
        $response->assertSee('Volunteer');
    }
}
