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
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IssueInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_invitation_and_emails_a_one_time_link(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'Grace@Example.TEST',
            'role' => 'administrator',
        ]);

        $response->assertRedirect(route('orgs.members.index', $organization));
        $response->assertSessionHas('status', 'Invitation sent to grace@example.test.');

        $invitation = $organization->invitations()->sole();
        $this->assertSame('Grace Hopper', $invitation->name);
        $this->assertSame('grace@example.test', $invitation->email);
        $this->assertSame(MembershipRole::Administrator, $invitation->role);
        $this->assertSame($administrator->id, $invitation->invited_by_id);
        $this->assertSame('2026-10-12 12:00:00', $invitation->expires_at->toDateTimeString());

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            fn (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'grace@example.test'
                && $notification->token !== $invitation->token_hash
                && hash('sha256', $notification->token) === $invitation->token_hash,
        );
    }

    /**
     * @return array<string, array{MembershipStatus}>
     */
    public static function membershipStatusesInTheRoster(): array
    {
        return [
            'pending approval' => [MembershipStatus::Pending],
            'active' => [MembershipStatus::Active],
            'inactive' => [MembershipStatus::Inactive],
        ];
    }

    #[DataProvider('membershipStatusesInTheRoster')]
    public function test_refuses_an_email_that_already_has_a_membership(MembershipStatus $status): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $member = User::factory()->create(['email' => 'grace@example.test']);
        Membership::factory()->for($organization)->for($member)->volunteer()->create(['status' => $status]);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'GRACE@example.test',
            'role' => 'volunteer',
        ]);

        $response->assertSessionHasErrors(['email' => 'This email address is already in your roster.']);
        $this->assertDatabaseCount(Invitation::class, 0);
        Notification::assertNothingSent();
    }

    public function test_refuses_an_email_that_already_has_an_open_invitation(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $existingInvitation = Invitation::factory()->for($organization)->create(['email' => 'grace@example.test']);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        $response->assertSessionHasErrors(['email' => 'An invitation has already been sent to this email address. You can resend it from the roster.']);
        $this->assertSame([$existingInvitation->id], Invitation::pluck('id')->all());
        Notification::assertNothingSent();
    }

    public function test_invites_an_email_whose_member_left_the_organization(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $formerMember = User::factory()->create(['email' => 'grace@example.test']);
        Membership::factory()->for($organization)->for($formerMember)->volunteer()->left()->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('grace@example.test', $organization->invitations()->sole()->email);
        Notification::assertSentOnDemandTimes(InvitationNotification::class, 1);
    }

    public function test_invites_an_email_that_belongs_only_to_another_organization(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $otherMember = User::factory()->create(['email' => 'grace@example.test']);
        Membership::factory()->for($otherMember)->volunteer()->active()->create();
        Invitation::factory()->create(['email' => 'grace@example.test']);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('grace@example.test', $organization->invitations()->sole()->email);
        Notification::assertSentOnDemandTimes(InvitationNotification::class, 1);
    }

    public function test_requires_a_name_email_and_role(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
            'role' => 'The role field is required.',
        ]);
        $this->assertDatabaseCount(Invitation::class, 0);
    }

    public function test_rejects_an_invalid_email_and_role(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'not-an-email',
            'role' => 'owner',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'The email field must be a valid email address.',
            'role' => 'The selected role is invalid.',
        ]);
        $this->assertDatabaseCount(Invitation::class, 0);
    }

    public function test_volunteer_gets_403_and_no_invitation_is_created(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        Notification::fake();

        $response = $this->actingAs($volunteer)->post(route('orgs.invitations.store', $organization), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'role' => 'volunteer',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount(Invitation::class, 0);
        Notification::assertNothingSent();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
