<?php

namespace App\Actions\Invitations;

use App\Actions\Accounts\CreateAccount;
use App\Enums\MembershipStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(private CreateAccount $createAccount) {}

    /**
     * Accept an invitation sent to an email address that has no account yet (FR-031, FR-032).
     *
     * The account is created already verified, because opening the emailed link proves
     * the address. The caller must check that the invitation has not expired.
     *
     * @param  array{name: string, password: string, phone?: ?string}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(Invitation $invitation, array $attributes, bool $adultConfirmed): Membership
    {
        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "You already have an account with {$invitation->email}. Sign in to accept this invitation.",
            ]);
        }

        return DB::transaction(function () use ($invitation, $attributes, $adultConfirmed): Membership {
            $user = $this->createAccount->handle(
                [...$attributes, 'email' => $invitation->email],
                adultConfirmed: $adultConfirmed,
                verified: true,
            );

            return $this->activateMembership($invitation, $user);
        });
    }

    /**
     * Accept an invitation as the signed-in person it was sent to, keeping their existing
     * account and password (FR-031; US4 scenario 3). The caller must check that the
     * invitation has not expired.
     *
     * @throws ValidationException when the user's email is not the invited address.
     */
    public function acceptAs(Invitation $invitation, User $user): Membership
    {
        if ($user->email !== $invitation->email) {
            throw ValidationException::withMessages([
                'email' => 'This invitation is for another email address.',
            ]);
        }

        return DB::transaction(fn (): Membership => $this->activateMembership($invitation, $user));
    }

    /**
     * Make the person an active member with the invitation's role, without further approval
     * (FR-032), and delete the invitation so its link can't be used again.
     *
     * A person who left the organization gets their membership back, so each person
     * keeps one membership per organization.
     */
    private function activateMembership(Invitation $invitation, User $user): Membership
    {
        $membership = $user->memberships()->where('organization_id', $invitation->organization_id)->first()
            ?? $user->memberships()->make();

        $membership->forceFill([
            'organization_id' => $invitation->organization_id,
            'role' => $invitation->role,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ])->save();

        $invitation->delete();

        return $membership->setRelation('user', $user);
    }
}
