<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Send the user to their organization, or list the organizations they can open
     * and the join and organization requests they are waiting on.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        $memberships = $user->memberships()
            ->with('organization')
            ->get()
            ->sortBy(fn (Membership $membership): string => $membership->organization->name);

        $activeMemberships = $memberships->filter(fn (Membership $membership): bool => $membership->isActive()
            && $membership->organization->status === OrganizationStatus::Active);

        if ($user->hasVerifiedEmail() && $activeMemberships->count() === 1) {
            return redirect()->route('orgs.show', $activeMemberships->first()->organization);
        }

        return view('dashboard', [
            'user' => $user,
            'memberships' => $activeMemberships,
            'joinRequests' => $memberships->filter(fn (Membership $membership): bool => $membership->status === MembershipStatus::Pending),
            'organizationRequests' => Organization::query()
                ->whereBelongsTo($user, 'requester')
                ->whereIn('status', [OrganizationStatus::Pending, OrganizationStatus::Rejected])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
