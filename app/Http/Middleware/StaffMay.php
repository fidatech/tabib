<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A page of the administration panel that only some of the staff may open: ->middleware('staff:billing.manage'). */
class StaffMay
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless($request->user('admin')?->allows($ability), 403);

        return $next($request);
    }
}
