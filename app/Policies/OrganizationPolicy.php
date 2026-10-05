<?php

namespace App\Policies;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    /**
     * Determine whether the user may enter the organization.
     *
     * Anyone without an active membership in an active organization is told the
     * organization does not exist, so its existence is never revealed (FR-004).
     * Active members of a suspended organization are told it is suspended.
     */
    public function view(User $user, Organization $organization): Response
    {
        $membership = $user->membershipIn($organization);

        if (! $membership?->isActive()) {
            return Response::denyAsNotFound();
        }

        return match ($organization->status) {
            OrganizationStatus::Active => Response::allow(),
            OrganizationStatus::Suspended => Response::deny(code: 'organization-suspended'),
            default => Response::denyAsNotFound(),
        };
    }

    /**
     * Determine whether the user may manage the organization's members and settings.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        $membership = $user->membershipIn($organization);

        return $organization->status === OrganizationStatus::Active
            && $membership?->isActive() === true
            && $membership->isAdministrator();
    }
}
