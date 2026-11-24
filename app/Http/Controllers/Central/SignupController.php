<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Services\Signups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The requests made on the website: the administrator approves one (the clinic is opened) or rejects it. */
class SignupController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), Signup::STATUSES) ? (string) $request->query('status') : 'pending';

        return view('central.signups', [
            'signups' => Signup::with(['tenant', 'decider', 'seller'])->where('status', $status)->orderByDesc('id')->paginate(25)->withQueryString(),
            'status' => $status, 'counts' => Signup::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'), 'prices' => Billing::prices(),
        ]);
    }

    /** Approve: the clinic that waits is opened for work (or made now, for a request that has none), and the request is closed. */
    public function approve(Request $request, Signups $signups): RedirectResponse
    {
        $signup = Signup::with('tenant')->findOrFail((int) $request->route('id'));
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug', $signup->slug)))]);
        $data = $request->validate([
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug')->ignore($signup->tenant_id)],
            'licence_expires_on' => ['nullable', 'date'],
            'max_branches' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $tenant = $signups->approve($signup, $data, $request->user('admin'));

        return redirect()->route('admin.signups.index', ['status' => 'approved'])->with('success', __(':name is ready at :domain.', ['name' => $tenant->name, 'domain' => $tenant->domain]));
    }

    public function reject(Request $request, Signups $signups): RedirectResponse
    {
        $signup = Signup::with('tenant')->findOrFail((int) $request->route('id'));
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $signups->reject($signup, $data['reason'], $request->user('admin'));

        return back()->with('success', __('The request of :name was rejected.', ['name' => $signup->name]));
    }

    /** Delete requests that are not wanted (spam): one, with its own button, or the ticked ones. Approved ones stay. */
    public function destroy(Request $request, Signups $signups): RedirectResponse
    {
        $data = $request->validate(['only' => ['nullable', 'integer'], 'ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['integer']]);
        $ids = isset($data['only']) ? [(int) $data['only']] : array_map('intval', $data['ids'] ?? []);
        if (! $ids) {
            return back()->with('danger', __('Tick the requests to delete first.'));
        }

        $gone = $signups->delete($ids);

        return back()->with($gone ? 'success' : 'danger', $gone === 1 ? __('The request was deleted.') : __(':count requests were deleted.', ['count' => $gone]));
    }
}
