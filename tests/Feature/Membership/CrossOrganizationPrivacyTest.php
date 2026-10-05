<?php

namespace Tests\Feature\Membership;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrossOrganizationPrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_page_does_not_mention_the_persons_other_organization(): void
    {
        [$foodBank, $administrator, $membership] = $this->personInBothOrganizations();

        $response = $this->actingAs($administrator)->get(route('orgs.members.show', [$foodBank, $membership]));

        $response->assertSeeText('Member Of Both');
        $response->assertDontSeeText('River Cleanup');
    }

    public function test_roster_does_not_mention_the_persons_other_organization(): void
    {
        [$foodBank, $administrator] = $this->personInBothOrganizations();

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $foodBank));

        $response->assertSeeText('Member Of Both');
        $response->assertDontSeeText('River Cleanup');
    }

    public function test_deactivated_member_keeps_access_to_their_other_organization(): void
    {
        $member = User::factory()->create();
        $foodBank = Organization::factory()->active()->create();
        $riverCleanup = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        Membership::factory()->for($foodBank)->for($member)->volunteer()->inactive()->create();
        Membership::factory()->for($riverCleanup)->for($member)->administrator()->active()->create();

        $response = $this->actingAs($member)->get(route('orgs.show', $riverCleanup));

        $response->assertSeeText('Your role: Administrator');
    }

    public function test_dashboard_lists_only_the_persons_own_open_invitations(): void
    {
        $user = User::factory()->create(['email' => 'grace@example.test']);
        Invitation::factory()->for(Organization::factory()->active()->create(['name' => 'Food Bank North']))->create(['email' => 'grace@example.test']);
        Invitation::factory()->for(Organization::factory()->active()->create(['name' => 'River Cleanup']))->create(['email' => 'someone@example.test']);
        Invitation::factory()->for(Organization::factory()->active()->create(['name' => 'Tool Library']))->expired()->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Pending invitations', 'Food Bank North']);
        $response->assertDontSeeText('River Cleanup');
        $response->assertDontSeeText('Tool Library');
    }

    /**
     * Make "Member Of Both" a volunteer of Food Bank North and an administrator of River Cleanup.
     *
     * @return array{Organization, User, Membership}
     */
    private function personInBothOrganizations(): array
    {
        $foodBank = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $riverCleanup = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        $administrator = User::factory()->create();
        Membership::factory()->for($foodBank)->for($administrator)->administrator()->active()->create();
        $member = User::factory()->create(['name' => 'Member Of Both']);
        $membership = Membership::factory()->for($foodBank)->for($member)->volunteer()->active()->create();
        Membership::factory()->for($riverCleanup)->for($member)->administrator()->active()->create();

        return [$foodBank, $administrator, $membership];
    }
}
