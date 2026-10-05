<?php

namespace Tests\Feature\JoinRequests;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JoinPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_sees_the_organization_name_account_fields_and_a_sign_in_link(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);

        $response = $this->get(route('join.show', $organization->signup_token));

        $response->assertSeeText('Join Food Bank North');
        $response->assertSee('name="password"', false);
        $response->assertSee('name="adult_confirmation"', false);
        $response->assertSee('href="'.route('login').'"', false);
    }

    public function test_signed_in_person_sees_a_request_button_without_account_fields(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('join.show', $organization->signup_token));

        $response->assertSeeText('Request to join');
        $response->assertDontSee('name="password"', false);
    }

    public function test_active_member_is_told_they_already_belong_and_taken_to_the_organization(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $member = User::factory()->create();
        Membership::factory()->for($organization)->for($member)->volunteer()->active()->create();

        $response = $this->actingAs($member)->get(route('join.show', $organization->signup_token));

        $response->assertRedirect(route('orgs.show', $organization));
        $response->assertSessionHas('status', "You're already a member of Food Bank North.");
    }

    public function test_unknown_link_says_it_is_no_longer_valid(): void
    {
        $response = $this->get(route('join.show', 'unknown-token'));

        $response->assertSeeText('This link is no longer valid.');
        $response->assertDontSee('name="password"', false);
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
    public function test_organization_that_is_not_accepting_sign_ups_says_so(Closure $createOrganization): void
    {
        $organization = $createOrganization();

        $response = $this->get(route('join.show', $organization->signup_token));

        $response->assertSeeText("This organization isn't accepting sign-ups right now.");
        $response->assertDontSee('name="password"', false);
    }
}
