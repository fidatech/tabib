<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    /** Choose the language. It is remembered for the user, and for the sign-in page of this browser. */
    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(config('app.locales')))]]);

        $request->session()->put('locale', $data['locale']);
        if ($user = $request->user()) {
            $user->locale = $data['locale'];
            $user->saveQuietly();
        }

        return back();
    }

    /** Switch between the Gregorian and the Solar Hijri calendar. Only what is shown changes. */
    public function calendar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->calendar = $user->calendar === 'shamsi' ? 'gregorian' : 'shamsi';
        $user->saveQuietly();

        return back();
    }

    public function profile(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);
        $user->update(['name' => $data['name'], 'email' => strtolower($data['email'])]);
        audit('updated', $user, 'Own profile');

        return back()->with('success', __('Your profile is saved.'));
    }

    public function password(): View
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);
        $request->user()->update(['password' => $data['password']]);
        audit('updated', $request->user(), 'Own password');

        return back()->with('success', __('Your password is changed.'));
    }

    /** Throw away what the system keeps ready (settings, pages, routes) so it is read fresh. */
    public function refresh(): RedirectResponse
    {
        Gate::authorize('settings.manage');
        Artisan::call('optimize:clear');
        audit('updated', null, 'System refreshed');

        return back()->with('success', __('The system is refreshed.'));
    }

    public function notices(Request $request): View
    {
        return view('notices.index', ['rows' => Notice::where('user_id', $request->user()->id)->orderByDesc('id')->paginate(per_page(25))]);
    }

    /** Open a notification: it is marked as read and the user goes to what it is about. */
    public function openNotice(Request $request): RedirectResponse
    {
        $notice = Notice::where('user_id', $request->user()->id)->findOrFail((int) $request->route('id'));
        $notice->update(['read_at' => $notice->read_at ?? now()]);

        return $notice->url ? redirect($notice->url) : redirect()->route('notices.index');
    }

    public function readNotices(Request $request): RedirectResponse
    {
        Notice::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
