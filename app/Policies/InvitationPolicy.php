<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvitationPolicy
{
    /**
     * Determine whether the user may accept the invitation from their dashboard, without
     * its emailed link.
     *
     * Only the person it was sent to may. Anyone else is told it doesn't exist, so
     * invitations to other addresses are never revealed.
     */
    public function accept(User $user, Invitation $invitation): Response
    {
        return $user->email === $invitation->email
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
