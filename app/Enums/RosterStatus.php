<?php

namespace App\Enums;

/**
 * The status shown for a roster entry. "Invited" represents an open invitation
 * and is never stored on a membership.
 */
enum RosterStatus: string
{
    case Invited = 'invited';
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Left = 'left';

    /**
     * Get the roster status that corresponds to a stored membership status.
     */
    public static function fromMembership(MembershipStatus $status): self
    {
        return match ($status) {
            MembershipStatus::Pending => self::Pending,
            MembershipStatus::Active => self::Active,
            MembershipStatus::Inactive => self::Inactive,
            MembershipStatus::Left => self::Left,
        };
    }

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Pending => 'Pending approval',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Left => 'Left',
        };
    }
}
