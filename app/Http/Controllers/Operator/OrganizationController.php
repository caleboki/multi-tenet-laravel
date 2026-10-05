<?php

namespace App\Http\Controllers\Operator;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * List organizations of one status, pending by default (FR-011).
     *
     * Pending requests are listed only once the requester has verified their email (R9).
     * Operators see organization-level details only, never rosters (FR-014).
     */
    public function index(Request $request): View
    {
        $status = $request->enum('status', OrganizationStatus::class) ?? OrganizationStatus::Pending;

        $organizations = Organization::query()
            ->where('status', $status)
            ->when($status === OrganizationStatus::Pending, fn (Builder $query) => $query
                ->whereHas('requester', fn (Builder $requester) => $requester->whereNotNull('email_verified_at'))
                ->orderBy('created_at'))
            ->with('requester')
            ->withCount(['memberships as active_members_count' => fn (Builder $membership) => $membership->active()])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('operator.organizations.index', [
            'organizations' => $organizations,
            'status' => $status,
        ]);
    }

    /**
     * Show an organization's details and the review or suspension actions that apply to it.
     */
    public function show(Organization $organization): View
    {
        $organization->load('requester')->loadCount(['memberships as active_members_count' => fn (Builder $membership) => $membership->active()]);

        return view('operator.organizations.show', [
            'organization' => $organization,
        ]);
    }
}
