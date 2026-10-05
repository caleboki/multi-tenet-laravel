<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Send the user to their organization, or list the organizations they can open.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        $memberships = $user->memberships()
            ->active()
            ->whereHas('organization', fn (Builder $organization) => $organization->active())
            ->with('organization')
            ->get();

        if ($user->hasVerifiedEmail() && $memberships->count() === 1) {
            return redirect()->route('orgs.show', $memberships->first()->organization);
        }

        return view('dashboard', [
            'user' => $user,
            'memberships' => $memberships->sortBy(fn (Membership $membership): string => $membership->organization->name),
        ]);
    }
}
