<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A clinic that registered on the website and is not approved yet: its owner signs in and sees the
 * system, but every page leads to the subscription page until the administrator approves it.
 */
class LockUntilApproved
{
    /** What stays open: signing in and out, the subscription and its invoices, support, language and look. */
    private const OPEN = ['login', 'login.store', 'logout', 'service.*', 'preferences.*', 'settings.logo', 'notices.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(Tenancy::class)->tenant;
        if ($tenant && $tenant->isPending() && $request->user() && ! $request->routeIs(...self::OPEN)) {
            return $request->expectsJson()
                ? response()->json(['message' => __('Your registration is waiting for approval.')], 423)
                : redirect()->route('service.licence');
        }

        return $next($request);
    }
}
