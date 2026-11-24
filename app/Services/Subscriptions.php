<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Support\Num;
use App\Tenancy\Tenancy;
use Throwable;

/**
 * Who paid and who is due, branch by branch.
 * An invoice bills the main branch and a number of dependent branches. The branches are taken in
 * the order they were opened: an invoice that counts N branches covers the first N of them, so the
 * newest invoice that reaches a branch says until when it is covered and whether that was paid.
 */
class Subscriptions
{
    /** paid: covered and paid · due: invoiced, not fully paid · expired: its paid year has ended · none: never invoiced */
    public const STATES = ['paid' => 'Paid', 'due' => 'Due', 'expired' => 'Renewal due', 'none' => 'Not invoiced'];

    public const TONES = ['paid' => 'success', 'due' => 'danger', 'expired' => 'warning', 'none' => 'secondary'];

    /**
     * @return array{rows: list<array<string, mixed>>, paid: int, due: int, units: int, left: string, yearly: string, error: bool}
     */
    public static function of(Tenant $tenant): array
    {
        try {
            $branches = app(Tenancy::class)->run($tenant, fn () => Branch::whereIn('type', [Branch::MAIN, Branch::DEPENDENT])->orderBy('id')
                ->get(['id', 'name', 'type', 'city', 'province', 'is_active', 'created_at'])->map(fn (Branch $b) => [
                    'id' => $b->id, 'name' => $b->name, 'main' => $b->type === Branch::MAIN, 'place' => implode(', ', array_filter([$b->city, $b->province])),
                    'active' => (bool) $b->is_active, 'opened' => $b->created_at?->toDateString(),
                ])->all());
            $error = false;
        } catch (Throwable $e) {
            report($e);
            $branches = [['id' => 0, 'name' => $tenant->name, 'main' => true, 'place' => '', 'active' => true, 'opened' => $tenant->created_at?->toDateString()]];
            $error = true;   // its database cannot be opened: the main branch is still shown
        }
        $invoices = Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled')->orderBy('to_date')->orderBy('id')->get();
        $left = fn (Invoice $i) => Num::money(Num::sub($i->total, Billing::paidOn($i)));
        $prices = Billing::prices();
        $today = now()->toDateString();

        $rows = [];
        $n = 0;   // place of a dependent branch among the open ones, in the order they were opened
        foreach ($branches as $b) {
            $row = $b + ['state' => 'none', 'until' => null, 'invoice' => null, 'price' => null, 'counts' => $b['active']];
            if ($b['active']) {
                if ($b['main']) {
                    $covering = $invoices->filter(fn (Invoice $i) => collect($i->lines ?? [['key' => 'main_renewal']])->contains(fn (array $l) => str_starts_with((string) ($l['key'] ?? ''), 'main_')));
                } else {
                    $n++;
                    // a yearly invoice renews the branches it already knew and opens its new ones; an invoice
                    // made in the middle of a year bills only the branches added since the one before
                    $covering = $invoices->filter(function (Invoice $i) use ($n) {
                        $keys = array_column($i->lines ?? [], 'key');
                        $known = (int) $i->branches - (int) $i->new_branches;

                        return ! $keys ? $n <= (int) $i->branches
                            : ((in_array('branch_renewal', $keys, true) && $n <= $known) || (in_array('branch_first', $keys, true) && $n > $known && $n <= (int) $i->branches));
                    });
                }
                // an invoice that waits to be paid speaks first; otherwise the one that reaches furthest
                $invoice = $covering->where('status', 'unpaid')->last() ?? $covering->last();
                if ($invoice) {
                    $new = ! $b['main'] && $n > (int) $invoice->branches - (int) $invoice->new_branches;
                    $line = collect($invoice->lines ?? [])->first(fn (array $l) => ($l['key'] ?? '') === ($b['main'] ? null : ($new ? 'branch_first' : 'branch_renewal')))
                        ?? collect($invoice->lines ?? [])->first(fn (array $l) => str_starts_with((string) ($l['key'] ?? ''), $b['main'] ? 'main_' : 'branch_'));
                    $row = ['state' => $invoice->status === 'unpaid' ? 'due' : ($invoice->to_date->toDateString() < $today ? 'expired' : 'paid'),
                        'until' => $invoice->to_date->toDateString(), 'invoice' => $invoice, 'price' => $line['price'] ?? ($b['main'] ? $invoice->main_branch_price : $invoice->branch_price)] + $row;
                } else {
                    $row['price'] = $prices[$b['main'] ? 'main_first' : 'branch_first'];
                }
            }
            $rows[] = $row;
        }
        $open = array_filter($rows, fn (array $r) => $r['counts']);
        $dependents = count(array_filter($open, fn (array $r) => ! $r['main']));

        return [
            'rows' => $rows, 'units' => count($open),
            'paid' => count(array_filter($open, fn (array $r) => $r['state'] === 'paid')),
            'due' => count(array_filter($open, fn (array $r) => $r['state'] !== 'paid')),
            'left' => Num::money(Num::sum($invoices->where('status', 'unpaid')->map($left)->all())),
            // what it pays every year from now on: the hosting of the main branch and of each further branch
            'yearly' => Num::money(Num::add($prices['main_renewal'], Num::mul($prices['branch_renewal'], $dependents))),
            'error' => $error,
        ];
    }
}
