<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Organizations\ApproveOrganization;
use App\Actions\Organizations\RejectOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class OrganizationReviewController extends Controller
{
    /**
     * Approve a pending organization (FR-012).
     */
    public function approve(Organization $organization, ApproveOrganization $approveOrganization): RedirectResponse
    {
        $approveOrganization->handle($organization);

        return redirect()
            ->route('operator.organizations.index')
            ->with('status', "Approved {$organization->name}.");
    }

    /**
     * Reject a pending organization with a reason (FR-012).
     */
    public function reject(RejectOrganizationRequest $request, Organization $organization, RejectOrganization $rejectOrganization): RedirectResponse
    {
        $rejectOrganization->handle($organization, $request->validated('reason'));

        return redirect()
            ->route('operator.organizations.index')
            ->with('status', "Rejected {$organization->name}.");
    }
}
