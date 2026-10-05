<?php

namespace App\Actions\Invitations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueInvitation
{
    /**
     * Invite a person to the organization, unless they are already in its roster (FR-034).
     *
     * A membership that is pending, active or inactive blocks the invitation, and so
     * does an open invitation. A person who left can be invited again. Memberships in
     * other organizations are never considered (FR-006).
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $inviter, string $name, string $email, MembershipRole $role): Invitation
    {
        $email = Str::lower(trim($email));

        $isInRoster = $organization->memberships()
            ->whereIn('status', [MembershipStatus::Pending, MembershipStatus::Active, MembershipStatus::Inactive])
            ->whereHas('user', fn (Builder $user) => $user->where('email', $email))
            ->exists();

        if ($isInRoster) {
            throw ValidationException::withMessages([
                'email' => 'This email address is already in your roster.',
            ]);
        }

        if ($organization->invitations()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An invitation has already been sent to this email address. You can resend it from the roster.',
            ]);
        }

        return $this->issue($organization, $inviter, $name, $email, $role);
    }

    /**
     * Create the invitation and email its link, without applying the duplicate rule.
     *
     * The caller must pass a lowercased email that it has already checked against the
     * roster. Roster imports use this to check every row with a single query.
     */
    public function issue(Organization $organization, User $inviter, string $name, string $email, MembershipRole $role): Invitation
    {
        $invitation = $organization->invitations()->make([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);

        $this->sendLink($organization, $invitation, $inviter);

        return $invitation;
    }

    /**
     * Save the invitation with a new 7-day link from the given administrator, and email
     * the link once any surrounding transaction commits. Any previous link stops working.
     */
    public function sendLink(Organization $organization, Invitation $invitation, User $inviter): void
    {
        $invitation->invitedBy()->associate($inviter);
        $plainToken = $invitation->regenerateToken();

        Notification::route('mail', $invitation->email)->notify(new InvitationNotification(
            organizationName: $organization->name,
            inviterName: $inviter->name,
            token: $plainToken,
            expiresAt: $invitation->expires_at,
            existingAccount: User::query()->where('email', $invitation->email)->exists(),
        ));
    }
}
