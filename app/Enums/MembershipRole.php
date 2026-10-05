<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Administrator = 'administrator';
    case Volunteer = 'volunteer';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Volunteer => 'Volunteer',
        };
    }
}
