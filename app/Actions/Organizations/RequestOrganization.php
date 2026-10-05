<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class RequestOrganization
{
    /**
     * Request a new organization. It stays pending until an operator reviews it (FR-009, FR-010).
     *
     * The slug is made from the name once and never changes (R7). Operators hear about
     * the request only once the requester's email is verified (R9). Until then it waits
     * for the SendPendingRequestNotifications listener.
     */
    public function handle(User $requester, string $name, string $contactEmail): Organization
    {
        $organization = new Organization([
            'name' => $name,
            'contact_email' => Str::lower(trim($contactEmail)),
        ]);

        $organization->forceFill([
            'slug' => $this->uniqueSlugFor($name),
            'status' => OrganizationStatus::Pending,
        ]);
        $organization->requester()->associate($requester);
        $organization->save();

        if ($requester->hasVerifiedEmail()) {
            $this->notifyOperators($organization, $requester);
        }

        return $organization;
    }

    /**
     * Tell every platform operator about the request.
     */
    public function notifyOperators(Organization $organization, User $requester): void
    {
        Notification::send(
            User::query()->where('is_platform_operator', true)->get(),
            new OrganizationRequested($organization, $requester),
        );
    }

    /**
     * Make a URL slug from the name, adding -2, -3 and so on when another organization,
     * including a rejected one, already uses it.
     */
    private function uniqueSlugFor(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 130, ''), '-') ?: 'organization';
        $slug = $base;

        for ($suffix = 2; Organization::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
