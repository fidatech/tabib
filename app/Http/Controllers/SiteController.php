<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Central\SettingsController;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Services\Signups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The public website on the central address: what the system is, what it costs, and the form a
 * clinic asks for it with. A request waits for the administrator, who approves or rejects it in the
 * panel or from the email he is sent; nothing is made before he approves.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', ['prices' => Billing::prices(), 'company' => SettingsController::company()]);
    }

    public function register(): View
    {
        return view('site.register', ['prices' => Billing::prices(), 'company' => SettingsController::company()]);
    }

    public function store(Request $request, Signups $signups): RedirectResponse
    {
        // a box people do not see: only a robot fills it
        if (filled($request->input('website'))) {
            return redirect('/');
        }
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_local' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug'),
                Rule::unique(Signup::class, 'slug')->where('status', 'pending')],
            'licence_no' => ['nullable', 'string', 'max:60'],
            'clinic_type' => ['nullable', Rule::in(array_keys(Signup::TYPES))],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'doctors' => ['nullable', 'integer', 'min:1', 'max:500'],
            'branches' => ['nullable', 'integer', 'min:0', 'max:500'],
            'owner_name' => ['required', 'string', 'max:150'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_phone' => ['required', 'string', 'min:7', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'message' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ], ['slug.regex' => __('The address starts with a letter and has 3 to 40 small letters, digits or dashes.'), 'slug.unique' => __('This address is taken; choose another.'),
            'slug.not_in' => __('This address is taken; choose another.')]);

        // not a flood of clinics from one place
        if (Signup::where('ip', $request->ip())->where('status', 'pending')->where('created_at', '>', now()->subDay())->count() >= 3) {
            return back()->withInput($request->except(['password', 'password_confirmation']))->with('danger', __('You have requests that are still waiting. Please wait for our answer.'));
        }

        $signup = Signup::create(['token' => Str::lower(Str::random(32)), 'password' => Hash::make($data['password']), 'branches' => (int) ($data['branches'] ?? 0),
            'doctors' => max(1, (int) ($data['doctors'] ?? 1)), 'status' => 'pending', 'ip' => $request->ip()] + collect($data)->except(['password', 'branches', 'doctors', 'terms'])->all());
        // nothing is made yet (no database, no address): the administrator is sent the email, and the clinic is opened when he approves
        $signups->notify($signup);

        return redirect()->route('site.status', ['token' => $signup->token]);
    }

    /**
     * The two buttons of the email. The address is signed, so it needs no sign-in; opening it only
     * shows the request and asks to confirm, because mail programs open links by themselves.
     */
    public function decide(Request $request, Signups $signups): View|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $signup = Signup::with('tenant')->findOrFail((int) $request->route('id'));
        $action = (string) $request->route('action');
        $view = fn (?string $done = null) => view('site.decide', ['signup' => $signup->fresh('tenant'), 'action' => $action, 'done' => $done, 'company' => SettingsController::company()]);
        if (! $request->isMethod('POST') || ! $signup->isPending()) {
            return $view();
        }
        if ($action === 'approve') {
            $signups->approve($signup, ['licence_expires_on' => now()->addYear()->toDateString(), 'max_branches' => $signup->branches]);
        } else {
            $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
            $signups->reject($signup, $data['reason'] ?? __('The request was not approved.'));
        }

        return $view($action);
    }

    /** Asked by the registration form as the address is typed: is it free? */
    public function check(Request $request): \Illuminate\Http\JsonResponse
    {
        $slug = strtolower(trim((string) $request->query('address')));
        $free = (bool) preg_match('/^[a-z][a-z0-9-]{2,39}$/', $slug)
            && ! in_array($slug, config('tenancy.reserved_slugs'), true)
            && ! Tenant::where('slug', $slug)->exists()
            && ! Signup::where('slug', $slug)->where('status', 'pending')->exists();

        return response()->json(['address' => $slug, 'free' => $free]);
    }

    /** The applicant follows the request here: waiting, approved (with the address) or rejected (with the reason). */
    public function status(Request $request): View
    {
        $signup = Signup::with('tenant')->where('token', (string) $request->route('token'))->firstOrFail();

        return view('site.status', ['signup' => $signup, 'company' => SettingsController::company()]);
    }

    /** "Sign in": a client types the name of its address and is taken to its own sign-in page. */
    public function open(Request $request): RedirectResponse
    {
        $slug = strtolower(trim((string) $request->query('address')));
        $slug = preg_replace('/\..*$/', '', $slug);   // "kabul.tabib.af" is fine too
        $tenant = $slug !== '' ? Tenant::where('slug', $slug)->first() : null;
        if (! $tenant) {
            return redirect('/#signin')->with('danger', __('No clinic has this address. Check the spelling, or register.'));
        }

        return redirect()->away(($request->isSecure() ? 'https://' : 'http://').$tenant->domain.($request->getPort() && ! in_array($request->getPort(), [80, 443], true) ? ':'.$request->getPort() : '').'/login');
    }
}
