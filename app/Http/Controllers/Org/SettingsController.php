<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationSettingsRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Update the organization's name, contact email and self sign-up switch (FR-008, FR-029).
     * The URL slug never changes (R7).
     */
    public function update(UpdateOrganizationSettingsRequest $request, Organization $organization): RedirectResponse
    {
        $organization->forceFill($request->validated())->save();

        return redirect()
            ->route('orgs.settings.edit', $organization)
            ->with('status', 'Settings saved.');
    }
}
