<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Models\Central\Ticket;
use App\Services\Licence;
use App\Support\Num;
use App\Tenancy\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/** The first page of the administration: how the service stands today. */
class DashboardController extends Controller
{
    public function index(Tenancy $tenancy): View
    {
        $today = now()->toDateString();
        $tenants = Tenant::orderBy('name')->get();
        $rules = Licence::rules();

        // licences that need a look: ended first, then the ones ending soonest
        $licences = $tenants->where('status', '!=', Tenant::ARCHIVED)
            ->map(fn (Tenant $t) => ['tenant' => $t] + Licence::state($t, $today))
            ->filter(fn (array $row) => $row['state'] !== 'ok')->sortBy('days')->values();

        // money received, month by month, for the last twelve months
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::parse($today)->startOfMonth()->subMonths($i);
            $months[$month->format('Y-m')] = '0';
        }
        foreach (Payment::whereNull('cancelled_at')->whereDate('paid_on', '>=', array_key_first($months).'-01')->get(['paid_on', 'amount']) as $payment) {
            $key = $payment->paid_on->format('Y-m');
            if (isset($months[$key])) {
                $months[$key] = Num::add($months[$key], $payment->amount);
            }
        }

        $invoiced = Invoice::where('status', '!=', 'cancelled')->sum('total');
        $paid = Payment::whereNull('cancelled_at')->sum('amount');

        return view('central.dashboard', [
            'counts' => [
                'active' => $tenants->where('status', Tenant::ACTIVE)->count(), 'suspended' => $tenants->where('status', Tenant::SUSPENDED)->count(),
                'branches' => $this->branches($tenancy, $tenants), 'tickets' => Ticket::where('status', 'open')->count(),
            ],
            'money' => [
                'year' => Num::money(Payment::whereNull('cancelled_at')->whereYear('paid_on', Carbon::parse($today)->year)->sum('amount')),
                'month' => Num::money(end($months)),
                'due' => Num::money(Num::sub($invoiced, $paid)),
                'unpaid' => Invoice::where('status', 'unpaid')->count(),
            ],
            'months' => array_map(fn (string $v) => (float) $v, $months),
            'licences' => $licences, 'rules' => $rules,
            'payments' => Payment::with('tenant')->whereNull('cancelled_at')->orderByDesc('paid_on')->orderByDesc('id')->limit(6)->get(),
            'unpaid' => Invoice::with('tenant')->where('status', 'unpaid')->orderBy('id')->limit(6)->get(),
            'tickets' => Ticket::with('tenant')->where('status', 'open')->orderBy('last_message_at')->limit(6)->get(),
        ]);
    }

    /**
     * Branches (main and dependent) of all clinics that are not archived. Counting means opening
     * every database, so the figure is kept for an hour.
     */
    private function branches(Tenancy $tenancy, $tenants): int
    {
        $kept = json_decode((string) CentralSetting::read('stat_branches'), true);
        if (is_array($kept) && ($kept['at'] ?? 0) > time() - 3600 && ($kept['tenants'] ?? null) === $tenants->count()) {
            return (int) $kept['n'];
        }
        $n = 0;
        foreach ($tenants->where('status', '!=', Tenant::ARCHIVED) as $tenant) {
            try {
                $n += (int) $tenancy->run($tenant, fn () => Branch::withBooks()->where('is_active', true)->count());
            } catch (Throwable $e) {
                report($e);
            }
        }
        CentralSetting::put('stat_branches', json_encode(['at' => time(), 'n' => $n, 'tenants' => $tenants->count()]));

        return $n;
    }
}
