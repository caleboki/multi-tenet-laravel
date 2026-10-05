<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the organization's settings, including its sign-up link (FR-025).
     */
    public function edit(Organization $organization): View
    {
        return view('orgs.settings.edit', [
            'organization' => $organization,
        ]);
    }
}
