<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\SellerClaim;
use App\Models\Central\SellerSettlement;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Services\Sellers;
use App\Services\Signups;
use App\Services\Subscriptions;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The panel of a reseller. He sees only what is his: the clinics he sold, their subscriptions and
 * payments, his commission and his account with us. He asks for new clinics to be opened and
 * reports the money he took; both wait for the administrator.
 */
class PanelController extends Controller
{
    public function home(Request $request): View
    {
        $me = $this->me($request);
        $clients = Tenant::where('seller_id', $me->id)->orderBy('licence_expires_on')->get();

        return view('reseller.dashboard', [
            'me' => $me, 'account' => Sellers::account($me),
            'waiting' => ['activations' => Signup::where('seller_id', $me->id)->where('status', 'pending')->count(), 'claims' => SellerClaim::where('seller_id', $me->id)->where('status', 'pending')->count()],
            // renewals of his clients that come within two months, or are late already
            'renewals' => $clients->filter(fn (Tenant $t) => $t->licence_expires_on && $t->licence_expires_on->lte(now()->addDays(60)))->values(),
            'recent' => Payment::with(['tenant', 'invoice'])->where('seller_id', $me->id)->whereNull('cancelled_at')->orderByDesc('paid_on')->orderByDesc('id')->limit(6)->get(),
        ]);
    }

    /** His clinics, each with its branches: paid or due. */
    public function clients(Request $request): View
    {
        $me = $this->me($request);

        return view('reseller.clients', [
            'rows' => Tenant::where('seller_id', $me->id)->orderBy('name')->get()->map(fn (Tenant $t) => ['tenant' => $t] + Subscriptions::of($t) + ['balance' => Billing::balance($t)]),
        ]);
    }

    /** Activation: he asks for a new clinic to be opened, and follows his requests. */
    public function activations(Request $request): View
    {
        return view('reseller.activations', ['requests' => Signup::with('tenant')->where('seller_id', $this->me($request)->id)->orderByDesc('id')->paginate(20)]);
    }

    public function activate(Request $request, Signups $signups): RedirectResponse
    {
        $me = $this->me($request);
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'name_local' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug'), Rule::unique(Signup::class, 'slug')->where('status', 'pending')],
            'licence_no' => ['nullable', 'string', 'max:60'], 'province' => ['nullable', 'string', 'max:100'], 'branches' => ['nullable', 'integer', 'min:0', 'max:500'],
            'owner_name' => ['required', 'string', 'max:150'], 'owner_email' => ['required', 'email', 'max:190'], 'owner_phone' => ['required', 'string', 'min:7', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)], 'message' => ['nullable', 'string', 'max:1000'],
        ], ['slug.regex' => __('The address starts with a letter and has 3 to 40 small letters, digits or dashes.'), 'slug.unique' => __('This address is taken; choose another.'), 'slug.not_in' => __('This address is taken; choose another.')]);

        $signup = Signup::create(['token' => Str::lower(Str::random(32)), 'password' => Hash::make($data['password']), 'branches' => (int) ($data['branches'] ?? 0),
            'status' => 'pending', 'ip' => $request->ip(), 'seller_id' => $me->id] + collect($data)->except(['password', 'branches'])->all());
        $signups->notify($signup);

        return redirect()->route('reseller.activations')->with('success', __('The request for :name was sent. It opens when the administrator approves it.', ['name' => $signup->name]));
    }

    /** The payments of his clients, and the money he reported and that waits to be confirmed. */
    public function payments(Request $request): View
    {
        $me = $this->me($request);
        $clients = Tenant::where('seller_id', $me->id)->orderBy('name')->get();

        return view('reseller.payments', [
            'payments' => Payment::with(['tenant', 'invoice'])->where('seller_id', $me->id)->orderByDesc('paid_on')->orderByDesc('id')->paginate(20),
            'claims' => SellerClaim::with(['tenant', 'invoice', 'payment'])->where('seller_id', $me->id)->orderByDesc('id')->limit(30)->get(),
            'clients' => $clients,
            // the invoices of his clients that wait to be paid, for the form
            'open' => Invoice::with('tenant')->whereIn('tenant_id', $clients->pluck('id'))->where('status', 'unpaid')->orderBy('id')->get()
                ->map(fn (Invoice $i) => ['invoice' => $i, 'left' => Num::money(Num::sub($i->total, Billing::paidOn($i)))]),
        ]);
    }

    public function report(Request $request): RedirectResponse
    {
        $me = $this->me($request);
        $data = $request->validate([
            'tenant_id' => ['required', Rule::exists(Tenant::class, 'id')->where('seller_id', $me->id)],
            'invoice_id' => ['nullable', Rule::exists(Invoice::class, 'id')->where('tenant_id', $request->input('tenant_id'))->where('status', 'unpaid')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        SellerClaim::create(['seller_id' => $me->id, 'amount' => Num::money($data['amount']), 'status' => 'pending'] + $data);

        return back()->with('success', __('Your report was sent. It counts when the administrator confirms it.'));
    }

    /** A report that still waits can be taken back. */
    public function withdraw(Request $request): RedirectResponse
    {
        SellerClaim::where('seller_id', $this->me($request)->id)->where('status', 'pending')->findOrFail((int) $request->route('id'))->delete();

        return back()->with('success', __('The report was taken back.'));
    }

    /** What he earned, payment by payment and clinic by clinic. */
    public function commissions(Request $request): View
    {
        $me = $this->me($request);
        $lines = Payment::with(['tenant', 'invoice'])->where('seller_id', $me->id)->whereNull('cancelled_at')->orderByDesc('paid_on')->orderByDesc('id')->get();

        return view('reseller.commissions', [
            'me' => $me, 'account' => Sellers::account($me), 'lines' => $lines,
            'byClient' => $lines->groupBy('tenant_id')->map(fn ($set) => ['tenant' => $set->first()->tenant, 'sales' => Num::money(Num::sum($set->pluck('amount'))), 'commission' => Num::money(Num::sum($set->pluck('commission'))), 'count' => $set->count()])
                ->sortByDesc(fn (array $r) => (float) $r['commission'])->values(),
        ]);
    }

    /** His account with us: every line, and the money that moved between us. */
    public function finances(Request $request): View
    {
        $me = $this->me($request);

        return view('reseller.finances', [
            'account' => Sellers::account($me), 'statement' => Sellers::statement($me),
            'settlements' => SellerSettlement::where('seller_id', $me->id)->whereNull('cancelled_at')->orderByDesc('paid_on')->orderByDesc('id')->get(),
        ]);
    }

    public function account(Request $request): View|RedirectResponse
    {
        $me = $this->me($request);
        if ($request->isMethod('GET')) {
            return view('reseller.account', ['me' => $me]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'current_password' => ['nullable', 'required_with:password', 'current_password:seller'], 'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);
        $me->update(array_filter(['name' => $data['name'], 'phone' => $data['phone'] ?? '', 'password' => $data['password'] ?? null], fn ($v) => $v !== null));

        return back()->with('success', __('Saved.'));
    }

    private function me(Request $request): Seller
    {
        return $request->user('seller');
    }
}
