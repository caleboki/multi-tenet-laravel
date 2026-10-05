<?php

namespace Tests\Feature\JoinRequests;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestApproved;
use App\Notifications\JoinRequestDeclined;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewJoinRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_verified_pending_requests_of_the_organization(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $requester = User::factory()->create(['name' => 'Pat Pending', 'email' => 'pat@example.test']);
        Membership::factory()->for($organization)->for($requester)->volunteer()->pending()->create([
            'requested_at' => '2026-10-01 09:00:00',
        ]);
        $this->membershipFor($organization, User::factory()->unverified()->create(['name' => 'Uma Unverified']))->pending()->create();
        $this->membershipFor($organization, User::factory()->create(['name' => 'Victor Volunteer']))->active()->create();
        $this->membershipFor(Organization::factory()->active()->create(), User::factory()->create(['name' => 'Oscar Other']))->pending()->create();

        $response = $this->actingAs($administrator)->get(route('orgs.join-requests.index', $organization));

        $response->assertSeeTextInOrder(['Pat Pending', 'pat@example.test', '1 Oct 2026']);
        $response->assertDontSeeText('Uma Unverified');
        $response->assertDontSeeText('Victor Volunteer');
        $response->assertDontSeeText('Oscar Other');
    }

    public function test_lists_25_requests_per_page_oldest_first(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        Membership::factory()
            ->count(26)
            ->for($organization)
            ->volunteer()
            ->pending()
            ->sequence(fn (Sequence $sequence): array => [
                'user_id' => User::factory()->state(['name' => sprintf('Requester %02d', $sequence->index + 1)]),
                'requested_at' => now()->subDays(30)->addHours($sequence->index),
            ])
            ->create();

        $firstPage = $this->actingAs($administrator)->get(route('orgs.join-requests.index', $organization));
        $secondPage = $this->actingAs($administrator)->get(route('orgs.join-requests.index', [$organization, 'page' => 2]));

        $firstPage->assertSeeTextInOrder(['Requester 01', 'Requester 25']);
        $firstPage->assertDontSeeText('Requester 26');
        $secondPage->assertSeeText('Requester 26');
        $secondPage->assertDontSeeText('Requester 25');
    }

    public function test_approving_makes_the_person_an_active_volunteer_and_notifies_them(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $requester = User::factory()->create(['name' => 'Pat Pending']);
        $joinRequest = $this->membershipFor($organization, $requester)->pending()->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->post(route('orgs.join-requests.approve', [$organization, $joinRequest]));

        $response->assertRedirect(route('orgs.join-requests.index', $organization));
        $response->assertSessionHas('status', "Approved Pat Pending's request to join.");

        $joinRequest->refresh();
        $this->assertSame(MembershipStatus::Active, $joinRequest->status);
        $this->assertSame('2026-10-05 12:00:00', $joinRequest->joined_at->toDateTimeString());

        Notification::assertSentTo(
            $requester,
            JoinRequestApproved::class,
            fn (JoinRequestApproved $notification): bool => $notification->organization->is($organization),
        );
    }

    public function test_declining_removes_the_request_and_notifies_the_person(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $requester = User::factory()->create(['name' => 'Pat Pending']);
        $joinRequest = $this->membershipFor($organization, $requester)->pending()->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->delete(route('orgs.join-requests.destroy', [$organization, $joinRequest]));

        $response->assertRedirect(route('orgs.join-requests.index', $organization));
        $response->assertSessionHas('status', "Declined Pat Pending's request to join.");
        $this->assertModelMissing($joinRequest);

        Notification::assertSentTo(
            $requester,
            JoinRequestDeclined::class,
            fn (JoinRequestDeclined $notification): bool => $notification->organization->is($organization),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function reviewActions(): array
    {
        return [
            'approve' => ['post', 'orgs.join-requests.approve'],
            'decline' => ['delete', 'orgs.join-requests.destroy'],
        ];
    }

    #[DataProvider('reviewActions')]
    public function test_refuses_to_review_a_membership_that_is_not_a_request(string $method, string $routeName): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $member = $this->membershipFor($organization, User::factory()->create())->inactive()->create();
        Notification::fake();

        $response = $this->actingAs($administrator)->{$method}(route($routeName, [$organization, $member]));

        $response->assertConflict();
        $this->assertSame(MembershipStatus::Inactive, $member->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_refuses_to_approve_a_request_whose_email_is_not_verified(): void
    {
        $organization = Organization::factory()->active()->create();
        $administrator = $this->administratorOf($organization);
        $joinRequest = $this->membershipFor($organization, User::factory()->unverified()->create())->pending()->create();

        $response = $this->actingAs($administrator)->post(route('orgs.join-requests.approve', [$organization, $joinRequest]));

        $response->assertConflict();
        $this->assertSame(MembershipStatus::Pending, $joinRequest->fresh()->status);
    }

    public function test_volunteer_gets_403(): void
    {
        $organization = Organization::factory()->active()->create();
        $volunteer = User::factory()->create();
        $this->membershipFor($organization, $volunteer)->active()->create();

        $response = $this->actingAs($volunteer)->get(route('orgs.join-requests.index', $organization));

        $response->assertForbidden();
    }

    private function administratorOf(Organization $organization): User
    {
        $administrator = User::factory()->create();
        Membership::factory()->for($organization)->for($administrator)->administrator()->active()->create();

        return $administrator;
    }

    private function membershipFor(Organization $organization, User $user): MembershipFactory
    {
        return Membership::factory()->for($organization)->for($user)->volunteer();
    }
}
