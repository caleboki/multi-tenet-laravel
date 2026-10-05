<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMembership
{
    /**
     * Allow the request into the organization in the URL only when OrganizationPolicy::view allows it.
     *
     * An allowed request also records the organization as the one the user last worked in,
     * so the dashboard can reopen it after the next sign-in (FR-022).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');

        $result = Gate::inspect('view', $organization);

        if ($result->allowed()) {
            $this->rememberAsLastOrganization($request->user(), $organization);

            return $next($request);
        }

        if ($result->code() === 'organization-suspended') {
            return response()->view('orgs.suspended', ['organization' => $organization], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_NOT_FOUND);
    }

    /**
     * Save the organization as the user's last one, skipping the write when it hasn't changed.
     */
    private function rememberAsLastOrganization(User $user, Organization $organization): void
    {
        if ($user->last_organization_id !== $organization->id) {
            $user->forceFill(['last_organization_id' => $organization->id])->save();
        }
    }
}
