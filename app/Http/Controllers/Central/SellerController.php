<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\SellerClaim;
use App\Models\Central\SellerSettlement;
use App\Models\Central\Tenant;
use App\Services\Sellers;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Resellers: the salesmen and marketing agents. Each has his clients, his commission, and an
 * account: what he took from clients, what he earned, what he handed over and what is left.
 */
class SellerController extends Controller
{
    public function index(): View
    {
        $sellers = Seller::orderByDesc('is_active')->orderBy('name')->get()->map(fn (Seller $s) => ['seller' => $s] + Sellers::account($s));

        return view('central.sellers', [
            'sellers' => $sellers,
            'claims' => SellerClaim::with(['seller', 'tenant', 'invoice'])->where('status', 'pending')->orderBy('id')->get(),
            'totals' => ['sales' => Num::sum($sellers->pluck('sales')), 'commission' => Num::sum($sellers->pluck('commission')),
                'owed' => Num::sum($sellers->pluck('balance')->filter(fn ($b) => Num::isPositive($b))),
                'owing' => Num::abs(Num::sum($sellers->pluck('balance')->filter(fn ($b) => Num::isNegative($b))))],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email'))) ?: null]);
        $login = $request->validate([
            'email' => ['nullable', 'required_with:password', Rule::unique(Seller::class, 'email')],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
        ], ['email.required_with' => __('He signs in with his email: write it too.')]);
        $seller = Seller::create($this->validated($request) + array_filter(['password' => $login['password'] ?? null]));
        AdminLog::record('created', null, 'Reseller '.$seller->name.' ('.$seller->commissionLabel().')');

        return redirect()->route('admin.sellers.show', ['id' => $seller->id])->with('success', __(':name saved.', ['name' => $seller->name]));
    }

    public function update(Request $request): RedirectResponse
    {
        $seller = Seller::findOrFail((int) $request->route('id'));
        $seller->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);
        AdminLog::record('updated', null, 'Reseller '.$seller->name.' ('.$seller->commissionLabel().')');

        return back()->with('success', __(':name saved.', ['name' => $seller->name]));
    }

    public function show(Request $request): View
    {
        $seller = Seller::findOrFail((int) $request->route('id'));

        return view('central.seller', [
            'seller' => $seller, 'account' => Sellers::account($seller), 'statement' => Sellers::statement($seller),
            'clients' => Tenant::where('seller_id', $seller->id)->orderBy('name')->get()->map(fn (Tenant $t) => ['tenant' => $t,
                'paid' => Num::money(Payment::where('tenant_id', $t->id)->where('seller_id', $seller->id)->whereNull('cancelled_at')->sum('amount')),
                'commission' => Num::money(Payment::where('tenant_id', $t->id)->where('seller_id', $seller->id)->whereNull('cancelled_at')->sum('commission'))]),
            'free' => Tenant::whereNull('seller_id')->orderBy('name')->get(['id', 'name']),
            'claims' => SellerClaim::with(['tenant', 'invoice'])->where('seller_id', $seller->id)->where('status', 'pending')->orderBy('id')->get(),
        ]);
    }

    /** Money between him and us: he hands over what he took, or we pay him commission. */
    public function settle(Request $request): RedirectResponse
    {
        $seller = Seller::findOrFail((int) $request->route('id'));
        $data = $request->validate([
            'direction' => ['required', Rule::in(array_keys(SellerSettlement::DIRECTIONS))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $settlement = SellerSettlement::create(['seller_id' => $seller->id, 'amount' => Num::money($data['amount']), 'admin_id' => $request->user('admin')->id,
            'number' => sprintf('SLR-%06d', ((int) SellerSettlement::max('id')) + 1)] + $data);
        AdminLog::record('reseller '.($data['direction'] === 'in' ? 'paid us' : 'was paid'), null, $seller->name.' '.$settlement->number.' $'.$settlement->amount);

        return back()->with('success', __(':number saved.', ['number' => $settlement->number]));
    }

    /** A wrong one is cancelled; it no longer counts. */
    public function cancel(Request $request): RedirectResponse
    {
        $settlement = SellerSettlement::with('seller')->where('seller_id', (int) $request->route('id'))->findOrFail((int) $request->route('settlement'));
        if (! $settlement->isCancelled()) {
            $settlement->update(['cancelled_at' => now()]);
            AdminLog::record('cancelled', null, 'Reseller '.$settlement->seller->name.' '.$settlement->number);
        }

        return back()->with('success', __(':number cancelled.', ['number' => $settlement->number]));
    }

    /** His sign-in to the panel of the resellers: the email, and a password (empty keeps the one he has). */
    public function login(Request $request): RedirectResponse
    {
        $seller = Seller::findOrFail((int) $request->route('id'));
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validateWithBag('login', [
            'email' => ['required', 'email', 'max:190', Rule::unique(Seller::class, 'email')->ignore($seller->id)],
            'password' => [$seller->password ? 'nullable' : 'required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
        ]);
        $seller->forceFill(['email' => $data['email']] + (filled($data['password'] ?? null) ? ['password' => $data['password'], 'remember_token' => null] : []))->save();
        AdminLog::record('login changed', null, 'Reseller '.$seller->name.': '.$data['email'].(filled($data['password'] ?? null) ? ', new password' : ''));

        return back()->with('success', __(':name can sign in at :address.', ['name' => $seller->name, 'address' => route('reseller.login')]));
    }

    /** Money a reseller reported: confirmed, it becomes a payment of the client that he took. */
    public function confirm(Request $request): RedirectResponse
    {
        $claim = SellerClaim::with(['seller', 'invoice', 'tenant'])->findOrFail((int) $request->route('claim'));
        $payment = Sellers::confirm($claim, $request->user('admin'));
        AdminLog::record('payment', $claim->tenant, $payment->number.' $'.$payment->amount.' · reported by '.$claim->seller->name.', commission $'.$payment->commission);

        return back()->with('success', __(':number saved.', ['number' => $payment->number]));
    }

    public function refuse(Request $request): RedirectResponse
    {
        $claim = SellerClaim::with('seller')->findOrFail((int) $request->route('claim'));
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        if ($claim->isPending()) {
            $claim->update(['status' => 'rejected', 'reason' => $data['reason'], 'decided_by' => $request->user('admin')->id, 'decided_at' => now()]);
            AdminLog::record('rejected', $claim->tenant, 'Payment reported by '.$claim->seller->name.' $'.$claim->amount);
        }

        return back()->with('success', __('The report was rejected.'));
    }

    /** Give a clinic to this reseller, or take it back (seller_id empty on the page of the client). */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', Rule::exists(Tenant::class, 'id')], 'seller_id' => ['nullable', Rule::exists(Seller::class, 'id')]]);
        $tenant = Tenant::findOrFail($data['tenant_id']);
        $tenant->forceFill(['seller_id' => $data['seller_id'] ?? null])->save();
        AdminLog::record('updated', $tenant, 'Reseller: '.(Seller::find($data['seller_id'] ?? 0)?->name ?? 'none'));

        return back()->with('success', __(':name saved.', ['name' => $tenant->name]));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'],
            'commission_type' => ['required', Rule::in(array_keys(Seller::COMMISSIONS))],
            'commission_value' => ['required', 'numeric', 'min:0', $request->input('commission_type') === 'percent' ? 'max:100' : 'max:1000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ['commission_value' => Num::money($data['commission_value'])] + $data;
    }
}
