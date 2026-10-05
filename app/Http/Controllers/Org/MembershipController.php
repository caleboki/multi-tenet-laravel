<?php

namespace App\Http\Controllers\Org;

use App\Actions\Memberships\ChangeMembership;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    /**
     * Leave the organization (FR-024). The last active administrator is refused (FR-018).
     */
    public function destroy(Request $request, Organization $organization, ChangeMembership $changeMembership): RedirectResponse
    {
        $changeMembership->handle($request->user()->membershipIn($organization), role: null, status: MembershipStatus::Left);

        return redirect()
            ->route('dashboard')
            ->with('status', "You've left {$organization->name}.");
    }
}
