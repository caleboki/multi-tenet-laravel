<?php

namespace App\Providers;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8));

        RateLimiter::for('public-forms', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('roster-import', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));

        Gate::define('operate-platform', fn (User $user): bool => $user->is_platform_operator);

        View::composer('layouts.app', function (ViewContract $view): void {
            $request = request();
            $openMemberships = $this->openMemberships($request->user());

            $view->with([
                'openMemberships' => $openMemberships,
                'currentOrganization' => $this->currentOrganization($request, $openMemberships),
            ]);
        });
    }

    /**
     * Get the user's active memberships in active organizations, for the header's
     * organization switcher (FR-021).
     *
     * @return Collection<int, Membership>
     */
    private function openMemberships(?User $user): Collection
    {
        if ($user === null) {
            return new Collection;
        }

        return $user->memberships()
            ->active()
            ->whereHas('organization', fn (Builder $organization) => $organization->active())
            ->with('organization')
            ->get()
            ->sortBy(fn (Membership $membership): string => $membership->organization->name)
            ->values();
    }

    /**
     * Get the organization the header names as current (FR-023).
     *
     * Inside an organization's pages it is the organization in the URL. Elsewhere it
     * is the organization the user last worked in, if they can still open it. Operator
     * pages also name an organization in the URL, but that is not the user's own.
     *
     * @param  Collection<int, Membership>  $openMemberships
     */
    private function currentOrganization(Request $request, Collection $openMemberships): ?Organization
    {
        $organization = $request->route('organization');

        if ($request->routeIs('orgs.*') && $organization instanceof Organization) {
            return $organization;
        }

        return $openMemberships
            ->first(fn (Membership $membership): bool => $membership->organization_id === $request->user()?->last_organization_id)
            ?->organization;
    }
}
