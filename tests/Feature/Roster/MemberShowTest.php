<?php

namespace Tests\Feature\Roster;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MemberShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_sees_the_members_details_read_only(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $volunteer = User::factory()->create([
            'name' => 'Victor Volunteer',
            'email' => 'victor@example.test',
            'phone' => '+44 20 7946 0000',
        ]);
        $membership = Membership::factory()->for($organization)->for($volunteer)->volunteer()->inactive()->create([
            'joined_at' => '2026-09-14 10:00:00',
        ]);

        $response = $this->actingAs($administrator)->get(route('orgs.members.show', [$organization, $membership]));

        $response->assertSeeTextInOrder([
            'Victor Volunteer',
            'victor@example.test',
            '+44 20 7946 0000',
            'Volunteer',
            'Inactive',
            '14 Sep 2026',
        ]);
        $response->assertDontSee('name="name"', false);
        $response->assertDontSee('name="email"', false);
        $response->assertDontSee('name="phone"', false);
    }

    public function test_volunteer_gets_403(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $otherMember = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->get(route('orgs.members.show', [$organization, $otherMember]));

        $response->assertForbidden();
    }

    public function test_member_of_another_organization_gets_404(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $otherMembership = Membership::factory()->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.show', [$organization, $otherMembership]));

        $response->assertNotFound();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
