<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the clinic from the address of the request. A central host opens the system
 * administrator panel; any other host must be the domain of a clinic.
 */
class InitializeTenancy
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            if ($this->tenancy->tenant) {
                $this->tenancy->end();
            }

            return $next($request);
        }

        // the list of clinics is in the central database, whatever was open before
        if ($this->tenancy->tenant) {
            $this->tenancy->end();
        }
        $tenant = Tenant::where('domain', $host)->first();
        abort_if(! $tenant, 404);

        // a clinic that waits for approval opens too: LockUntilApproved keeps it on its subscription page
        if (! $tenant->isActive() && ! $tenant->isPending()) {
            return response()->view('errors.suspended', ['name' => $tenant->name, 'licence' => $tenant->locked_reason === 'licence', 'rejected' => $tenant->locked_reason === 'rejected', 'company' => \App\Http\Controllers\Central\SettingsController::company()], 503);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
