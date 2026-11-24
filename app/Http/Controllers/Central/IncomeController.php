<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Services\Licence;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Income of the service: month by month, clinic by clinic, and the renewals that are coming. */
class IncomeController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $payments = Payment::whereNull('cancelled_at')->whereYear('paid_on', $year)->get(['tenant_id', 'paid_on', 'amount', 'method']);
        $invoices = Invoice::where('status', '!=', 'cancelled')->whereYear('from_date', $year)->get(['tenant_id', 'total', 'from_date']);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $months[$key] = [
                'invoiced' => Num::money(Num::sum($invoices->filter(fn ($i) => $i->from_date->format('Y-m') === $key)->pluck('total'))),
                'received' => Num::money(Num::sum($payments->filter(fn ($p) => $p->paid_on->format('Y-m') === $key)->pluck('amount'))),
            ];
        }

        $tenants = Tenant::orderBy('name')->get();
        $allInvoiced = Invoice::where('status', '!=', 'cancelled')->selectRaw('tenant_id, sum(total) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id');
        $allPaid = Payment::whereNull('cancelled_at')->selectRaw('tenant_id, sum(amount) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id');
        $clients = $tenants->map(fn (Tenant $t) => [
            'tenant' => $t,
            'invoiced' => Num::money(Num::sum($invoices->where('tenant_id', $t->id)->pluck('total'))),
            'received' => Num::money(Num::sum($payments->where('tenant_id', $t->id)->pluck('amount'))),
            'due' => Num::money(Num::sub($allInvoiced[$t->id] ?? 0, $allPaid[$t->id] ?? 0)),
        ])->filter(fn (array $row) => ! Num::isZero($row['invoiced']) || ! Num::isZero($row['received']) || ! Num::isZero($row['due']))->values();

        $renewals = $tenants->where('status', '!=', Tenant::ARCHIVED)->filter(fn (Tenant $t) => $t->licence_expires_on !== null)
            ->map(fn (Tenant $t) => ['tenant' => $t] + Licence::state($t))->filter(fn (array $row) => $row['days'] <= 90)->sortBy('days')->values();

        return view('central.income', [
            'year' => $year, 'years' => range((int) now()->year, max(2026, (int) now()->year - 5)),
            'months' => $months, 'clients' => $clients, 'renewals' => $renewals,
            'methods' => collect(Payment::METHODS)->map(fn ($label, $key) => Num::money(Num::sum($payments->where('method', $key)->pluck('amount'))))->all(),
            'totals' => ['invoiced' => Num::money(Num::sum(array_column($months, 'invoiced'))), 'received' => Num::money(Num::sum(array_column($months, 'received'))),
                'due' => Num::money(Num::sum($clients->pluck('due')))],
        ]);
    }
}
