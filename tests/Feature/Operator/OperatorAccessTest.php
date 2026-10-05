<?php

namespace Tests\Feature\Operator;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperatorAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_who_is_not_an_operator_gets_403_without_learning_the_organization_name(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        $otherOrganization = Organization::factory()->active()->create(['name' => 'River Cleanup']);

        $response = $this->actingAs($administrator)->get(route('operator.organizations.show', $otherOrganization));

        $response->assertForbidden();
        $response->assertDontSeeText('River Cleanup');
    }

    public function test_list_defaults_to_pending_requests_from_verified_requesters(): void
    {
        $operator = User::factory()->operator()->create();
        Organization::factory()->pending()->create(['name' => 'Tool Library']);
        Organization::factory()->pending()->for(User::factory()->unverified(), 'requester')->create(['name' => 'Unverified Kitchen']);
        Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Organization::factory()->rejected()->create(['name' => 'Rejected Club']);

        $response = $this->actingAs($operator)->get(route('operator.organizations.index'));

        $response->assertSeeText('Tool Library');
        $response->assertDontSeeText('Unverified Kitchen');
        $response->assertDontSeeText('Food Bank North');
        $response->assertDontSeeText('Rejected Club');
    }

    public function test_list_shows_organizations_of_the_chosen_status_with_their_details(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $operator = User::factory()->operator()->create();
        $requester = User::factory()->create(['name' => 'Fiona Founder', 'email' => 'fiona@example.test']);
        $organization = Organization::factory()->active()->for($requester, 'requester')->create([
            'name' => 'Food Bank North',
            'contact_email' => 'hello@foodbank.test',
            'created_at' => '2026-09-14 10:00:00',
        ]);
        Membership::factory()->count(3)->for($organization)->volunteer()->active()->create();
        Membership::factory()->count(2)->for($organization)->volunteer()->inactive()->create();
        Organization::factory()->pending()->create(['name' => 'Tool Library']);

        $response = $this->actingAs($operator)->get(route('operator.organizations.index', ['status' => 'active']));

        $response->assertSeeTextInOrder(['Food Bank North', 'Active', 'hello@foodbank.test', 'Fiona Founder', 'fiona@example.test', '14 Sep 2026', '3']);
        $response->assertDontSeeText('Tool Library');
    }

    public function test_organization_page_shows_no_roster_or_member_details(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $member = User::factory()->create(['name' => 'Victor Volunteer', 'email' => 'victor@example.test']);
        Membership::factory()->for($organization)->for($member)->volunteer()->active()->create();

        $response = $this->actingAs($operator)->get(route('operator.organizations.show', $organization));

        $response->assertSeeText('Food Bank North');
        $response->assertDontSeeText('Victor Volunteer');
        $response->assertDontSeeText('victor@example.test');
        $response->assertDontSee(route('orgs.members.index', $organization));
    }

    public function test_operator_cannot_open_an_organizations_roster_without_a_membership(): void
    {
        $operator = User::factory()->operator()->create();
        $organization = Organization::factory()->active()->create();

        $response = $this->actingAs($operator)->get(route('orgs.members.index', $organization));

        $response->assertNotFound();
    }
}
