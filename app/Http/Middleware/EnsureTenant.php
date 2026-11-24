<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages of a clinic cannot be opened on the address of the system administrator panel. */
class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(Tenancy::class)->tenant) {
            // the bare central address is the public website of the system
            if ($request->path() === '/' && $request->isMethod('GET')) {
                return response(app(\App\Http\Controllers\SiteController::class)->home());
            }
            abort(404);
        }

        return $next($request);
    }
}
