<?php

namespace Tests\Feature\Invitations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AcceptInvitationExistingAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signed_out_invitee_with_an_account_signs_in_and_returns_to_the_invitation(): void
    {
        User::factory()->create(['email' => 'grace@example.test']);
        Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);
        $this->get(route('invitations.show', 'plain-token'))
            ->assertSeeText('You already have an account with grace@example.test. Sign in to accept this invitation.');

        $response = $this->post(route('login.store'), [
            'email' => 'grace@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('invitations.show', 'plain-token'));
    }

    public function test_signed_in_invitee_sees_an_accept_button_without_account_fields(): void
    {
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        Invitation::factory()->for($organization)->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($invitee)->get(route('invitations.show', 'plain-token'));

        $response->assertSeeText('Join River Cleanup');
        $response->assertSeeText('Accept invitation');
        $response->assertDontSee('name="password"', false);
    }

    public function test_accepting_while_signed_in_adds_an_active_membership_without_changing_the_password(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->administrator()->withToken('plain-token')->create([
            'email' => 'grace@example.test',
        ]);

        $response = $this->actingAs($invitee)->post(route('invitations.accept', 'plain-token'));

        $response->assertRedirect(route('orgs.show', $organization));

        $membership = $invitee->membershipIn($organization);
        $this->assertSame(MembershipRole::Administrator, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame('2026-10-05 12:00:00', $membership->joined_at->toDateTimeString());
        $this->assertModelMissing($invitation);
        $this->assertTrue(Hash::check('password', $invitee->fresh()->password));
        $this->assertSame(1, User::where('email', 'grace@example.test')->count());
    }

    public function test_accepting_reuses_the_membership_of_a_person_who_left(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $invitee = User::factory()->create(['email' => 'grace@example.test']);
        $organization = Organization::factory()->active()->create();
        $formerMembership = Membership::factory()->for($organization)->for($invitee)->volunteer()->left()->create([
            'joined_at' => '2025-01-15 09:00:00',
        ]);
        Invitation::factory()->for($organization)->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $this->actingAs($invitee)->post(route('invitations.accept', 'plain-token'));

        $formerMembership->refresh();
        $this->assertSame(MembershipStatus::Active, $formerMembership->status);
        $this->assertSame('2026-10-05 12:00:00', $formerMembership->joined_at->toDateTimeString());
        $this->assertSame(1, $invitee->memberships()->count());
    }

    public function test_person_signed_in_with_another_email_is_told_the_invitation_is_not_theirs(): void
    {
        $someoneElse = User::factory()->create(['email' => 'other@example.test']);
        Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($someoneElse)->get(route('invitations.show', 'plain-token'));

        $response->assertSeeText('This invitation is for another email address.');
        $response->assertDontSeeText('Accept invitation');
        $response->assertDontSee('name="password"', false);
    }

    public function test_accepting_while_signed_in_with_another_email_changes_nothing(): void
    {
        $someoneElse = User::factory()->create(['email' => 'other@example.test']);
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($someoneElse)->post(route('invitations.accept', 'plain-token'));

        $response->assertRedirect(route('invitations.show', 'plain-token'));
        $this->assertNull($someoneElse->membershipIn($organization));
        $this->assertModelExists($invitation);
    }

    public function test_invitation_email_to_an_existing_account_asks_the_person_to_sign_in(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();
        User::factory()->create(['email' => 'grace@example.test']);
        Notification::fake();

        $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            fn (InvitationNotification $notification): bool => $notification->existingAccount,
        );
    }
}
