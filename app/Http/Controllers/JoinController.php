<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Memberships\RequestToJoin;
use App\Http\Requests\StoreJoinRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JoinController extends Controller
{
    /**
     * Show the organization's sign-up page (FR-025).
     *
     * An active member is sent straight to the organization. A signed-out visitor who
     * chooses to sign in is brought back here afterwards (FR-026).
     */
    public function show(Request $request, string $signupToken): View|RedirectResponse
    {
        $organization = $this->organizationFor($signupToken);

        if ($organization !== null && Gate::allows('view', $organization)) {
            return redirect()
                ->route('orgs.show', $organization)
                ->with('status', "You're already a member of {$organization->name}.");
        }

        if ($request->user() === null) {
            redirect()->setIntendedUrl($request->url());
        }

        return view('join.show', [
            'organization' => $organization,
            'signupToken' => $signupToken,
        ]);
    }

    /**
     * Ask to join, creating an account first when the person is signed out (FR-026, FR-045).
     *
     * The return address stored by show() is cleared, so verifying the email later leads
     * to the dashboard rather than back to this sign-up page.
     */
    public function store(StoreJoinRequest $request, string $signupToken, CreateAccount $createAccount, RequestToJoin $requestToJoin): RedirectResponse
    {
        $organization = $this->organizationFor($signupToken);

        if (! $organization?->acceptsSignups()) {
            return redirect()->route('join.show', $signupToken);
        }

        $isNewAccount = $request->user() === null;

        $user = DB::transaction(function () use ($request, $organization, $createAccount, $requestToJoin): User {
            $user = $request->user() ?? $createAccount->handle(
                $request->safe()->only(['name', 'email', 'password', 'phone']),
                adultConfirmed: $request->boolean('adult_confirmation'),
                verified: false,
            );

            $requestToJoin->handle($organization, $user);

            return $user;
        });

        if ($isNewAccount) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('status', $user->hasVerifiedEmail()
            ? "Your request to join {$organization->name} has been sent to its administrators."
            : "Verify your email address to send your request to join {$organization->name}.");
    }

    /**
     * Find the organization whose current sign-up link carries the token.
     */
    private function organizationFor(string $signupToken): ?Organization
    {
        return Organization::query()->where('signup_token', $signupToken)->first();
    }
}
