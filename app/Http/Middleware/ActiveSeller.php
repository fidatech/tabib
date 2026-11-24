<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A reseller who was switched off is signed out at his next click. */
class ActiveSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('seller')?->canSignIn()) {
            Auth::guard('seller')->logout();

            return redirect()->route('reseller.login')->withErrors(['email' => __('This account is not active.')]);
        }

        return $next($request);
    }
}
