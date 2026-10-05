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

    /**
     * Get every role as an option for a select list, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
