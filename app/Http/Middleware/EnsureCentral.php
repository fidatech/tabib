<?php

namespace App\Http\Middleware;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The system administrator panel cannot be opened on the address of a clinic. */
class EnsureCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app(Tenancy::class)->tenant !== null, 404);

        return $next($request);
    }
}
