<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the person's organizations and everything waiting for them (FR-021).
     *
     * The page lists the organizations they can open, suspended ones they can't, and
     * the invitations, join requests and organization requests they are waiting on. It
     * never redirects: opening the last organization after sign-in is StartController's job.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $memberships = $user->memberships()
            ->with('organization')
            ->get()
            ->sortBy(fn (Membership $membership): string => $membership->organization->name);

        $activeMemberships = $memberships->filter(fn (Membership $membership): bool => $membership->isActive());

        return view('dashboard', [
            'user' => $user,
            'memberships' => $activeMemberships->filter(fn (Membership $membership): bool => $membership->organization->status === OrganizationStatus::Active),
            'suspendedMemberships' => $activeMemberships->filter(fn (Membership $membership): bool => $membership->organization->status === OrganizationStatus::Suspended),
            'joinRequests' => $memberships->filter(fn (Membership $membership): bool => $membership->status === MembershipStatus::Pending),
            'invitations' => Invitation::query()
                ->openFor($user)
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
}
