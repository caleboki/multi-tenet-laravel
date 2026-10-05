<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    /**
     * Show the invitation, or explain that its link can no longer be used.
     */
    public function show(string $token): View
    {
        $invitation = $this->openInvitation($token);

        return view('invitations.show', [
            'token' => $token,
            'invitation' => $invitation,
            'hasAccount' => $invitation !== null && User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    /**
     * Create the invitee's account, make them an active member, and sign them in (FR-031, FR-032).
     */
    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->openInvitation($token);

        if ($invitation === null) {
            return redirect()->route('invitations.show', $token);
        }

        $membership = $acceptInvitation->handle(
            $invitation,
            $request->safe()->only(['name', 'password', 'phone']),
            adultConfirmed: $request->boolean('adult_confirmation'),
        );

        Auth::login($membership->user);
        $request->session()->regenerate();

        return redirect()->route('orgs.show', $invitation->organization);
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
