<?php

namespace App\Rules;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueOrganizationName implements ValidationRule
{
    /**
     * Create a new rule instance, optionally ignoring the organization being renamed.
     */
    public function __construct(private ?Organization $ignore = null) {}

    /**
     * Fail when a pending, active or suspended organization already has the name, ignoring case (FR-007).
     *
     * The LIKE wildcards are escaped, so the comparison is an exact match. The partial
     * unique index on lower(name) still catches two requests that race past this check.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $isTaken = Organization::query()
            ->whereNot('status', OrganizationStatus::Rejected)
            ->whereLike('name', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value), caseSensitive: false)
            ->when($this->ignore, fn ($query, Organization $ignore) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($isTaken) {
            $fail('That organization name is already taken.');
        }
    }
}
