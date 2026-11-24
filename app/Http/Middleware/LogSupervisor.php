<?php

namespace App\Http\Middleware;

use App\Tenancy\Supervisor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every page the support staff of Fida Technologies open inside a clinic is written in its audit log. */
class LogSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && tenant() && $user->email === Supervisor::SUPPORT_EMAIL) {
            audit('viewed', null, 'Fida support: /'.ltrim($request->path(), '/'));
        }

        return $next($request);
    }
}
