<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Notifications\OrganizationRejected;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectOrganization
{
    /**
     * Reject a pending organization and send the requester the reason (FR-012).
     * Its name becomes available again.
     *
     * @throws ConflictHttpException when the organization is no longer pending.
     */
    public function handle(Organization $organization, string $reason): Organization
    {
        return DB::transaction(function () use ($organization, $reason): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            if ($organization->status !== OrganizationStatus::Pending) {
                throw new ConflictHttpException('This organization request has already been reviewed.');
            }

            $organization->forceFill([
                'status' => OrganizationStatus::Rejected,
                'rejection_reason' => $reason,
            ])->save();

            $organization->requester->notify(new OrganizationRejected($organization));

            return $organization;
        });
    }
}
