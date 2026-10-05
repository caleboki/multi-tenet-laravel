<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestOrganizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_creates_an_unverified_account_and_a_pending_organization(): void
    {
        Notification::fake();

        $response = $this->post(route('organization-requests.store'), [
            ...$this->accountFields(),
            'organization_name' => 'Tool Library',
            'contact_email' => 'Hello@ToolLibrary.TEST',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'sam@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());

        $organization = Organization::where('name', 'Tool Library')->sole();
        $this->assertSame(OrganizationStatus::Pending, $organization->status);
        $this->assertSame($user->id, $organization->requested_by_id);
        $this->assertSame('tool-library', $organization->slug);
        $this->assertSame('hello@toollibrary.test', $organization->contact_email);
        $this->assertNull($organization->signup_token);
        $this->assertSame(0, $organization->memberships()->count());

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_in_person_requests_an_organization_without_account_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => 'Tool Library',
            'contact_email' => 'hello@toollibrary.test',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($user->id, Organization::where('name', 'Tool Library')->sole()->requested_by_id);
    }

    public function test_signing_in_from_the_request_page_returns_the_person_to_it(): void
    {
        User::factory()->create(['email' => 'sam@example.test']);
        $this->get(route('organization-requests.create'));

        $response = $this->post(route('login.store'), [
            'email' => 'sam@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('organization-requests.create'));
    }

    public function test_request_page_shows_account_fields_and_a_sign_in_link_to_a_guest(): void
    {
        $response = $this->get(route('organization-requests.create'));

        $response->assertSee('name="organization_name"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('href="'.route('login').'"', false);
    }

    /**
     * @return array<string, array{OrganizationStatus}>
     */
    public static function statusesThatKeepTheName(): array
    {
        return [
            'pending' => [OrganizationStatus::Pending],
            'active' => [OrganizationStatus::Active],
            'suspended' => [OrganizationStatus::Suspended],
        ];
    }

    #[DataProvider('statusesThatKeepTheName')]
    public function test_refuses_a_name_already_taken_ignoring_case(OrganizationStatus $status): void
    {
        Organization::factory()->create(['name' => 'Food Bank North', 'status' => $status]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => 'food bank NORTH',
            'contact_email' => 'hello@example.test',
        ]);

        $response->assertSessionHasErrors(['organization_name' => 'That organization name is already taken.']);
        $this->assertDatabaseCount(Organization::class, 1);
    }

    public function test_name_check_treats_wildcard_characters_literally(): void
    {
        Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => 'Food_Bank North',
            'contact_email' => 'hello@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertModelExists(Organization::where('name', 'Food_Bank North')->sole());
    }

    public function test_accepts_the_name_of_a_rejected_organization(): void
    {
        Organization::factory()->rejected()->create(['name' => 'Community Kitchen', 'slug' => 'community-kitchen']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => 'Community Kitchen',
            'contact_email' => 'hello@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            'community-kitchen-2',
            Organization::where('status', OrganizationStatus::Pending)->where('name', 'Community Kitchen')->sole()->slug,
        );
    }

    public function test_gives_a_numbered_slug_when_another_name_makes_the_same_slug(): void
    {
        Organization::factory()->active()->create(['name' => 'Tool Library', 'slug' => 'tool-library']);
        Organization::factory()->active()->create(['name' => 'Tool Library!', 'slug' => 'tool-library-2']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => 'Tool-Library',
            'contact_email' => 'hello@example.test',
        ]);

        $this->assertSame('tool-library-3', Organization::where('name', 'Tool-Library')->sole()->slug);
    }

    public function test_requires_an_organization_name_and_contact_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), []);

        $response->assertSessionHasErrors([
            'organization_name' => 'The organization name field is required.',
            'contact_email' => 'The contact email field is required.',
        ]);
        $this->assertDatabaseCount(Organization::class, 0);
    }

    public function test_rejects_a_name_longer_than_120_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organization-requests.store'), [
            'organization_name' => str_repeat('a', 121),
            'contact_email' => 'hello@example.test',
        ]);

        $response->assertSessionHasErrors(['organization_name' => 'The organization name field must not be greater than 120 characters.']);
    }

    public function test_guest_must_confirm_they_are_an_adult(): void
    {
        $response = $this->post(route('organization-requests.store'), [
            ...$this->accountFields(),
            'adult_confirmation' => null,
            'organization_name' => 'Tool Library',
            'contact_email' => 'hello@toollibrary.test',
        ]);

        $response->assertSessionHasErrors([
            'adult_confirmation' => 'You must confirm you are 18 or older. This platform is for adults only.',
        ]);
        $this->assertDatabaseMissing(User::class, ['email' => 'sam@example.test']);
        $this->assertDatabaseCount(Organization::class, 0);
        $this->assertGuest();
    }

    public function test_organization_requests_are_throttled(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('organization-requests.store'))->assertRedirect();
        }

        $response = $this->post(route('organization-requests.store'));

        $response->assertTooManyRequests();
    }

    public function test_dashboard_shows_a_pending_request_as_awaiting_approval(): void
    {
        $user = User::factory()->create();
        Organization::factory()->pending()->for($user, 'requester')->create(['name' => 'Tool Library']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Your organization requests', 'Tool Library', 'Awaiting approval']);
    }

    public function test_dashboard_shows_the_reason_a_request_was_rejected(): void
    {
        $user = User::factory()->create();
        Organization::factory()->rejected()->for($user, 'requester')->create([
            'name' => 'Tool Library',
            'rejection_reason' => 'We could not confirm the organization exists.',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeTextInOrder(['Your organization requests', 'Tool Library', 'Not approved', 'We could not confirm the organization exists.']);
    }

    /**
     * @return array{name: string, email: string, password: string, password_confirmation: string, adult_confirmation: string}
     */
    private function accountFields(): array
    {
        return [
            'name' => 'Sam Starter',
            'email' => 'sam@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'adult_confirmation' => '1',
        ];
    }
}
