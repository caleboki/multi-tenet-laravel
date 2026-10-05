<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Enums\OrganizationStatus;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    /**
     * Show the invitation, or explain why it can't be accepted here.
     *
     * A signed-out invitee who already has an account is asked to sign in, and is
     * brought back here afterwards (FR-031).
     */
    public function show(Request $request, string $token): View
    {
        $invitation = $this->openInvitation($token);
        $user = $request->user();
        $hasAccount = $invitation !== null && User::query()->where('email', $invitation->email)->exists();

        if ($user === null && $hasAccount) {
            redirect()->setIntendedUrl($request->url());
        }

        return view('invitations.show', [
            'token' => $token,
            'invitation' => $invitation,
            'hasAccount' => $hasAccount,
            'isForSignedInUser' => $user !== null && $user->email === $invitation?->email,
        ]);
    }

    /**
     * Accept the invitation and open the organization (FR-031, FR-032).
     *
     * A signed-in invitee keeps their account. A signed-out person with a new email
     * address creates an account and is signed in.
     */
    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->openInvitation($token);
        $user = $request->user();

        if ($invitation === null || ($user !== null && $user->email !== $invitation->email)) {
            return redirect()->route('invitations.show', $token);
        }

        if ($user !== null) {
            $acceptInvitation->acceptAs($invitation, $user);
        } else {
            $membership = $acceptInvitation->handle(
                $invitation,
                $request->safe()->only(['name', 'password', 'phone']),
                adultConfirmed: $request->boolean('adult_confirmation'),
            );

            Auth::login($membership->user);
            $request->session()->regenerate();
        }

        $request->session()->forget('url.intended');

        return redirect()->route('orgs.show', $invitation->organization);
    }

    /**
     * Accept an invitation from the dashboard as the signed-in person it was sent to.
     *
     * A verified email address proves the person owns the invited address, as opening the
     * emailed link does. Only token hashes are stored, so the dashboard can't link to the
     * email's URL. The `can:accept,invitation` middleware refuses anyone else with a 404.
     */
    public function acceptFromDashboard(Request $request, Invitation $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation->load('organization');

        if ($invitation->isExpired()) {
            return redirect()
                ->route('dashboard')
                ->with('status', "That invitation has expired. Ask the organization's administrator to send a new one.");
        }

        if ($invitation->organization->status !== OrganizationStatus::Active) {
            return redirect()
                ->route('dashboard')
                ->with('status', "That organization isn't open right now, so the invitation can't be accepted.");
        }

        $acceptInvitation->acceptAs($invitation, $request->user());

        return redirect()
            ->route('orgs.show', $invitation->organization)
            ->with('status', "You've joined {$invitation->organization->name}.");
    }

    /**
     * Find the invitation the token belongs to, or null when the link is unknown, already used or expired.
     */
    private function openInvitation(string $token): ?Invitation
    {
        $invitation = Invitation::findByToken($token);

        if ($invitation === null || $invitation->isExpired()) {
            return null;
        }

        return $invitation->load('organization');
    }
}
