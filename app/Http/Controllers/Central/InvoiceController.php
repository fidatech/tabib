<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Billing;
use App\Support\Num;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Licence invoices of the clinics. */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::orderBy('name')->get();
        // the clinic chosen in the form: what its next invoice would hold
        $chosen = $tenants->firstWhere('id', (int) $request->query('tenant'));

        return view('central.invoices', [
            'invoices' => Invoice::with('tenant')->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))->orderByDesc('id')->paginate(25)->withQueryString(),
            'tenants' => $tenants, 'chosen' => $chosen, 'onlyNew' => $request->boolean('only_new'),
            'quote' => $chosen ? Billing::quote($chosen, $request->boolean('only_new')) : null,
            'prices' => Billing::prices(),
        ]);
    }

    /**
     * A licence invoice in US dollars. The main branch pays its first-year price once and the
     * yearly hosting after that; so does every further branch, at its own two prices.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', Rule::exists(Tenant::class, 'id')],
            'from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'only_new' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $tenant = Tenant::findOrFail($data['tenant_id']);
        $quote = Billing::quote($tenant, (bool) ($data['only_new'] ?? false));
        if (! $quote['lines']) {
            return back()->with('danger', __('There is nothing to invoice: this clinic has no new branch.'));
        }
        $main = collect($quote['lines'])->first(fn (array $line) => str_starts_with($line['key'], 'main_'));

        $invoice = DB::connection((new Invoice)->getConnectionName())->transaction(fn () => Invoice::create([
            'tenant_id' => $tenant->id, 'from_date' => $data['from_date'], 'to_date' => $data['to_date'], 'note' => $data['note'] ?? null,
            'number' => sprintf('INV-%06d', ((int) Invoice::max('id')) + 1),
            'main_branch_price' => $main['amount'] ?? '0', 'branch_price' => Billing::prices()['branch_renewal'],
            'branches' => $quote['branches'], 'new_branches' => $quote['new_branches'], 'lines' => $quote['lines'], 'total' => $quote['total'],
        ]));
        AdminLog::record('invoiced', $tenant, $invoice->number.' '.$invoice->total);

        return redirect()->route('admin.invoices.index')->with('success', __(':number saved.', ['number' => $invoice->number]));
    }

    /** The four prices: main branch and further branch, first year and renewal. */
    public function prices(Request $request): RedirectResponse
    {
        $data = $request->validate(array_fill_keys(array_keys(Billing::DEFAULTS), ['required', 'numeric', 'min:0', 'max:1000000']));
        foreach ($data as $key => $value) {
            CentralSetting::put('price_'.$key, Num::money($value));
        }
        AdminLog::record('updated', null, 'Prices '.implode(' / ', $data));

        return back()->with('success', __('Saved.'));
    }

    /** Paid: the licence of the clinic runs to the last day of the invoice. */
    public function status(Request $request): RedirectResponse
    {
        $invoice = Invoice::with('tenant')->findOrFail($request->route('id'));
        $data = $request->validate(['status' => ['required', Rule::in(['paid', 'unpaid', 'cancelled'])]]);
        $invoice->update(['status' => $data['status'], 'paid_on' => $data['status'] === 'paid' ? now()->toDateString() : null]);
        $yearly = collect($invoice->lines ?? [['key' => 'main_renewal']])->contains(fn (array $line) => str_starts_with((string) ($line['key'] ?? ''), 'main_'));
        if ($yearly && $data['status'] === 'paid' && (! $invoice->tenant->licence_expires_on || $invoice->tenant->licence_expires_on->lt($invoice->to_date))) {
            $invoice->tenant->update(['licence_expires_on' => $invoice->to_date]);
        }
        \App\Services\Licence::check($invoice->tenant);
        AdminLog::record('invoice '.$data['status'], $invoice->tenant, $invoice->number);

        return back()->with('success', __(':number saved.', ['number' => $invoice->number]));
    }

    /** The invoice as a paper, to print or to save as PDF. */
    public function show(Request $request): View
    {
        $invoice = Invoice::with('tenant')->findOrFail((int) $request->route('id'));

        return view('central.paper', ['kind' => 'invoice', 'invoice' => $invoice, 'payment' => null, 'tenant' => $invoice->tenant, 'balance' => Billing::balance($invoice->tenant)]);
    }
}
