<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Notifications\JoinRequestApproved;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveJoinRequest
{
    /**
     * Make the person an active volunteer of the organization and tell them (FR-028).
     *
     * @throws ConflictHttpException when the membership is not a request awaiting approval.
     */
    public function handle(Organization $organization, Membership $joinRequest): Membership
    {
        if (! $joinRequest->isAwaitingApproval()) {
            throw new ConflictHttpException('This join request has already been handled.');
        }

        $joinRequest->forceFill([
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ])->save();

        $joinRequest->user->notify(new JoinRequestApproved($organization));

        return $joinRequest;
    }
}
