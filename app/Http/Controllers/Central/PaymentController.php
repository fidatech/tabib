<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Money received from the clinics: recorded against an invoice or on account, with a receipt. */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::orderBy('name')->get();
        $chosen = $tenants->firstWhere('id', (int) $request->query('tenant'));

        return view('central.payments', [
            'payments' => Payment::with(['tenant', 'invoice', 'admin', 'seller'])->when($chosen, fn ($q) => $q->where('tenant_id', $chosen->id))->orderByDesc('paid_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'tenants' => $tenants, 'chosen' => $chosen, 'sellers' => \App\Models\Central\Seller::where('is_active', true)->orderBy('name')->get(),
            'open' => $chosen ? Invoice::where('tenant_id', $chosen->id)->where('status', 'unpaid')->orderBy('id')->get()->map(fn (Invoice $i) => ['invoice' => $i, 'left' => Num::money(Num::sub($i->total, Billing::paidOn($i)))]) : collect(),
            'balance' => $chosen ? Billing::balance($chosen) : null,
            'invoice' => (int) $request->query('invoice'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', Rule::exists(Tenant::class, 'id')],
            'invoice_id' => ['nullable', Rule::exists(Invoice::class, 'id')->where('tenant_id', $request->input('tenant_id'))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            // the reseller of this sale: who earns the commission, and whether he took the money himself
            'seller_id' => ['nullable', Rule::exists(\App\Models\Central\Seller::class, 'id')],
            'collected_by_seller' => ['nullable', 'boolean'],
            'commission' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
        ]);
        $seller = \App\Models\Central\Seller::find($data['seller_id'] ?? 0);
        $data['seller_id'] = $seller?->id;
        $data['collected_by_seller'] = $seller && ($data['collected_by_seller'] ?? false);
        // empty: worked out from his commission; a figure typed here wins (an agreed exception)
        $data['commission'] = ! $seller ? '0.00' : (filled($data['commission'] ?? null) ? Num::money($data['commission'])
            : \App\Services\Sellers::commissionFor($seller, Num::money($data['amount']), Invoice::find($data['invoice_id'] ?? 0)));

        $payment = DB::connection((new Payment)->getConnectionName())->transaction(function () use ($data, $request) {
            $payment = Payment::create(['amount' => Num::money($data['amount']), 'admin_id' => $request->user('admin')->id,
                'number' => sprintf('RCP-%06d', ((int) Payment::max('id')) + 1)] + $data);
            if ($payment->invoice) {
                Billing::settle($payment->invoice);
            }

            return $payment;
        });
        AdminLog::record('payment', $payment->tenant, $payment->number.' $'.$payment->amount.' '.$payment->method.($seller ? ' · '.$seller->name.' commission $'.$payment->commission.($payment->collected_by_seller ? ' (he took the money)' : '') : ''));

        return redirect()->route('admin.payments.index', ['tenant' => $payment->tenant_id])->with('success', __(':number saved.', ['number' => $payment->number]));
    }

    /** A wrong payment is cancelled: it stays on the list, and its invoice is open again. */
    public function destroy(Request $request): RedirectResponse
    {
        $payment = Payment::with('invoice.tenant')->findOrFail((int) $request->route('id'));
        if (! $payment->isCancelled()) {
            $payment->update(['cancelled_at' => now()]);
            if ($payment->invoice) {
                Billing::settle($payment->invoice);
            }
            AdminLog::record('payment cancelled', $payment->tenant, $payment->number);
        }

        return back()->with('success', __(':number cancelled.', ['number' => $payment->number]));
    }

    public function receipt(Request $request): View
    {
        $payment = Payment::with(['tenant', 'invoice', 'admin'])->findOrFail((int) $request->route('id'));

        return view('central.paper', ['kind' => 'receipt', 'payment' => $payment, 'invoice' => $payment->invoice, 'tenant' => $payment->tenant, 'balance' => Billing::balance($payment->tenant)]);
    }
}
