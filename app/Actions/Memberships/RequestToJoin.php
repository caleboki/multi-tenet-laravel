<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JoinRequestReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RequestToJoin
{
    /**
     * Ask to join the organization as a volunteer through its sign-up link (FR-026).
     *
     * The request is saved as pending straight away, but its administrators hear about
     * it only once the person's email is verified (R9). Until then it waits for the
     * SendPendingRequestNotifications listener. A person who left reuses their
     * membership, so each person keeps one membership per organization.
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $user): Membership
    {
        $membership = $user->membershipIn($organization);
        $refusal = $this->refusalFor($organization, $user, $membership);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['membership' => $refusal]);
        }

        if ($membership === null) {
            $membership = $user->memberships()->make();
            $membership->organization()->associate($organization);
        }

        $membership->forceFill([
            'role' => MembershipRole::Volunteer,
            'status' => MembershipStatus::Pending,
            'requested_at' => now(),
        ])->save();

        if ($user->hasVerifiedEmail()) {
            $this->notifyAdministrators($organization, $user);
        }

        return $membership;
    }

    /**
     * Tell every active administrator of the organization about the person's request.
     */
    public function notifyAdministrators(Organization $organization, User $requester): void
    {
        Notification::send(User::query()->administratorsOf($organization)->get(), new JoinRequestReceived($organization, $requester));
    }

    /**
     * Explain why the person can't ask to join, or return null when they can (FR-034).
     *
     * People already in the roster, or with an open invitation, are refused. A person
     * who left may ask again. The sign-up page shows this before anyone clicks.
     */
    public function refusalFor(Organization $organization, User $user, ?Membership $membership = null): ?string
    {
        $membership ??= $user->membershipIn($organization);

        return match ($membership?->status) {
            MembershipStatus::Active => "You're already a member of {$organization->name}.",
            MembershipStatus::Pending => "Your request to join {$organization->name} is already waiting for approval.",
            MembershipStatus::Inactive => "Your membership of {$organization->name} is inactive. Ask its administrator to reactivate you.",
            MembershipStatus::Left, null => $organization->invitations()->where('email', $user->email)->exists()
                ? "You've already been invited to join {$organization->name}. Use the link in your invitation email."
                : null,
        };
    }
}
