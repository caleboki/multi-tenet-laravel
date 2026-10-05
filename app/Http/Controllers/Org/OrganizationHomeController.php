<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationHomeController extends Controller
{
    /**
     * Show the organization's home page to one of its active members.
     *
     * Administrators also see how many join requests are waiting (FR-016).
     */
    public function __invoke(Request $request, Organization $organization): View
    {
        $membership = $request->user()->membershipIn($organization);

        return view('orgs.show', [
            'organization' => $organization,
            'membership' => $membership,
            'joinRequestCount' => $membership->isAdministrator()
                ? $organization->memberships()->awaitingApproval()->count()
                : 0,
        ]);
    }
}
