<?php

namespace Tests\Feature\Membership;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VolunteerAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_volunteer_can_open_the_organization_home(): void
    {
        $organization = Organization::factory()->active()->create(['name' => 'Food Bank North']);
        $volunteer = $this->volunteerOf($organization);

        $response = $this->actingAs($volunteer)->get(route('orgs.show', $organization));

        $response->assertSeeText('Food Bank North');
        $response->assertDontSeeText('Join requests');
    }

    /**
     * @return array<string, array{string, Closure(Organization, Membership): string}>
     */
    public static function administratorOnlyPages(): array
    {
        return [
            'join requests' => ['get', fn (Organization $organization): string => route('orgs.join-requests.index', $organization)],
            'approve a join request' => ['post', fn (Organization $organization, Membership $joinRequest): string => route('orgs.join-requests.approve', [$organization, $joinRequest])],
            'settings' => ['get', fn (Organization $organization): string => route('orgs.settings.edit', $organization)],
        ];
    }

    /**
     * @param  Closure(Organization, Membership): string  $url
     */
    #[DataProvider('administratorOnlyPages')]
    public function test_volunteer_gets_403_on_administrator_pages(string $method, Closure $url): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = $this->volunteerOf($organization);
        $joinRequest = Membership::factory()->for($organization)->volunteer()->pending()->create();

        $response = $this->actingAs($volunteer)->{$method}($url($organization, $joinRequest));

        $response->assertForbidden();
        $this->assertSame(MembershipStatus::Pending, $joinRequest->fresh()->status);
    }

    private function volunteerOf(Organization $organization): User
    {
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        return $volunteer;
    }
}
