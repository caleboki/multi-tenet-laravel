<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sweeps every registered organization and operator route, so routes added by later
 * stories are covered as soon as they exist (SC-001, research R15).
 */
class TenantIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_gets_404_on_every_route_of_another_organization(): void
    {
        $administrator = $this->administratorOf(Organization::factory()->active()->create());
        $otherOrganizationRecords = $this->recordsIn(Organization::factory()->active()->create());
        $routes = $this->routesNamed(['orgs.']);

        $unexpectedStatuses = $this->statusesOtherThan(404, $routes, $otherOrganizationRecords, $administrator);

        $this->assertNotEmpty($routes);
        $this->assertSame([], $unexpectedStatuses);
    }

    public function test_administrator_gets_404_when_a_child_record_belongs_to_another_organization(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $mixedRecords = [
            ...$this->recordsIn(Organization::factory()->active()->create()),
            'organization' => $organization,
        ];
        $routes = $this->routesNamed(['orgs.'])
            ->filter(fn (Route $route): bool => array_diff($route->parameterNames(), ['organization']) !== []);

        $unexpectedStatuses = $this->statusesOtherThan(404, $routes, $mixedRecords, $administrator);

        $this->assertNotEmpty($routes);
        $this->assertSame([], $unexpectedStatuses);
    }

    public function test_guest_is_redirected_to_login_from_every_signed_in_route(): void
    {
        $records = $this->recordsIn(Organization::factory()->active()->create());
        $routes = $this->routesNamed(['orgs.', 'operator.', 'dashboard', 'start', 'profile.edit']);
        $unexpectedResponses = [];

        foreach ($routes as $route) {
            foreach ($this->methodsOf($route) as $method) {
                $response = $this->call($method, $this->urlFor($route, $records));

                if (! $response->isRedirect(route('login'))) {
                    $unexpectedResponses["{$method} {$route->getName()}"] = $response->status();
                }
            }
        }

        $this->assertNotEmpty($routes);
        $this->assertSame([], $unexpectedResponses);
    }

    public function test_verified_user_who_is_not_an_operator_gets_403_on_every_operator_route(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $unexpectedStatuses = $this->statusesOtherThan(
            403,
            $this->routesNamed(['operator.']),
            $this->recordsIn($organization),
            $administrator,
        );

        $this->assertSame([], $unexpectedStatuses);
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    /**
     * Create one record of each kind a route parameter can name, all inside the given organization.
     *
     * @return array{organization: Organization, membership: Membership, invitation: Invitation}
     */
    private function recordsIn(Organization $organization): array
    {
        return [
            'organization' => $organization,
            'membership' => Membership::factory()->for($organization)->volunteer()->active()->create(),
            'invitation' => Invitation::factory()->for($organization)->create(),
        ];
    }

    /**
     * Get every registered route whose name starts with one of the given prefixes or equals one of the given names.
     *
     * @param  list<string>  $namesOrPrefixes
     * @return Collection<int, Route>
     */
    private function routesNamed(array $namesOrPrefixes): Collection
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith((string) $route->getName(), $namesOrPrefixes))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function methodsOf(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }

    /**
     * Request every method of every route as the given user and collect the responses with an unexpected status.
     *
     * @param  Collection<int, Route>  $routes
     * @param  array<string, mixed>  $records
     * @return array<string, int>
     */
    private function statusesOtherThan(int $expectedStatus, Collection $routes, array $records, User $user): array
    {
        $unexpectedStatuses = [];

        foreach ($routes as $route) {
            foreach ($this->methodsOf($route) as $method) {
                $status = $this->actingAs($user)->call($method, $this->urlFor($route, $records))->status();

                if ($status !== $expectedStatus) {
                    $unexpectedStatuses["{$method} {$route->getName()}"] = $status;
                }
            }
        }

        return $unexpectedStatuses;
    }

    /**
     * Build the route's URL from the records, failing when the route uses a parameter this test can't fill.
     *
     * @param  array<string, mixed>  $records
     */
    private function urlFor(Route $route, array $records): string
    {
        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            if (! array_key_exists($parameter, $records)) {
                $this->fail("Route [{$route->getName()}] uses the parameter {{$parameter}}, which TenantIsolationTest can't fill. Add a record for it to recordsIn().");
            }

            $parameters[$parameter] = $records[$parameter];
        }

        return route($route->getName(), $parameters);
    }
}
