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
     */
    public function __invoke(Request $request, Organization $organization): View
    {
        return view('orgs.show', [
            'organization' => $organization,
            'membership' => $request->user()->membershipIn($organization),
        ]);
    }
}
