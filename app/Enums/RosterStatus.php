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

    /**
     * Get the tone of the status badge that shows this status.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'warning',
            self::Inactive => 'danger',
            self::Invited, self::Left => 'neutral',
        };
    }

    /**
     * Get every status as an option for a filter list, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
