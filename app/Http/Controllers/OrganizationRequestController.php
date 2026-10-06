<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Organizations\RequestOrganization;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationRequestController extends Controller
{
    /**
     * Show the form for requesting a new organization (FR-009).
     *
     * A signed-out visitor who chooses to sign in is brought back here afterwards.
     */
    public function create(Request $request): View
    {
        if ($request->user() === null) {
            redirect()->setIntendedUrl($request->url());
        }

        return view('organization-requests.create');
    }

    /**
     * Request the organization, creating an account first when the person is signed out (FR-009, FR-045).
     *
     * The return address stored by create() is cleared, so verifying the email later leads
     * to the dashboard rather than back to this form.
     */
    public function store(StoreOrganizationRequest $request, CreateAccount $createAccount, RequestOrganization $requestOrganization): RedirectResponse
    {
        $isNewAccount = $request->user() === null;

        $organization = DB::transaction(function () use ($request, $createAccount, $requestOrganization): Organization {
            $requester = $request->user() ?? $createAccount->handle(
                $request->safe()->only(['name', 'email', 'password', 'phone']),
                adultConfirmed: $request->boolean('adult_confirmation'),
                verified: false,
            );

            return $requestOrganization->handle(
                $requester,
                $request->validated('organization_name'),
                $request->validated('contact_email'),
            );
        });

        $user = $organization->requester;

        if ($isNewAccount) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->forget('url.intended');

        return $user->hasVerifiedEmail()
            ? redirect()->route('dashboard')->with('status', "Your request for {$organization->name} has been sent to the platform operators.")
            : redirect()->route('dashboard')->with('info', "Verify your email address to send your request for {$organization->name}.");
    }
}
