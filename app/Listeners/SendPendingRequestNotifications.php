<?php

namespace App\Listeners;

use App\Actions\Memberships\RequestToJoin;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class SendPendingRequestNotifications
{
    public function __construct(private RequestToJoin $requestToJoin) {}

    /**
     * Send the requests that were waiting for the person to verify their email (R9, FR-041).
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
    }
}
