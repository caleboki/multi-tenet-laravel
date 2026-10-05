<?php

namespace Tests\Feature\Invitations;

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

class ManageInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resend_replaces_the_link_and_expiry_and_emails_the_new_link(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $invitation = Invitation::factory()->for($organization)->expired()->withToken('old-token')->create([
            'email' => 'grace@example.test',
        ]);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.invitations.resend', [$organization, $invitation]));

        $response->assertRedirect(route('orgs.members.index', $organization));
        $response->assertSessionHas('status', 'Invitation resent to grace@example.test.');

        $invitation->refresh();
        $this->assertNotSame(hash('sha256', 'old-token'), $invitation->token_hash);
        $this->assertSame('2026-10-12 12:00:00', $invitation->expires_at->toDateTimeString());
        $this->assertSame($administrator->id, $invitation->invited_by_id);

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            fn (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'grace@example.test'
                && hash('sha256', $notification->token) === $invitation->token_hash,
        );
    }

    public function test_old_link_stops_working_after_a_resend(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $invitation = Invitation::factory()->for($organization)->withToken('old-token')->create();
        Notification::fake();
        $this->actingAs($administrator)->post(route('orgs.invitations.resend', [$organization, $invitation]));

        $response = $this->get(route('invitations.show', 'old-token'));

        $response->assertSeeText('This invitation link has expired or has already been used.');
        $response->assertDontSee('name="password"', false);
    }

    public function test_cancel_deletes_the_invitation(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $invitation = Invitation::factory()->for($organization)->create(['email' => 'grace@example.test']);

        $response = $this->actingAs($administrator)->delete(route('orgs.invitations.destroy', [$organization, $invitation]));

        $response->assertRedirect(route('orgs.members.index', $organization));
        $response->assertSessionHas('status', 'Invitation to grace@example.test cancelled.');
        $this->assertModelMissing($invitation);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invitationActions(): array
    {
        return [
            'resend' => ['post', 'orgs.invitations.resend'],
            'cancel' => ['delete', 'orgs.invitations.destroy'],
        ];
    }

    #[DataProvider('invitationActions')]
    public function test_invitation_of_another_organization_gets_404(string $method, string $routeName): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $otherInvitation = Invitation::factory()->withToken('other-token')->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->{$method}(route($routeName, [$organization, $otherInvitation]));

        $response->assertNotFound();
        $this->assertSame(hash('sha256', 'other-token'), $otherInvitation->fresh()->token_hash);
        Notification::assertNothingSent();
    }

    #[DataProvider('invitationActions')]
    public function test_volunteer_gets_403(string $method, string $routeName): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $invitation = Invitation::factory()->for($organization)->withToken('original-token')->create();
        Notification::fake();

        $response = $this->actingAs($volunteer)->{$method}(route($routeName, [$organization, $invitation]));

        $response->assertForbidden();
        $this->assertSame(hash('sha256', 'original-token'), $invitation->fresh()->token_hash);
        Notification::assertNothingSent();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
