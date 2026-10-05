<?php

namespace App\Http\Controllers\Org;

use App\Actions\Memberships\ChangeMembership;
use App\Actions\Roster\BuildRosterQuery;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\RosterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RosterFilterRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MemberController extends Controller
{
    /**
     * Show the organization's roster of members and open invitations, 25 per page (FR-035, FR-036, FR-039).
     */
    public function index(RosterFilterRequest $request, Organization $organization, BuildRosterQuery $buildRosterQuery): View
    {
        $role = $request->enum('role', MembershipRole::class);
        $status = $request->enum('status', RosterStatus::class);

        $entries = $buildRosterQuery->handle($organization, $request->validated('q'), $role, $status)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $entry): object => (object) [
                ...(array) $entry,
                'role' => MembershipRole::from($entry->role),
                'status' => RosterStatus::from($entry->status),
                'joined_at' => $entry->joined_at === null ? null : Carbon::parse($entry->joined_at),
            ]);

        return view('orgs.members.index', [
            'organization' => $organization,
            'entries' => $entries,
            'search' => $request->validated('q'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    /**
     * Show a member's details. Personal details belong to the member, so they are read-only (FR-037).
     */
    public function show(Organization $organization, Membership $membership): View
    {
        return view('orgs.members.show', [
            'organization' => $organization,
            'membership' => $membership->load('user'),
        ]);
    }

    /**
     * Promote, demote, deactivate or reactivate a member (FR-017, FR-038).
     */
    public function update(UpdateMemberRequest $request, Organization $organization, Membership $membership, ChangeMembership $changeMembership): RedirectResponse
    {
        $changeMembership->handle(
            $membership,
            $request->enum('role', MembershipRole::class),
            $request->enum('status', MembershipStatus::class),
        );

        return redirect()
            ->route('orgs.members.show', [$organization, $membership])
            ->with('status', "Saved the changes to {$membership->user->name}.");
    }
}
