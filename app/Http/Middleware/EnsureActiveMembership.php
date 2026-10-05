<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveMembership
{
    /**
     * Allow the request into the organization in the URL only when OrganizationPolicy::view allows it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');

        $result = Gate::inspect('view', $organization);

        if ($result->allowed()) {
            return $next($request);
        }

        if ($result->code() === 'organization-suspended') {
            return response()->view('orgs.suspended', ['organization' => $organization], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_NOT_FOUND);
    }
}
