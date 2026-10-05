<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationSuspended;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SuspendOrganization
{
    /**
     * Suspend an active organization so none of its members can open it, and tell its
     * active administrators (FR-013).
     *
     * @throws ConflictHttpException when the organization is not active.
     */
    public function handle(Organization $organization): Organization
    {
        return DB::transaction(function () use ($organization): Organization {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

            if ($organization->status !== OrganizationStatus::Active) {
                throw new ConflictHttpException('Only an active organization can be suspended.');
            }

            $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

            Notification::send(
                User::query()->administratorsOf($organization)->get(),
                new OrganizationSuspended($organization),
            );

            return $organization;
        });
    }
}
