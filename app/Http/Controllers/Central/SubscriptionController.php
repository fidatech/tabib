<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Subscriptions;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/** Who paid and who is due, clinic by clinic and branch by branch; and the renewals that come every year. */
class SubscriptionController extends Controller
{
    /** Every clinic with its main branch and its further branches: paid, due, renewal due, or never invoiced. */
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['due', 'paid'], true) ? $request->query('show') : 'all';
        $search = trim((string) $request->query('q'));
        $all = $this->tenants($search)->map(fn (Tenant $t) => ['tenant' => $t] + Subscriptions::of($t));
        $rows = $all->filter(fn (array $r) => $show === 'all' || ($show === 'due' ? $r['due'] > 0 : $r['due'] === 0))->values();
        $page = max(1, (int) $request->query('page', 1));

        return view('central.subscriptions', [
            'rows' => new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'show' => $show, 'search' => $search,
            'totals' => ['clinics' => $all->count(), 'clear' => $all->where('due', 0)->count(), 'with_due' => $all->where('due', '>', 0)->count(),
                'branches_due' => $all->sum('due'), 'left' => Num::money(Num::sum($all->pluck('left')))],
        ]);
    }

    /**
     * Recurring payments: the yearly hosting of each clinic, when it comes next and whether its
     * invoice is made. The date is the day its paid year ends (the licence date).
     */
    public function renewals(Request $request): View
    {
        $within = in_array($request->query('within'), ['overdue', '30', '90', 'all'], true) ? $request->query('within') : '90';
        $today = now()->startOfDay();
        $all = $this->tenants('')->map(function (Tenant $t) use ($today) {
            $sub = Subscriptions::of($t);
            $date = $t->licence_expires_on ?? Invoice::where('tenant_id', $t->id)->where('status', '!=', 'cancelled')->max('to_date');
            $date = $date ? \Illuminate\Support\Carbon::parse($date)->startOfDay() : null;
            $days = $date ? (int) $today->diffInDays($date, false) : null;

            return ['tenant' => $t, 'date' => $date, 'days' => $days, 'yearly' => $sub['yearly'], 'branches' => max(0, $sub['units'] - 1), 'left' => $sub['left'],
                'open' => Invoice::where('tenant_id', $t->id)->where('status', 'unpaid')->orderByDesc('id')->first(),
                'state' => $days === null ? 'none' : ($days < 0 ? 'overdue' : ($days <= 30 ? 'soon' : 'later'))];
        });
        $rows = $all->filter(fn (array $r) => match ($within) {
            'overdue' => $r['state'] === 'overdue', '30' => in_array($r['state'], ['overdue', 'soon'], true),
            '90' => $r['days'] !== null && $r['days'] <= 90, default => true,
        })->sortBy(fn (array $r) => $r['days'] ?? PHP_INT_MAX)->values();
        $sum = fn ($set) => Num::money(Num::sum($set->pluck('yearly')));

        return view('central.renewals', [
            'rows' => $rows, 'within' => $within,
            'totals' => ['yearly' => $sum($all), 'clinics' => $all->count(), 'overdue' => $all->where('state', 'overdue')->count(), 'overdue_amount' => $sum($all->where('state', 'overdue')),
                'soon' => $all->where('state', 'soon')->count(), 'soon_amount' => $sum($all->where('state', 'soon'))],
        ]);
    }

    /** The clinics that are in service (not archived, not waiting for approval). */
    private function tenants(string $search)
    {
        return Tenant::with('seller')->whereIn('status', [Tenant::ACTIVE, Tenant::SUSPENDED])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderBy('name')->get();
    }
}
