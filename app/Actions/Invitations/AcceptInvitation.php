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
     * the address. The membership is active straight away with the invitation's role,
     * and the invitation is deleted so its link can't be used again. The caller must
     * check that the invitation has not expired.
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

            $membership = $user->memberships()->make();
            $membership->forceFill([
                'organization_id' => $invitation->organization_id,
                'role' => $invitation->role,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ])->save();

            $invitation->delete();

            return $membership->setRelation('user', $user);
        });
    }
}
