<?php

namespace Tests\Feature\Organizations;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_sees_the_sign_up_link(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.settings.edit', $organization));

        $response->assertSee('value="'.route('join.show', $organization->signup_token).'"', false);
    }

    public function test_offers_a_copy_button_for_the_sign_up_link(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->get(route('orgs.settings.edit', $organization));

        $response->assertSee('data-copy-target="signup_link"', false);
        $response->assertSeeText('Copy link');
    }

    public function test_updates_the_name_and_contact_email_and_keeps_the_slug(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North', 'slug' => 'food-bank-north']);
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->patch(route('orgs.settings.update', $organization), [
            'name' => 'Northside Food Bank',
            'contact_email' => 'Team@Northside.TEST',
            'self_signup_enabled' => '1',
        ]);

        $response->assertRedirect(route('orgs.settings.edit', $organization));
        $response->assertSessionHas('status', 'Settings saved.');

        $organization->refresh();
        $this->assertSame('Northside Food Bank', $organization->name);
        $this->assertSame('team@northside.test', $organization->contact_email);
        $this->assertSame('food-bank-north', $organization->slug);
    }

    public function test_allows_changing_only_the_case_of_the_organizations_own_name(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->patch(route('orgs.settings.update', $organization), [
            'name' => 'FOOD BANK NORTH',
            'contact_email' => 'hello@foodbank.test',
            'self_signup_enabled' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('FOOD BANK NORTH', $organization->fresh()->name);
    }

    public function test_refuses_a_name_another_organization_has(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        Organization::factory()->active()->create(['name' => 'River Cleanup']);
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->patch(route('orgs.settings.update', $organization), [
            'name' => 'river cleanup',
            'contact_email' => 'hello@foodbank.test',
            'self_signup_enabled' => '1',
        ]);

        $response->assertSessionHasErrors(['name' => 'That organization name is already taken.']);
        $this->assertSame('Food Bank North', $organization->fresh()->name);
    }

    public function test_turning_off_self_sign_up_closes_the_sign_up_link(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $this->actingAs($administrator)->patch(route('orgs.settings.update', $organization), [
            'name' => $organization->name,
            'contact_email' => $organization->contact_email,
            'self_signup_enabled' => '0',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('join.show', $organization->signup_token));

        $this->assertFalse($organization->fresh()->self_signup_enabled);
        $response->assertSeeText("This organization isn't accepting sign-ups right now.");
    }

    public function test_regenerating_the_sign_up_link_stops_the_old_one_working(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $administrator = $this->administratorOf($organization);
        $oldToken = $organization->signup_token;

        $response = $this->actingAs($administrator)->post(route('orgs.signup-link.store', $organization));

        $response->assertRedirect(route('orgs.settings.edit', $organization));
        $newToken = $organization->fresh()->signup_token;
        $this->assertNotSame($oldToken, $newToken);
        $visitor = User::factory()->create();
        $this->actingAs($visitor)->get(route('join.show', $oldToken))->assertSeeText('This link is no longer valid.');
        $this->actingAs($visitor)->get(route('join.show', $newToken))->assertSeeText('Join Food Bank North');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function settingsChanges(): array
    {
        return [
            'update settings' => ['patch', 'orgs.settings.update'],
            'regenerate the sign-up link' => ['post', 'orgs.signup-link.store'],
        ];
    }

    #[DataProvider('settingsChanges')]
    public function test_volunteer_gets_403(string $method, string $routeName): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        $token = $organization->signup_token;

        $response = $this->actingAs($volunteer)->{$method}(route($routeName, $organization), [
            'name' => 'Taken Over',
            'contact_email' => 'evil@example.test',
            'self_signup_enabled' => '0',
        ]);

        $response->assertForbidden();
        $organization->refresh();
        $this->assertSame('Food Bank North', $organization->name);
        $this->assertSame($token, $organization->signup_token);
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
