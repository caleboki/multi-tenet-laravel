<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Left = 'left';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return RosterStatus::fromMembership($this)->label();
    }
}
