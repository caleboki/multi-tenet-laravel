<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Organizations\ReinstateOrganization;
use App\Actions\Organizations\SuspendOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class OrganizationSuspensionController extends Controller
{
    /**
     * Suspend an active organization (FR-013).
     */
    public function suspend(Organization $organization, SuspendOrganization $suspendOrganization): RedirectResponse
    {
        $suspendOrganization->handle($organization);

        return redirect()
            ->route('operator.organizations.show', $organization)
            ->with('status', "Suspended {$organization->name}.");
    }

    /**
     * Reinstate a suspended organization (FR-013).
     */
    public function reinstate(Organization $organization, ReinstateOrganization $reinstateOrganization): RedirectResponse
    {
        $reinstateOrganization->handle($organization);

        return redirect()
            ->route('operator.organizations.show', $organization)
            ->with('status', "Reinstated {$organization->name}.");
    }
}
