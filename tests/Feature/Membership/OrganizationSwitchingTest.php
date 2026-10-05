<?php

namespace Tests\Feature\Membership;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationSwitchingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opening_an_organization_page_remembers_it_as_the_last_organization(): void
    {
        $user = User::factory()->create();
        $first = $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $second = $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $user->forceFill(['last_organization_id' => $first->id])->save();

        $this->actingAs($user)->get(route('orgs.show', $second));

        $this->assertSame($second->id, $user->fresh()->last_organization_id);
    }

    public function test_refused_organization_page_does_not_change_the_last_organization(): void
    {
        $user = User::factory()->create();
        $organization = $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $user->forceFill(['last_organization_id' => $organization->id])->save();
        $otherOrganization = Organization::factory()->active()->create();

        $this->actingAs($user)->get(route('orgs.show', $otherOrganization))->assertNotFound();

        $this->assertSame($organization->id, $user->fresh()->last_organization_id);
    }

    public function test_dashboard_opens_the_last_organization_when_it_is_still_open(): void
    {
        $user = User::factory()->create();
        $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $lastOrganization = $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $user->forceFill(['last_organization_id' => $lastOrganization->id])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('orgs.show', $lastOrganization));
    }

    /**
     * @return array<string, array{Closure(User): Organization}>
     */
    public static function lastOrganizationsThatAreNoLongerOpen(): array
    {
        return [
            'organization suspended' => [function (User $user): Organization {
                $organization = Organization::factory()->suspended()->create();
                Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();

                return $organization;
            }],
            'membership deactivated' => [function (User $user): Organization {
                $organization = Organization::factory()->active()->create();
                Membership::factory()->for($organization)->for($user)->volunteer()->inactive()->create();

                return $organization;
            }],
        ];
    }

    /**
     * @param  Closure(User): Organization  $createLastOrganization
     */
    #[DataProvider('lastOrganizationsThatAreNoLongerOpen')]
    public function test_dashboard_falls_back_to_the_only_open_organization(Closure $createLastOrganization): void
    {
        $user = User::factory()->create();
        $onlyOpenOrganization = $this->activeMembershipIn(Organization::factory()->active()->create(), $user);
        $user->forceFill(['last_organization_id' => $createLastOrganization($user)->id])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('orgs.show', $onlyOpenOrganization));
    }

    public function test_dashboard_asks_to_choose_when_there_is_no_last_organization(): void
    {
        $user = User::factory()->create();
        $foodBank = $this->activeMembershipIn(Organization::factory()->active()->create(['name' => 'Food Bank North']), $user);
        $toolLibrary = $this->activeMembershipIn(Organization::factory()->active()->create(['name' => 'Tool Library']), $user);
        $riverCleanup = $this->activeMembershipIn(Organization::factory()->suspended()->create(['name' => 'River Cleanup']), $user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeInOrder(['Your organizations', route('orgs.show', $foodBank), route('orgs.show', $toolLibrary)]);
        $response->assertSeeTextInOrder(['River Cleanup', 'This organization is suspended']);
        $response->assertDontSee(route('orgs.show', $riverCleanup));
    }

    public function test_person_whose_only_organization_is_suspended_is_told_so_without_a_link(): void
    {
        $user = User::factory()->create();
        $organization = $this->activeMembershipIn(Organization::factory()->suspended()->create(['name' => 'River Cleanup']), $user);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['River Cleanup', 'This organization is suspended']);
        $response->assertDontSee(route('orgs.show', $organization));
    }

    public function test_header_switcher_lists_only_organizations_the_person_can_open(): void
    {
        $user = User::factory()->create();
        $foodBank = $this->activeMembershipIn(Organization::factory()->active()->create(['name' => 'Food Bank North']), $user);
        $toolLibrary = $this->activeMembershipIn(Organization::factory()->active()->create(['name' => 'Tool Library']), $user);
        $pending = Organization::factory()->active()->create(['name' => 'Pending Place']);
        Membership::factory()->for($pending)->for($user)->volunteer()->pending()->create();
        $inactive = Organization::factory()->active()->create(['name' => 'Inactive Club']);
        Membership::factory()->for($inactive)->for($user)->volunteer()->inactive()->create();
        $suspended = $this->activeMembershipIn(Organization::factory()->suspended()->create(['name' => 'River Cleanup']), $user);

        $response = $this->actingAs($user)->get(route('orgs.show', $foodBank));

        $response->assertSeeInOrder(['Switch organization', route('orgs.show', $foodBank), route('orgs.show', $toolLibrary)]);
        $response->assertDontSeeText('Pending Place');
        $response->assertDontSeeText('Inactive Club');
        $response->assertDontSeeText('River Cleanup');
        $response->assertDontSee(route('orgs.show', $suspended));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function rolesByOrganization(): array
    {
        return [
            'organization where the person volunteers' => ['Food Bank North', 'Volunteer', false],
            'organization where the person administers' => ['River Cleanup', 'Administrator', true],
        ];
    }

    #[DataProvider('rolesByOrganization')]
    public function test_role_shown_follows_the_organization_being_viewed(string $organizationName, string $role, bool $seesRoster): void
    {
        $user = User::factory()->create();
        $foodBank = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $riverCleanup = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        Membership::factory()->for($foodBank)->for($user)->volunteer()->active()->create();
        Membership::factory()->for($riverCleanup)->for($user)->administrator()->active()->create();
        $organization = Organization::where('name', $organizationName)->sole();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertSeeText("Your role: {$role}");

        if ($seesRoster) {
            $response->assertSee(route('orgs.members.index', $organization));
        } else {
            $response->assertDontSee(route('orgs.members.index', $organization));
        }
    }

    public function test_header_names_the_last_organization_outside_organization_pages(): void
    {
        $user = User::factory()->create();
        $organization = $this->activeMembershipIn(Organization::factory()->active()->create(['name' => 'Food Bank North']), $user);
        $user->forceFill(['last_organization_id' => $organization->id])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertSeeText('Current organization: Food Bank North');
    }

    public function test_header_says_no_organization_is_selected_without_a_last_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeText('Current organization: No organization selected');
    }

    private function activeMembershipIn(Organization $organization, User $user): Organization
    {
        Membership::factory()->for($organization)->for($user)->volunteer()->active()->create();

        return $organization;
    }
}
