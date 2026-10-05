<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class SignupLinkController extends Controller
{
    /**
     * Replace the sign-up link. The old link stops working straight away (FR-029).
     */
    public function store(Organization $organization): RedirectResponse
    {
        $organization->regenerateSignupToken();

        return redirect()
            ->route('orgs.settings.edit', $organization)
            ->with('status', 'Created a new sign-up link. The old link no longer works.');
    }
}
