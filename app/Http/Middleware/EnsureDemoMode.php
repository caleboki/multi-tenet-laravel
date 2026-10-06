<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoMode
{
    /**
     * Hide demo-only pages, such as the demo inbox, unless demo mode is on.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('demo.enabled'), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
