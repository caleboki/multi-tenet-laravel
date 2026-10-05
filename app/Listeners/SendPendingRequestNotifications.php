<?php

namespace App\Listeners;

use App\Actions\Memberships\RequestToJoin;
use App\Actions\Organizations\RequestOrganization;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class SendPendingRequestNotifications
{
    public function __construct(
        private RequestToJoin $requestToJoin,
        private RequestOrganization $requestOrganization,
    ) {}

    /**
     * Send the requests that were waiting for the person to verify their email (R9, FR-041):
     * join requests go to each organization's administrators, and organization requests
     * go to the platform operators.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->memberships()
            ->where('status', MembershipStatus::Pending)
            ->with('organization')
            ->get()
            ->each(fn (Membership $joinRequest) => $this->requestToJoin->notifyAdministrators($joinRequest->organization, $user));

        Organization::query()
            ->whereBelongsTo($user, 'requester')
            ->where('status', OrganizationStatus::Pending)
            ->get()
            ->each(fn (Organization $organization) => $this->requestOrganization->notifyOperators($organization, $user));
    }
}
