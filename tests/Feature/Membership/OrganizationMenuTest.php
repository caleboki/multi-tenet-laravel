<?php

namespace Tests\Feature\Membership;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{Closure(Organization): string}>
     */
    public static function organizationPages(): array
    {
        return [
            'home' => [fn (Organization $organization): string => route('orgs.show', $organization)],
            'roster' => [fn (Organization $organization): string => route('orgs.members.index', $organization)],
            'invite' => [fn (Organization $organization): string => route('orgs.invitations.create', $organization)],
            'join requests' => [fn (Organization $organization): string => route('orgs.join-requests.index', $organization)],
            'import' => [fn (Organization $organization): string => route('orgs.imports.create', $organization)],
            'settings' => [fn (Organization $organization): string => route('orgs.settings.edit', $organization)],
        ];
    }

    /**
     * @param  Closure(Organization): string  $url
     */
    #[DataProvider('organizationPages')]
    public function test_administrator_sees_the_organization_menu_on_every_organization_page(Closure $url): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Membership::factory()->count(2)->for($organization)->volunteer()->pending()->create();

        $response = $this->actingAs($administrator)->get($url($organization));

        $response->assertSeeInOrder([
            route('orgs.show', $organization),
            route('orgs.members.index', $organization),
            route('orgs.invitations.create', $organization),
            route('orgs.join-requests.index', $organization),
            route('orgs.imports.create', $organization),
            route('orgs.settings.edit', $organization),
        ]);
        $response->assertSeeText('Join requests (2)');
    }

    public function test_menu_marks_the_current_page(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->get(route('orgs.settings.edit', $organization));

        $response->assertSee('href="'.route('orgs.settings.edit', $organization).'" aria-current="page"', false);
        $response->assertDontSee('href="'.route('orgs.members.index', $organization).'" aria-current="page"', false);
    }

    public function test_menu_marks_the_roster_while_viewing_a_member(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $member = Membership::factory()->for($organization)->volunteer()->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.show', [$organization, $member]));

        $response->assertSee('href="'.route('orgs.members.index', $organization).'" aria-current="page"', false);
    }

    public function test_volunteer_sees_no_organization_menu(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->get(route('orgs.show', $organization));

        $response->assertDontSee('href="'.route('orgs.join-requests.index', $organization).'"', false);
        $response->assertDontSee('href="'.route('orgs.settings.edit', $organization).'"', false);
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }
}
