<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ChangeMembership
{
    /**
     * Change a current member's role or status: promote, demote, deactivate, reactivate
     * or leave (FR-017, FR-024, FR-038). A null argument leaves that value as it is.
     *
     * Every change that could remove an active administrator goes through here, so the
     * organization always keeps one (FR-018). The organization row is locked for the
     * transaction, which makes concurrent changes in the same organization wait for each
     * other. Without the lock, two administrators demoting each other at the same moment
     * could both pass the check and leave none (research R8). The membership is re-read
     * under the lock so the check uses its current values.
     *
     * Reactivating keeps the original date joined.
     *
     * @throws ValidationException when no other active administrator would remain.
     * @throws ConflictHttpException when the membership is a join request or the person has left.
     */
    public function handle(Membership $membership, ?MembershipRole $role, ?MembershipStatus $status): Membership
    {
        return DB::transaction(function () use ($membership, $role, $status): Membership {
            Organization::query()->whereKey($membership->organization_id)->lockForUpdate()->first();
            $membership->refresh();

            if (! in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Inactive], true)) {
                throw new ConflictHttpException('Only current members can be changed here.');
            }

            $newRole = $role ?? $membership->role;
            $newStatus = $status ?? $membership->status;

            $removesAnActiveAdministrator = $membership->isAdministrator()
                && $membership->isActive()
                && ($newRole !== MembershipRole::Administrator || $newStatus !== MembershipStatus::Active);

            if ($removesAnActiveAdministrator && ! $this->hasAnotherActiveAdministrator($membership)) {
                throw ValidationException::withMessages([
                    'membership' => 'The organization must keep at least one active administrator.',
                ]);
            }

            $membership->forceFill([
                'role' => $newRole,
                'status' => $newStatus,
            ])->save();

            return $membership;
        });
    }

    /**
     * Determine whether the organization has an active administrator other than this membership.
     */
    private function hasAnotherActiveAdministrator(Membership $membership): bool
    {
        return Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->administrators()
            ->whereKeyNot($membership->getKey())
            ->exists();
    }
}
