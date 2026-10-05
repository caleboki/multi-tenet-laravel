<?php

namespace Tests\Feature\Roster;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RosterIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_sees_every_entry_of_their_organization_with_its_status(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $this->memberOf($organization, 'Ian Inactive')->inactive()->create();
        $this->memberOf($organization, 'Lena Left')->left()->create();
        $this->memberOf($organization, 'Pat Pending')->pending()->create();
        $this->memberOf($organization, 'Victor Volunteer')->active()->create();
        Invitation::factory()->for($organization)->create(['name' => 'Ivy Invited']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $organization));

        $response->assertSeeTextInOrder([
            'Alice Admin', 'Active',
            'Ian Inactive', 'Inactive',
            'Ivy Invited', 'Invited',
            'Lena Left', 'Left',
            'Pat Pending', 'Pending approval',
            'Victor Volunteer', 'Active',
        ]);
    }

    public function test_roster_never_lists_members_or_invitations_of_another_organization(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $otherOrganization = Organization::factory()->active()->create();
        $this->memberOf($otherOrganization, 'Oscar Other')->active()->create();
        Invitation::factory()->for($otherOrganization)->create(['name' => 'Olga Outside']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $organization));

        $response->assertSeeText('Alice Admin');
        $response->assertDontSeeText('Oscar Other');
        $response->assertDontSeeText('Olga Outside');
    }

    public function test_shows_each_members_email_role_and_date_joined(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $volunteer = User::factory()->create(['name' => 'Victor Volunteer', 'email' => 'victor@example.test']);
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create([
            'joined_at' => '2026-09-14 10:00:00',
        ]);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $organization));

        $response->assertSeeTextInOrder(['Victor Volunteer', 'victor@example.test', 'Volunteer', 'Active', '14 Sep 2026']);
    }

    public function test_hides_join_requests_from_people_who_have_not_verified_their_email(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $unverified = User::factory()->unverified()->create(['name' => 'Uma Unverified']);
        Membership::factory()->for($organization)->for($unverified)->volunteer()->pending()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $organization));

        $response->assertSeeText('Alice Admin');
        $response->assertDontSeeText('Uma Unverified');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function searches(): array
    {
        return [
            'name, ignoring case' => ['SMITH', 'Alice Smith', 'Bob Jones'],
            'email, ignoring case' => ['JONES@EXAMPLE', 'Bob Jones', 'Alice Smith'],
            'invitation name' => ['ivy', 'Ivy Invited', 'Alice Smith'],
        ];
    }

    #[DataProvider('searches')]
    public function test_search_matches_name_or_email(string $search, string $expectedName, string $excludedName): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Admin Person');
        $this->memberOf($organization, 'Alice Smith', 'a.s@example.test')->active()->create();
        $this->memberOf($organization, 'Bob Jones', 'bob.jones@example.test')->active()->create();
        Invitation::factory()->for($organization)->create(['name' => 'Ivy Invited', 'email' => 'ivy@example.test']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => $search]));

        $response->assertSeeText($expectedName);
        $response->assertDontSeeText($excludedName);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function searchesWithWildcardCharacters(): array
    {
        return [
            'percent sign' => ['100%', '100% Committed', '1000 Hours'],
            'underscore' => ['J_ne', 'J_ne Doe', 'Jane Doe'],
        ];
    }

    #[DataProvider('searchesWithWildcardCharacters')]
    public function test_search_treats_wildcard_characters_literally(string $search, string $expectedName, string $excludedName): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Admin Person');
        $this->memberOf($organization, $expectedName)->active()->create();
        $this->memberOf($organization, $excludedName)->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => $search]));

        $response->assertSeeText($expectedName);
        $response->assertDontSeeText($excludedName);
    }

    public function test_search_for_a_member_of_another_organization_returns_no_results(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Admin Person');
        $this->memberOf(Organization::factory()->active()->create(), 'River Person', 'river@example.test')->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => 'river']));

        $response->assertDontSeeText('River Person');
        $response->assertSeeText('No one in the roster matches.');
    }

    public function test_role_filter_lists_only_entries_with_that_role(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $this->memberOf($organization, 'Victor Volunteer')->volunteer()->active()->create();
        Invitation::factory()->for($organization)->administrator()->create(['name' => 'Ivan Invitee']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'role' => 'administrator']));

        $response->assertSeeText('Alice Admin');
        $response->assertSeeText('Ivan Invitee');
        $response->assertDontSeeText('Victor Volunteer');
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function statusFilters(): array
    {
        return [
            'invited' => ['invited', 'Ivy Invited', ['Ian Inactive', 'Alice Admin']],
            'inactive' => ['inactive', 'Ian Inactive', ['Ivy Invited', 'Alice Admin']],
        ];
    }

    /**
     * @param  list<string>  $excludedNames
     */
    #[DataProvider('statusFilters')]
    public function test_status_filter_lists_only_entries_with_that_status(string $status, string $expectedName, array $excludedNames): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $this->memberOf($organization, 'Ian Inactive')->inactive()->create();
        Invitation::factory()->for($organization)->create(['name' => 'Ivy Invited']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'status' => $status]));

        $response->assertSeeText($expectedName);

        foreach ($excludedNames as $excludedName) {
            $response->assertDontSeeText($excludedName);
        }
    }

    public function test_rejects_an_unknown_role_or_status_filter(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'role' => 'owner', 'status' => 'banned']));

        $response->assertSessionHasErrors([
            'role' => 'The selected role is invalid.',
            'status' => 'The selected status is invalid.',
        ]);
    }

    public function test_lists_25_entries_per_page_and_keeps_the_search_in_page_links(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Admin Person');
        Membership::factory()
            ->count(26)
            ->for($organization)
            ->volunteer()
            ->active()
            ->sequence(fn (Sequence $sequence): array => [
                'user_id' => User::factory()->state(['name' => sprintf('Volunteer %02d', $sequence->index + 1)]),
            ])
            ->create();

        $firstPage = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => 'Volunteer']));
        $secondPage = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => 'Volunteer', 'page' => 2]));

        $firstPage->assertSeeText('Volunteer 25');
        $firstPage->assertDontSeeText('Volunteer 26');
        $firstPage->assertSee(route('orgs.members.index', [$organization, 'q' => 'Volunteer', 'page' => 2]));

        $secondPage->assertSeeText('Volunteer 26');
        $secondPage->assertDontSeeText('Volunteer 25');
    }

    public function test_escapes_member_names(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        $this->memberOf($organization, "Mallory <script>alert('x')</script>")->active()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', $organization));

        $response->assertSee('Mallory &lt;script&gt;', false);
        $response->assertDontSee("<script>alert('x')</script>", false);
    }

    public function test_volunteer_gets_403(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->get(route('orgs.members.index', $organization));

        $response->assertForbidden();
        $response->assertSeeText("You don't have access to this page");
    }

    private function administratorOf(Organization $organization, string $name): User
    {
        $administrator = User::factory()->create(['name' => $name]);
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    /**
     * Start a volunteer membership for a new user with the given name, so the caller picks its status.
     */
    private function memberOf(Organization $organization, string $name, ?string $email = null): MembershipFactory
    {
        $user = User::factory()->create(array_filter(['name' => $name, 'email' => $email]));

        return Membership::factory()->for($organization)->for($user)->volunteer();
    }
}
