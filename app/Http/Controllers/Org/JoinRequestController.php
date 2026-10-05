<?php

namespace App\Http\Controllers\Org;

use App\Actions\Memberships\ApproveJoinRequest;
use App\Actions\Memberships\DeclineJoinRequest;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JoinRequestController extends Controller
{
    /**
     * List the join requests waiting for review, oldest first (FR-027, FR-039).
     */
    public function index(Organization $organization): View
    {
        return view('orgs.join-requests.index', [
            'organization' => $organization,
            'joinRequests' => $organization->memberships()
                ->awaitingApproval()
                ->with('user')
                ->orderBy('requested_at')
                ->orderBy('id')
                ->paginate(25),
        ]);
    }

    /**
     * Approve a join request (FR-028).
     */
    public function approve(Organization $organization, Membership $membership, ApproveJoinRequest $approveJoinRequest): RedirectResponse
    {
        $approveJoinRequest->handle($organization, $membership);

        return redirect()
            ->back(fallback: route('orgs.join-requests.index', $organization))
            ->with('status', "Approved {$membership->user->name}'s request to join.");
    }

    /**
     * Decline a join request (FR-028).
     */
    public function destroy(Organization $organization, Membership $membership, DeclineJoinRequest $declineJoinRequest): RedirectResponse
    {
        $declineJoinRequest->handle($organization, $membership);

        return redirect()
            ->back(fallback: route('orgs.join-requests.index', $organization))
            ->with('status', "Declined {$membership->user->name}'s request to join.");
    }
}
