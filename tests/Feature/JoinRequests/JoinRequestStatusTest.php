<?php

namespace Tests\Feature\JoinRequests;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JoinRequestStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_shows_a_verified_request_as_awaiting_approval(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Membership::factory()->for($organization)->for($user)->volunteer()->pending()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Your join requests', 'Food Bank North', 'Awaiting approval']);
    }

    public function test_dashboard_asks_an_unverified_requester_to_verify_their_email_first(): void
    {
        $user = User::factory()->unverified()->create();
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Membership::factory()->for($organization)->for($user)->volunteer()->pending()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Your join requests', 'Food Bank North', 'Verify your email first']);
    }

    public function test_person_with_a_pending_request_cannot_open_the_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->active()->create();
        Membership::factory()->for($organization)->for($user)->volunteer()->pending()->create();

        $response = $this->actingAs($user)->get(route('orgs.show', $organization));

        $response->assertNotFound();
    }
}
