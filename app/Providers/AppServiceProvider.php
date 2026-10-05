<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
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
            $view->with('currentOrganization', $this->currentOrganization(request()));
        });
    }

    /**
     * Get the organization the header names as current (FR-023).
     *
     * Inside an organization's pages it is the organization in the URL. Elsewhere it
     * is the organization the user last worked in, if they can still enter it. Operator
     * pages also name an organization in the URL, but that is not the user's own.
     */
    private function currentOrganization(Request $request): ?Organization
    {
        $organization = $request->route('organization');

        if ($request->routeIs('orgs.*') && $organization instanceof Organization) {
            return $organization;
        }

        $user = $request->user();
        $lastOrganization = $user?->lastOrganization;

        if ($lastOrganization === null) {
            return null;
        }

        return Gate::forUser($user)->allows('view', $lastOrganization) ? $lastOrganization : null;
    }
}
