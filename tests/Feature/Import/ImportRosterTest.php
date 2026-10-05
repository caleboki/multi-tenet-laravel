<?php

namespace Tests\Feature\Import;

use App\Enums\MembershipRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportRosterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invites_every_row_of_a_valid_file_as_a_volunteer(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $rows = collect(range(1, 200))->map(fn (int $number): string => "Person {$number},person{$number}@example.org");
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload("name,email\n".$rows->implode("\n")),
        ]);

        $response->assertRedirect(route('orgs.imports.create', $organization));
        $response->assertSessionHas('importReport', ['invited' => 200, 'skipped' => []]);
        $this->assertSame(200, $organization->invitations()->where('role', MembershipRole::Volunteer)->count());
        $this->assertSame($administrator->id, $organization->invitations()->value('invited_by_id'));
        Notification::assertSentOnDemandTimes(InvitationNotification::class, 200);
    }

    public function test_skips_rows_that_cannot_be_invited_and_says_why(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Membership::factory()->for($organization)->for(User::factory()->state(['email' => 'member@example.org']))->volunteer()->active()->create();
        Membership::factory()->for($organization)->for(User::factory()->state(['email' => 'inactive@example.org']))->volunteer()->inactive()->create();
        Membership::factory()->for($organization)->for(User::factory()->state(['email' => 'left@example.org']))->volunteer()->left()->create();
        Invitation::factory()->for($organization)->create(['email' => 'invited@example.org']);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload(implode("\n", [
                'name,email',
                'Ada Lovelace,ada@example.org',
                ',nameless@example.org',
                'Bad Address,not-an-email',
                'Ada Again,ADA@example.org',
                'Member,member@example.org',
                'Inactive,inactive@example.org',
                'Invited,invited@example.org',
                'Left Earlier,left@example.org',
            ])),
        ]);

        $response->assertSessionHas('importReport', [
            'invited' => 2,
            'skipped' => [
                ['row' => 3, 'email' => 'nameless@example.org', 'reason' => 'Missing name'],
                ['row' => 4, 'email' => 'not-an-email', 'reason' => 'Invalid email'],
                ['row' => 5, 'email' => 'ADA@example.org', 'reason' => 'Duplicate of row 2'],
                ['row' => 6, 'email' => 'member@example.org', 'reason' => 'Already in roster'],
                ['row' => 7, 'email' => 'inactive@example.org', 'reason' => 'Already in roster'],
                ['row' => 8, 'email' => 'invited@example.org', 'reason' => 'Already in roster'],
            ],
        ]);
        $this->assertSame(
            ['ada@example.org', 'invited@example.org', 'left@example.org'],
            $organization->invitations()->orderBy('email')->pluck('email')->all(),
        );
        Notification::assertSentOnDemandTimes(InvitationNotification::class, 2);
    }

    public function test_invites_someone_who_belongs_only_to_another_organization_without_saying_so(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $otherOrganization = Organization::factory()->active()->create(['name' => 'River Cleanup']);
        Membership::factory()->for($otherOrganization)->for(User::factory()->state(['email' => 'river@example.org']))->administrator()->active()->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->followingRedirects()->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload("name,email\nRiver Admin,river@example.org"),
        ]);

        $response->assertSeeTextInOrder(['1 invited', '0 skipped']);
        $response->assertDontSeeText('River Cleanup');
        $this->assertSame('river@example.org', $organization->invitations()->sole()->email);
    }

    public function test_report_page_lists_the_skipped_rows(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Notification::fake();

        $response = $this->actingAs($administrator)->followingRedirects()->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload("name,email\nAda Lovelace,ada@example.org\nAda Again,ada@example.org"),
        ]);

        $response->assertSeeTextInOrder(['1 invited', '1 skipped', '3', 'ada@example.org', 'Duplicate of row 2']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function filesRejectedAsAWhole(): array
    {
        return [
            'not a text file' => ["\x00\x01\x02\x03\xff\xfe\x00", 'The file could not be read. Upload a CSV file.'],
            'missing columns' => ["first name,last name\nAda,Lovelace", 'The file must have a header row with name and email columns.'],
            'more than 1,000 rows' => ["name,email\n".str_repeat("Ada,ada@example.org\n", 1001), 'The file has 1,001 rows. The limit is 1,000.'],
            'no rows' => ["name,email\n", 'The file has no rows to import.'],
        ];
    }

    #[DataProvider('filesRejectedAsAWhole')]
    public function test_rejects_the_whole_file_and_invites_no_one(string $contents, string $message): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload($contents),
        ]);

        $response->assertSessionHasErrors(['file' => $message]);
        $this->assertSame(0, $organization->invitations()->count());
        Notification::assertNothingSent();
    }

    public function test_requires_a_file(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        $response = $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), []);

        $response->assertSessionHasErrors(['file' => 'The file field is required.']);
    }

    public function test_volunteer_gets_403_and_no_one_is_invited(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        Membership::factory()->for($organization)->for($volunteer)->volunteer()->active()->create();
        Notification::fake();

        $response = $this->actingAs($volunteer)->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload("name,email\nAda Lovelace,ada@example.org"),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, $organization->invitations()->count());
        Notification::assertNothingSent();
    }

    public function test_imports_are_limited_to_five_a_minute(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), [
                'file' => $this->csvUpload("name,email\n"),
            ])->assertRedirect();
        }

        $response = $this->actingAs($administrator)->post(route('orgs.imports.store', $organization), [
            'file' => $this->csvUpload("name,email\n"),
        ]);

        $response->assertTooManyRequests();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    private function csvUpload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('roster.csv', $contents);
    }
}
