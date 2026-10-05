<?php

namespace Tests\Feature\Roster;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateMemberTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deactivating_keeps_the_member_in_the_roster_and_ends_their_access(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $volunteer = User::factory()->create(['name' => 'Victor Volunteer']);
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('orgs.members.show', [$organization, $membership]));
        $response->assertSessionHas('status', 'Saved the changes to Victor Volunteer.');
        $this->assertSame(MembershipStatus::Inactive, $membership->fresh()->status);

        $this->actingAs($volunteer)->get(route('orgs.show', $organization))->assertNotFound();
    }

    public function test_reactivating_restores_access_and_keeps_the_date_joined(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $volunteer = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->inactive()->create([
            'joined_at' => '2026-03-01 09:00:00',
        ]);

        $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'status' => 'active',
        ]);

        $membership->refresh();
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame('2026-03-01 09:00:00', $membership->joined_at->toDateTimeString());

        $this->actingAs($volunteer)->get(route('orgs.show', $organization))->assertOk();
    }

    public function test_promoting_a_volunteer_gives_them_the_roster(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $volunteer = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'role' => 'administrator',
        ]);

        $this->assertSame(MembershipRole::Administrator, $membership->fresh()->role);
        $this->actingAs($volunteer)->get(route('orgs.members.index', $organization))->assertOk();
    }

    public function test_demoted_administrator_is_refused_the_roster_on_their_next_request(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $administrator = $this->administratorOf($organization);
        $otherAdministrator = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($otherAdministrator)->administrator()->active()->create();

        $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'role' => 'volunteer',
        ]);

        $this->assertSame(MembershipRole::Volunteer, $membership->fresh()->role);
        $this->actingAs($otherAdministrator)->get(route('orgs.members.index', $organization))
            ->assertForbidden()
            ->assertSeeText("You don't have access to this page")
            ->assertSee(route('orgs.show', $organization));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function changesThatRemoveAnAdministrator(): array
    {
        return [
            'demote' => [['role' => 'volunteer']],
            'deactivate' => [['status' => 'inactive']],
        ];
    }

    /**
     * @param  array<string, string>  $change
     */
    #[DataProvider('changesThatRemoveAnAdministrator')]
    public function test_refuses_to_remove_the_last_active_administrator(array $change): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        Membership::factory()->for($organization)->administrator()->inactive()->create();

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), $change);

        $response->assertSessionHasErrors(['membership' => 'The organization must keep at least one active administrator.']);
        $membership->refresh();
        $this->assertSame(MembershipRole::Administrator, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
    }

    public function test_administrator_can_step_down_when_another_administrator_remains(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'role' => 'volunteer',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(MembershipRole::Volunteer, $membership->fresh()->role);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusesAnAdministratorCannotSet(): array
    {
        return [
            'pending' => ['pending'],
            'left' => ['left'],
        ];
    }

    #[DataProvider('statusesAnAdministratorCannotSet')]
    public function test_rejects_a_status_other_than_active_or_inactive(string $status): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $membership = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'status' => $status,
        ]);

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
    }

    public function test_requires_a_role_or_status(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $membership = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), []);

        $response->assertSessionHasErrors(['role' => 'The role field is required when status is not present.']);
    }

    /**
     * @return array<string, array{MembershipStatus}>
     */
    public static function membershipsThatAreNotCurrentMembers(): array
    {
        return [
            'join request' => [MembershipStatus::Pending],
            'left' => [MembershipStatus::Left],
        ];
    }

    #[DataProvider('membershipsThatAreNotCurrentMembers')]
    public function test_refuses_to_change_a_membership_that_is_not_a_current_member(MembershipStatus $status): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $membership = Membership::factory()->for($organization)->volunteer()->create(['status' => $status]);

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $membership]), [
            'status' => 'active',
        ]);

        $response->assertConflict();
        $this->assertSame($status, $membership->fresh()->status);
    }

    public function test_volunteer_gets_403(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $otherMember = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->patch(route('orgs.members.update', [$organization, $otherMember]), [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
        $this->assertSame(MembershipStatus::Active, $otherMember->fresh()->status);
    }

    public function test_membership_of_another_organization_gets_404(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $otherMembership = Membership::factory()->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->patch(route('orgs.members.update', [$organization, $otherMembership]), [
            'status' => 'inactive',
        ]);

        $response->assertNotFound();
        $this->assertSame(MembershipStatus::Active, $otherMembership->fresh()->status);
    }

    public function test_member_page_offers_role_and_status_controls(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $membership = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.show', [$organization, $membership]));

        $response->assertSee('name="role"', false);
        $response->assertSeeText('Deactivate');
        $response->assertSee(route('orgs.members.update', [$organization, $membership]));
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
