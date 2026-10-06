<?php

namespace Tests\Feature\JoinRequests;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestReceived;
use Closure;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmitJoinRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_creates_an_unverified_account_and_a_pending_request(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Notification::fake();

        $response = $this->post(route('join.store', $organization->signup_token), [
            ...$this->accountFields(),
            'email' => 'Jo@Example.TEST',
            'phone' => '0123 456 789',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'jo@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('0123 456 789', $user->phone);

        $membership = $user->membershipIn($organization);
        $this->assertSame(MembershipStatus::Pending, $membership->status);
        $this->assertSame(MembershipRole::Volunteer, $membership->role);
        $this->assertSame('2026-10-05 12:00:00', $membership->requested_at->toDateTimeString());

        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNothingSentTo($administrator);
    }

    public function test_verifying_after_asking_to_join_leads_to_the_start_page_not_back_to_the_sign_up_page(): void
    {
        $organization = Organization::factory()->active()->create();
        Notification::fake();
        $this->get(route('join.show', $organization->signup_token));
        $this->post(route('join.store', $organization->signup_token), $this->accountFields());
        $user = User::where('email', 'jo@example.test')->sole();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('start').'?verified=1');
    }

    public function test_guest_must_confirm_they_are_an_adult(): void
    {
        $organization = Organization::factory()->active()->create();

        $response = $this->post(route('join.store', $organization->signup_token), [
            ...$this->accountFields(),
            'adult_confirmation' => null,
        ]);

        $response->assertSessionHasErrors([
            'adult_confirmation' => 'You must confirm you are 18 or older. This platform is for adults only.',
        ]);
        $this->assertDatabaseMissing(User::class, ['email' => 'jo@example.test']);
        $this->assertDatabaseCount(Membership::class, 0);
        $this->assertGuest();
    }

    public function test_guest_using_the_email_of_an_existing_account_is_asked_to_sign_in(): void
    {
        $organization = Organization::factory()->active()->create();
        User::factory()->create(['email' => 'jo@example.test']);

        $response = $this->post(route('join.store', $organization->signup_token), [
            ...$this->accountFields(),
            'email' => 'JO@example.test',
        ]);

        $response->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in to continue.']);
        $this->assertDatabaseCount(Membership::class, 0);
        $this->assertGuest();
    }

    public function test_signed_in_verified_person_requests_to_join_and_administrators_are_notified(): void
    {
        $organization = Organization::factory()->active()->create();
        $firstAdministrator = $this->administratorOf($organization);
        $secondAdministrator = $this->administratorOf($organization);
        Membership::factory()->for($organization)->volunteer()->active()->create();
        $user = User::factory()->create();
        Notification::fake();

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(MembershipStatus::Pending, $user->membershipIn($organization)->status);

        Notification::assertSentTimes(JoinRequestReceived::class, 2);
        Notification::assertSentTo(
            [$firstAdministrator, $secondAdministrator],
            JoinRequestReceived::class,
            fn (JoinRequestReceived $notification): bool => $notification->requester->is($user)
                && $notification->organization->is($organization),
        );
    }

    public function test_signing_in_from_the_join_page_returns_the_person_to_it(): void
    {
        $organization = Organization::factory()->active()->create();
        User::factory()->create(['email' => 'jo@example.test']);
        $this->get(route('join.show', $organization->signup_token));

        $response = $this->post(route('login.store'), [
            'email' => 'jo@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('join.show', $organization->signup_token));
    }

    /**
     * @return array<string, array{MembershipStatus, string}>
     */
    public static function membershipsThatBlockARequest(): array
    {
        return [
            'active' => [MembershipStatus::Active, "You're already a member of Food Bank North."],
            'pending approval' => [MembershipStatus::Pending, 'Your request to join Food Bank North is already waiting for approval.'],
            'inactive' => [MembershipStatus::Inactive, 'Your membership of Food Bank North is inactive. Ask its administrator to reactivate you.'],
        ];
    }

    #[DataProvider('membershipsThatBlockARequest')]
    public function test_refuses_a_person_who_already_has_a_membership(MembershipStatus $status, string $message): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $user = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($user)->volunteer()->create([
            'status' => $status,
            'requested_at' => '2026-09-01 09:00:00',
        ]);
        Notification::fake();

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertSessionHasErrors(['membership' => $message]);
        $membership->refresh();
        $this->assertSame($status, $membership->status);
        $this->assertSame('2026-09-01 09:00:00', $membership->requested_at->toDateTimeString());
        Notification::assertNothingSent();
    }

    public function test_refuses_a_person_who_already_has_an_invitation(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $user = User::factory()->create(['email' => 'jo@example.test']);
        Invitation::factory()->for($organization)->create(['email' => 'jo@example.test']);

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertSessionHasErrors([
            'membership' => "You've already been invited to join Food Bank North. Use the link in your invitation email.",
        ]);
        $this->assertNull($user->membershipIn($organization));
    }

    public function test_guest_whose_email_has_an_invitation_gets_no_account(): void
    {
        $organization = Organization::factory()->active()->create();
        Invitation::factory()->for($organization)->create(['email' => 'jo@example.test']);
        Notification::fake();

        $response = $this->post(route('join.store', $organization->signup_token), $this->accountFields());

        $response->assertSessionHasErrors('membership');
        $this->assertDatabaseMissing(User::class, ['email' => 'jo@example.test']);
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_person_who_left_requests_again_with_the_same_membership(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $user = User::factory()->create();
        $membership = Membership::factory()->for($organization)->for($user)->administrator()->left()->create();

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertSessionHasNoErrors();
        $membership->refresh();
        $this->assertSame(MembershipStatus::Pending, $membership->status);
        $this->assertSame(MembershipRole::Volunteer, $membership->role);
        $this->assertSame('2026-10-05 12:00:00', $membership->requested_at->toDateTimeString());
        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_person_whose_request_was_declined_can_request_again(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $user = User::factory()->create();
        $declinedRequest = Membership::factory()->for($organization)->for($user)->volunteer()->pending()->create();
        Notification::fake();
        $this->actingAs($administrator)->delete(route('orgs.join-requests.destroy', [$organization, $declinedRequest]));

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertSessionHasNoErrors();
        $this->assertSame(MembershipStatus::Pending, $user->membershipIn($organization)->status);
    }

    /**
     * @return array<string, array{Closure(): Organization}>
     */
    public static function organizationsNotAcceptingSignups(): array
    {
        return [
            'suspended' => [fn (): Organization => Organization::factory()->suspended()->create()],
            'self sign-up turned off' => [fn (): Organization => Organization::factory()->active()->create(['self_signup_enabled' => false])],
        ];
    }

    /**
     * @param  Closure(): Organization  $createOrganization
     */
    #[DataProvider('organizationsNotAcceptingSignups')]
    public function test_refuses_a_request_to_an_organization_that_is_not_accepting_sign_ups(Closure $createOrganization): void
    {
        $organization = $createOrganization();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('join.store', $organization->signup_token));

        $response->assertRedirect(route('join.show', $organization->signup_token));
        $this->assertNull($user->membershipIn($organization));
    }

    public function test_join_requests_are_throttled(): void
    {
        $organization = Organization::factory()->active()->create();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('join.store', $organization->signup_token))->assertRedirect();
        }

        $response = $this->post(route('join.store', $organization->signup_token));

        $response->assertTooManyRequests();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    /**
     * @return array{name: string, email: string, password: string, password_confirmation: string, adult_confirmation: string}
     */
    private function accountFields(): array
    {
        return [
            'name' => 'Jo Joiner',
            'email' => 'jo@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'adult_confirmation' => '1',
        ];
    }
}
