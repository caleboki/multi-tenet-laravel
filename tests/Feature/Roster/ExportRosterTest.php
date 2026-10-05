<?php

namespace Tests\Feature\Roster;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportRosterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_downloads_a_csv_named_after_the_organization_and_date(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create(['slug' => 'food-bank-north']);
        $administrator = $this->administratorOf($organization, 'Alice Admin');

        $response = $this->actingAs($administrator)->get(route('orgs.members.export', $organization));

        $response->assertDownload('food-bank-north-roster-2026-10-05.csv');
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBFName,Email,Phone,Role,Status,", $response->streamedContent());
    }

    public function test_includes_every_entry_of_the_organization_and_no_other(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        Membership::factory()->for($organization)->for(User::factory()->state([
            'name' => 'Victor Volunteer',
            'email' => 'victor@example.org',
            'phone' => '0123 456',
        ]))->volunteer()->active()->create(['joined_at' => '2026-09-14 10:00:00']);
        Invitation::factory()->for($organization)->create(['name' => 'Ivy Invited', 'email' => 'ivy@example.org']);
        $otherOrganization = Organization::factory()->active()->create();
        Membership::factory()->for($otherOrganization)->for(User::factory()->state(['name' => 'Oscar Other']))->volunteer()->active()->create();
        Invitation::factory()->for($otherOrganization)->create(['name' => 'Olga Outside']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.export', $organization));

        $records = $this->records($response);
        $this->assertSame(['Alice Admin', 'Ivy Invited', 'Victor Volunteer'], array_column($records, 0));
        $this->assertSame(['Victor Volunteer', 'victor@example.org', '0123 456', 'Volunteer', 'Active', '2026-09-14'], $records[2]);
        $this->assertSame(['Ivy Invited', 'ivy@example.org', '', 'Volunteer', 'Invited', ''], $records[1]);
    }

    public function test_applies_the_rosters_search_and_filters(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');
        Membership::factory()->for($organization)->for(User::factory()->state(['name' => 'Victor Volunteer']))->volunteer()->active()->create();
        Membership::factory()->for($organization)->for(User::factory()->state(['name' => 'Vera Volunteer']))->volunteer()->active()->create();
        Membership::factory()->for($organization)->for(User::factory()->state(['name' => 'Vince Inactive']))->volunteer()->inactive()->create();
        Invitation::factory()->for($organization)->create(['name' => 'Violet Invited']);

        $response = $this->actingAs($administrator)->get(route('orgs.members.export', [
            $organization,
            'q' => 'v',
            'role' => 'volunteer',
            'status' => 'active',
        ]));

        $this->assertSame(['Vera Volunteer', 'Victor Volunteer'], array_column($this->records($response), 0));
    }

    public function test_sample_file_shows_the_expected_columns(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');

        $response = $this->actingAs($administrator)->get(route('orgs.imports.sample', $organization));

        $response->assertDownload('roster-import-sample.csv');
        $this->assertSame("name,email\nAda Lovelace,ada@example.org\nGrace Hopper,grace@example.org\n", $response->streamedContent());
    }

    /**
     * @return array<string, array{Closure(Organization): string}>
     */
    public static function administratorOnlyDownloads(): array
    {
        return [
            'roster export' => [fn (Organization $organization): string => route('orgs.members.export', $organization)],
            'sample file' => [fn (Organization $organization): string => route('orgs.imports.sample', $organization)],
            'import page' => [fn (Organization $organization): string => route('orgs.imports.create', $organization)],
        ];
    }

    /**
     * @param  Closure(Organization): string  $url
     */
    #[DataProvider('administratorOnlyDownloads')]
    public function test_volunteer_gets_403(Closure $url): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();

        $response = $this->actingAs($volunteer)->get($url($organization));

        $response->assertForbidden();
    }

    public function test_roster_links_to_the_export_with_the_current_filters_and_to_the_import(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization, 'Alice Admin');

        $response = $this->actingAs($administrator)->get(route('orgs.members.index', [$organization, 'q' => 'ada', 'status' => 'active']));

        $response->assertSee(route('orgs.members.export', [$organization, 'q' => 'ada', 'status' => 'active']));
        $response->assertSee(route('orgs.imports.create', $organization));
    }

    private function administratorOf(Organization $organization, string $name): User
    {
        $administrator = User::factory()->create(['name' => $name]);
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    /**
     * Read the data records of a downloaded roster, skipping the byte-order mark and header.
     *
     * @return list<list<string>>
     */
    private function records(TestResponse $response): array
    {
        $handle = fopen('php://memory', 'w+');
        fwrite($handle, substr($response->streamedContent(), 3));
        rewind($handle);

        $records = [];

        while (($record = fgetcsv($handle, escape: '')) !== false) {
            $records[] = $record;
        }

        fclose($handle);

        return array_slice($records, 1);
    }
}
