<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationHomeController extends Controller
{
    /**
     * Show the organization's home page to one of its active members. Administrators
     * reach the management pages through the organization menu in the layout (FR-016).
     */
    public function __invoke(Request $request, Organization $organization): View
    {
        return view('orgs.show', [
            'organization' => $organization,
            'membership' => $request->user()->membershipIn($organization),
        ]);
    }
}
