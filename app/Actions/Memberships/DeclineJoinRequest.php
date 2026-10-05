<?php

namespace App\Actions\Memberships;

use App\Models\Membership;
use App\Models\Organization;
use App\Notifications\JoinRequestDeclined;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DeclineJoinRequest
{
    /**
     * Remove the request without granting access, and tell the person (FR-028).
     *
     * The membership row is deleted, so the request leaves the roster and the person
     * may ask again later.
     *
     * @throws ConflictHttpException when the membership is not a request awaiting approval.
     */
    public function handle(Organization $organization, Membership $joinRequest): void
    {
        if (! $joinRequest->isAwaitingApproval()) {
            throw new ConflictHttpException('This join request has already been handled.');
        }

        $joinRequest->delete();

        $joinRequest->user->notify(new JoinRequestDeclined($organization));
    }
}
