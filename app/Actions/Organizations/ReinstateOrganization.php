<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReinstateOrganization
{
    /**
     * Reinstate a suspended organization, giving its members their access back (FR-013).
     * No email is sent, because the spec lists none.
     *
     * @throws ConflictHttpException when the organization is not suspended.
     */
    public function handle(Organization $organization): Organization
    {
        return DB::transaction(function () use ($organization): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            if ($organization->status !== OrganizationStatus::Suspended) {
                throw new ConflictHttpException('Only a suspended organization can be reinstated.');
            }

            $organization->forceFill(['status' => OrganizationStatus::Active])->save();

            return $organization;
        });
    }
}
