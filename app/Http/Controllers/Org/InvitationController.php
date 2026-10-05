<?php

namespace App\Http\Controllers\Org;

use App\Actions\Invitations\IssueInvitation;
use App\Actions\Invitations\ResendInvitation;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Show the form for inviting a person by name and email.
     */
    public function create(Organization $organization): View
    {
        return view('orgs.invitations.create', [
            'organization' => $organization,
        ]);
    }

    /**
     * Invite a person and email them a one-time link (FR-030, FR-031).
     */
    public function store(StoreInvitationRequest $request, Organization $organization, IssueInvitation $issueInvitation): RedirectResponse
    {
        $invitation = $issueInvitation->handle(
            $organization,
            $request->user(),
            $request->validated('name'),
            $request->validated('email'),
            $request->enum('role', MembershipRole::class),
        );

        return redirect()
            ->route('orgs.members.index', $organization)
            ->with('status', "Invitation sent to {$invitation->email}.");
    }

    /**
     * Email the invitation again with a new link. The old link stops working (FR-033).
     */
    public function resend(Request $request, Organization $organization, Invitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        $resendInvitation->handle($organization, $invitation, $request->user());

        return redirect()
            ->back(fallback: route('orgs.members.index', $organization))
            ->with('status', "Invitation resent to {$invitation->email}.");
    }

    /**
     * Cancel an unused invitation (FR-033).
     */
    public function destroy(Organization $organization, Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return redirect()
            ->back(fallback: route('orgs.members.index', $organization))
            ->with('status', "Invitation to {$invitation->email} cancelled.");
    }
}
