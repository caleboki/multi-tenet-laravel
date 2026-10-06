<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartController extends Controller
{
    /**
     * Open the organization the user works in, right after sign-in (FR-022).
     *
     * Fortify sends people here after signing in and after verifying their email. It
     * opens the organization they last worked in if they can still open it, otherwise
     * their only one. Everyone else goes to the dashboard to choose, which also lists
     * what is waiting for them.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->hasVerifiedEmail() ? $this->organizationToOpen($user) : null;

        return $organization === null
            ? redirect()->route('dashboard')
            : redirect()->route('orgs.show', $organization);
    }

    /**
     * Pick the organization to open: the one the user last worked in if they can still
     * open it, otherwise their only open organization.
     */
    private function organizationToOpen(User $user): ?Organization
    {
        $openMemberships = $user->memberships()
            ->active()
            ->whereHas('organization', fn (Builder $organization) => $organization->active())
            ->with('organization')
            ->get();

        $lastMembership = $openMemberships->first(fn (Membership $membership): bool => $membership->organization_id === $user->last_organization_id);

        if ($lastMembership !== null) {
            return $lastMembership->organization;
        }

        return $openMemberships->count() === 1 ? $openMemberships->first()->organization : null;
    }
}
