<?php

namespace Tests\Feature\Invitations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcceptInvitationNewAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_setup_form_fixes_the_email_and_prefills_the_name(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Invitation::factory()->for($organization)->withToken('plain-token')->create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
        ]);

        $response = $this->get(route('invitations.show', 'plain-token'));

        $response->assertSeeText('Food Bank North');
        $response->assertSeeText('grace@example.test');
        $response->assertDontSee('name="email"', false);
        $response->assertSee('value="Grace Hopper"', false);
    }

    public function test_accepting_creates_a_verified_account_and_active_membership_and_signs_in(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $invitation = Invitation::factory()->for($organization)->administrator()->withToken('plain-token')->create([
            'email' => 'grace@example.test',
        ]);

        $response = $this->post(route('invitations.accept', 'plain-token'), [
            'name' => 'Grace B. Hopper',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '+1 555 0100',
            'adult_confirmation' => '1',
        ]);

        $response->assertRedirect(route('orgs.show', $organization));

        $user = User::where('email', 'grace@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Grace B. Hopper', $user->name);
        $this->assertSame('+1 555 0100', $user->phone);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('2026-10-05 12:00:00', $user->email_verified_at->toDateTimeString());
        $this->assertSame('2026-10-05 12:00:00', $user->adult_confirmed_at->toDateTimeString());

        $membership = $user->membershipIn($organization);
        $this->assertSame(MembershipRole::Administrator, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame('2026-10-05 12:00:00', $membership->joined_at->toDateTimeString());

        $this->assertModelMissing($invitation);
    }

    public function test_refuses_to_create_an_account_without_adult_confirmation(): void
    {
        $invitation = Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->post(route('invitations.accept', 'plain-token'), [
            'name' => 'Grace Hopper',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHasErrors([
            'adult_confirmation' => 'You must confirm you are 18 or older. This platform is for adults only.',
        ]);
        $this->assertDatabaseMissing(User::class, ['email' => 'grace@example.test']);
        $this->assertModelExists($invitation);
        $this->assertGuest();
    }

    public function test_requires_a_name_and_password(): void
    {
        Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->post(route('invitations.accept', 'plain-token'), ['adult_confirmation' => '1']);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'password' => 'The password field is required.',
        ]);
        $this->assertDatabaseMissing(User::class, ['email' => 'grace@example.test']);
    }

    public function test_expired_link_tells_the_person_to_ask_for_a_new_invitation(): void
    {
        Invitation::factory()->expired()->withToken('plain-token')->create();

        $response = $this->get(route('invitations.show', 'plain-token'));

        $response->assertSeeText('This invitation link has expired or has already been used.');
        $response->assertSeeText("Ask your organization's administrator to send you a new invitation.");
        $response->assertDontSee('name="password"', false);
    }

    public function test_unknown_link_tells_the_person_to_ask_for_a_new_invitation(): void
    {
        $response = $this->get(route('invitations.show', 'unknown-token'));

        $response->assertSeeText('This invitation link has expired or has already been used.');
        $response->assertDontSee('name="password"', false);
    }

    public function test_accepting_an_expired_invitation_changes_nothing(): void
    {
        $invitation = Invitation::factory()->expired()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->post(route('invitations.accept', 'plain-token'), [
            'name' => 'Grace Hopper',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'adult_confirmation' => '1',
        ]);

        $response->assertRedirect(route('invitations.show', 'plain-token'));
        $this->assertDatabaseMissing(User::class, ['email' => 'grace@example.test']);
        $this->assertModelExists($invitation);
        $this->assertGuest();
    }

    public function test_invitation_for_an_existing_account_does_not_offer_account_setup(): void
    {
        User::factory()->create(['email' => 'grace@example.test']);
        Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->get(route('invitations.show', 'plain-token'));

        $response->assertSeeText('You already have an account with grace@example.test. Sign in to accept this invitation.');
        $response->assertDontSee('name="password"', false);
    }

    public function test_refuses_to_create_a_second_account_for_an_existing_email(): void
    {
        $existingUser = User::factory()->create(['email' => 'grace@example.test']);
        $invitation = Invitation::factory()->withToken('plain-token')->create(['email' => 'grace@example.test']);

        $response = $this->post(route('invitations.accept', 'plain-token'), [
            'name' => 'Grace Hopper',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'adult_confirmation' => '1',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'You already have an account with grace@example.test. Sign in to accept this invitation.',
        ]);
        $this->assertSame([$existingUser->id], User::where('email', 'grace@example.test')->pluck('id')->all());
        $this->assertDatabaseCount(Membership::class, 0);
        $this->assertModelExists($invitation);
        $this->assertGuest();
    }

    public function test_invitation_links_are_throttled(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->get(route('invitations.show', 'unknown-token'))->assertOk();
        }

        $response = $this->get(route('invitations.show', 'unknown-token'));

        $response->assertTooManyRequests();
    }
}
