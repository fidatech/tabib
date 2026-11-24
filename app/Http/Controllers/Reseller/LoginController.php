<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Sign-in of the resellers to their panel. */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View|RedirectResponse
    {
        return Auth::guard('seller')->check() ? redirect()->route('reseller.home') : view('reseller.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]);
        $key = Str::transliterate('seller|'.Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! Auth::guard('seller')->attempt(['email' => Str::lower($credentials['email']), 'password' => $credentials['password'], 'is_active' => true])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Auth::guard('seller')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('reseller.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('reseller.login');
    }
}
