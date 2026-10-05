<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Open the organization the user works in, or let them choose one (FR-021, FR-022).
     *
     * When nothing opens automatically, the page lists the organizations the user can
     * open, suspended ones they can't, and the invitations, join requests and
     * organization requests they are waiting on.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        $memberships = $user->memberships()
            ->with('organization')
            ->get()
            ->sortBy(fn (Membership $membership): string => $membership->organization->name);

        $activeMemberships = $memberships->filter(fn (Membership $membership): bool => $membership->isActive());
        $openMemberships = $activeMemberships->filter(fn (Membership $membership): bool => $membership->organization->status === OrganizationStatus::Active);

        $organizationToOpen = $user->hasVerifiedEmail() ? $this->organizationToOpen($user, $openMemberships) : null;

        if ($organizationToOpen !== null) {
            return redirect()->route('orgs.show', $organizationToOpen);
        }

        return view('dashboard', [
            'user' => $user,
            'memberships' => $openMemberships,
            'suspendedMemberships' => $activeMemberships->filter(fn (Membership $membership): bool => $membership->organization->status === OrganizationStatus::Suspended),
            'joinRequests' => $memberships->filter(fn (Membership $membership): bool => $membership->status === MembershipStatus::Pending),
            'invitations' => Invitation::query()
                ->where('email', $user->email)
                ->where('expires_at', '>', now())
                ->whereHas('organization', fn (Builder $organization) => $organization->active())
                ->with('organization')
                ->get()
                ->sortBy(fn (Invitation $invitation): string => $invitation->organization->name),
            'organizationRequests' => Organization::query()
                ->whereBelongsTo($user, 'requester')
                ->whereIn('status', [OrganizationStatus::Pending, OrganizationStatus::Rejected])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Pick the organization to open: the one the user last worked in if they can still
     * open it, otherwise their only organization.
     *
     * @param  Collection<int, Membership>  $openMemberships
     */
    private function organizationToOpen(User $user, Collection $openMemberships): ?Organization
    {
        $lastMembership = $openMemberships->first(fn (Membership $membership): bool => $membership->organization_id === $user->last_organization_id);

        if ($lastMembership !== null) {
            return $lastMembership->organization;
        }

        return $openMemberships->count() === 1 ? $openMemberships->first()->organization : null;
    }
}
