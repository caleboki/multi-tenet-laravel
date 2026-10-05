<?php

namespace Tests\Feature\Invitations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AcceptInvitationFromDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_offers_an_accept_button_to_a_verified_invitee(): void
    {
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $invitation = Invitation::factory()->for(Organization::factory()->active()->state(['name' => 'River Cleanup']))->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Pending invitations', 'River Cleanup', 'Accept']);
        $response->assertSee('action="'.route('dashboard.invitations.accept', $invitation).'"', false);
    }

    public function test_dashboard_asks_an_unverified_invitee_to_verify_first(): void
    {
        $invitee = User::factory()->unverified()->create(['email' => 'grace@example.test']);
        $invitation = Invitation::factory()->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->get(route('dashboard'));

        $response->assertSeeText('Verify your email address to accept here, or use the link in your invitation email.');
        $response->assertDontSee(route('dashboard.invitations.accept', $invitation));
    }

    public function test_accepting_adds_an_active_membership_and_opens_the_organization(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        $invitation = Invitation::factory()->for($organization)->administrator()->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->post(route('dashboard.invitations.accept', $invitation));

        $response->assertRedirect(route('orgs.show', $organization));
        $response->assertSessionHas('status', "You've joined River Cleanup.");

        $membership = $invitee->membershipIn($organization);
        $this->assertSame(MembershipRole::Administrator, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame('2026-10-05 12:00:00', $membership->joined_at->toDateTimeString());
        $this->assertModelMissing($invitation);
    }

    public function test_someone_elses_invitation_gets_404(): void
    {
        $someoneElse = User::factory()->create(['email' => 'other@example.test']);
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($someoneElse)->post(route('dashboard.invitations.accept', $invitation));

        $response->assertNotFound();
        $this->assertNull($someoneElse->membershipIn($organization));
        $this->assertModelExists($invitation);
    }

    public function test_expired_invitation_is_refused_with_an_explanation(): void
    {
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->expired()->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->post(route('dashboard.invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', "That invitation has expired. Ask the organization's administrator to send a new one.");
        $this->assertNull($invitee->membershipIn($organization));
        $this->assertModelExists($invitation);
    }

    public function test_invitation_to_an_organization_that_is_not_open_is_refused(): void
    {
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->suspended()->create();
        $invitation = Invitation::factory()->for($organization)->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->post(route('dashboard.invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', "That organization isn't open right now, so the invitation can't be accepted.");
        $this->assertNull($invitee->membershipIn($organization));
    }

    public function test_unverified_invitee_is_sent_to_verify_their_email(): void
    {
        $invitee = User::factory()->unverified()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->post(route('dashboard.invitations.accept', $invitation));

        $response->assertRedirect(route('verification.notice'));
        $this->assertNull($invitee->membershipIn($organization));
    }

    public function test_guest_is_sent_to_sign_in(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'grace@example.test']);

        $response = $this->post(route('dashboard.invitations.accept', $invitation));

        $response->assertRedirect(route('login'));
        $this->assertModelExists($invitation);
    }
}
