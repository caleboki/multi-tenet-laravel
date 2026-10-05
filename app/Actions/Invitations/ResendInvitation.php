<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;

class ResendInvitation
{
    public function __construct(private IssueInvitation $issueInvitation) {}

    /**
     * Email a new link that expires in 7 days. The previous link stops working, and the
     * administrator who resent it becomes the inviter (FR-033).
     */
    public function handle(Organization $organization, Invitation $invitation, User $inviter): Invitation
    {
        $this->issueInvitation->sendLink($organization, $invitation, $inviter);

        return $invitation;
    }
}
