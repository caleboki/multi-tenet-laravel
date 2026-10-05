<?php

namespace Tests\Feature\Membership;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaveOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_leaves_and_is_sent_to_the_dashboard(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $volunteer = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->delete(route('orgs.membership.destroy', $organization));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', "You've left Food Bank North.");
        $this->assertSame(MembershipStatus::Left, $membership->fresh()->status);
    }

    public function test_last_active_administrator_cannot_leave(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->from(route('orgs.show', $organization))->delete(route('orgs.membership.destroy', $organization));

        $response->assertRedirect(route('orgs.show', $organization));
        $response->assertSessionHasErrors(['membership' => 'The organization must keep at least one active administrator.']);
        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
    }

    public function test_administrator_can_leave_when_another_administrator_remains(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        Membership::factory()->for($organization)->administrator()->active()->create();

        $this->actingAs($administrator)->delete(route('orgs.membership.destroy', $organization));

        $membership->refresh();
        $this->assertSame(MembershipStatus::Left, $membership->status);
        $this->assertSame(MembershipRole::Administrator, $membership->role);
    }

    public function test_person_who_left_can_ask_to_join_again_through_the_sign_up_link(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $this->actingAs($volunteer)->delete(route('orgs.membership.destroy', $organization));

        $response = $this->actingAs($volunteer)->post(route('join.store', $organization->signup_token));

        $response->assertSessionHasNoErrors();
        $this->assertSame(MembershipStatus::Pending, $membership->fresh()->status);
    }

    public function test_organization_home_offers_a_leave_button(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->get(route('orgs.show', $organization));

        $response->assertSee('action="'.route('orgs.membership.destroy', $organization).'"', false);
        $response->assertSeeText('Leave organization');
    }
}
