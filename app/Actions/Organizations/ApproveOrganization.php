<?php

namespace App\Actions\Organizations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Notifications\OrganizationApproved;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveOrganization
{
    /**
     * Approve a pending organization (FR-012). It becomes active with a sign-up link, and
     * its requester becomes its first active administrator.
     *
     * The organization row is locked for the transaction, so two operators reviewing at
     * the same time can't both act on it.
     *
     * @throws ConflictHttpException when the organization is no longer pending.
     */
    public function handle(Organization $organization): Organization
    {
        return DB::transaction(function () use ($organization): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            if ($organization->status !== OrganizationStatus::Pending) {
                throw new ConflictHttpException('This organization request has already been reviewed.');
            }

            $organization->status = OrganizationStatus::Active;
            $organization->regenerateSignupToken();

            $organization->memberships()->make()->forceFill([
                'user_id' => $organization->requested_by_id,
                'role' => MembershipRole::Administrator,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ])->save();

            $organization->requester->notify(new OrganizationApproved($organization));

            return $organization;
        });
    }
}
