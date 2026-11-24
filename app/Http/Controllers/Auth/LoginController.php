<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Wrong passwords allowed per email and IP address before a one-minute pause. */
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        audit('signed in', null, $request->user()->name);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The support staff of Fida Technologies arrive from the administration panel with a ticket
     * for one minute and the reason they gave. The reason is written in the audit log of the clinic.
     */
    public function support(Request $request): RedirectResponse
    {
        $tenant = tenant();
        $id = (int) $request->query('a');
        $expires = (int) $request->query('e');
        $reason = (string) $request->query('r');
        $admin = \App\Models\Central\Admin::where('is_active', true)->find($id);
        abort_unless($tenant && $admin && $admin->allows('enter') && $expires >= time() && $expires <= time() + 120
            && hash_equals(\App\Tenancy\Supervisor::seal($tenant->id, $id, $expires, 'support|'.$reason), (string) $request->query('s')), 403);

        Auth::login(\App\Tenancy\Supervisor::supportUser());
        $request->session()->regenerate();
        audit('signed in', null, 'Fida support: '.$admin->name.' — '.$reason);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
