<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Support\Num;
use App\Tenancy\Tenancy;

/**
 * What a clinic pays Fida Technologies, in US dollars.
 * The main branch: 500 the first year (the system and its setup), then 100 a year for hosting.
 * Every further branch: 100 the first year (its setup), then 50 a year for hosting.
 * The four prices can be changed by the system administrator.
 */
class Billing
{
    public const DEFAULTS = ['main_first' => '500', 'main_renewal' => '100', 'branch_first' => '100', 'branch_renewal' => '50'];

    public const LABELS = [
        'main_first' => 'Main branch, first year', 'main_renewal' => 'Main branch, hosting for a year',
        'branch_first' => 'A further branch, first year', 'branch_renewal' => 'A further branch, hosting for a year',
    ];

    /**
     * What a clinic owes: everything invoiced to it (cancelled invoices aside) less everything it paid.
     *
     * @return array{invoiced: string, paid: string, due: string}
     */
    public static function balance(Tenant $tenant): array
    {
        $invoiced = Num::money(Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled')->sum('total'));
        $paid = Num::money(\App\Models\Central\Payment::where('tenant_id', $tenant->id)->whereNull('cancelled_at')->sum('amount'));

        return ['invoiced' => $invoiced, 'paid' => $paid, 'due' => Num::money(Num::sub($invoiced, $paid))];
    }

    /** What was paid towards one invoice. */
    public static function paidOn(Invoice $invoice): string
    {
        return Num::money(\App\Models\Central\Payment::where('invoice_id', $invoice->id)->whereNull('cancelled_at')->sum('amount'));
    }

    /**
     * After a payment came or went: an invoice that is covered is paid (and a yearly one moves the
     * licence date), one that is no longer covered is not.
     */
    public static function settle(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }
        $covered = Num::cmp(self::paidOn($invoice), $invoice->total) >= 0;
        $invoice->update(['status' => $covered ? 'paid' : 'unpaid', 'paid_on' => $covered ? ($invoice->paid_on ?: now()->toDateString()) : null]);
        $tenant = $invoice->tenant;
        $yearly = collect($invoice->lines ?? [['key' => 'main_renewal']])->contains(fn (array $line) => str_starts_with((string) ($line['key'] ?? ''), 'main_'));
        if ($covered && $yearly && (! $tenant->licence_expires_on || $tenant->licence_expires_on->lt($invoice->to_date))) {
            $tenant->update(['licence_expires_on' => $invoice->to_date]);
        }
        Licence::check($tenant);
    }

    /** @return array<string, string> the four prices */
    public static function prices(): array
    {
        $prices = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = CentralSetting::read('price_'.$key);
            $prices[$key] = Num::money($value !== null && is_numeric($value) ? $value : $default);
        }

        return $prices;
    }

    /**
     * What the next invoice of a clinic holds.
     * First year or renewal is read from its earlier invoices (cancelled ones do not count): the
     * main branch is new when there is none; a branch is new when no earlier invoice counted it.
     *
     * @param  bool  $onlyNew  an invoice in the middle of a year, for the branches added since the last one and nothing else
     * @return array{lines: list<array{key: string, label: string, quantity: int, price: string, amount: string}>, total: string, branches: int, new_branches: int, first: bool}
     */
    public static function quote(Tenant $tenant, bool $onlyNew = false): array
    {
        $prices = self::prices();
        $branches = (int) app(Tenancy::class)->run($tenant, fn () => Branch::where('type', Branch::DEPENDENT)->where('is_active', true)->count());
        $earlier = Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled');
        $first = ! (clone $earlier)->exists();
        $billed = (int) (clone $earlier)->max('branches');
        $new = max(0, $branches - $billed);
        $old = $branches - $new;

        $lines = [];
        $add = function (string $key, int $quantity) use (&$lines, $prices) {
            if ($quantity > 0) {
                $lines[] = ['key' => $key, 'label' => self::LABELS[$key], 'quantity' => $quantity, 'price' => $prices[$key], 'amount' => Num::money(Num::mul($prices[$key], $quantity))];
            }
        };
        if (! $onlyNew) {
            $add($first ? 'main_first' : 'main_renewal', 1);
            $add('branch_renewal', $old);
        }
        $add('branch_first', $new);

        return [
            'lines' => $lines, 'total' => Num::money(Num::sum(array_column($lines, 'amount'))),
            'branches' => $branches, 'new_branches' => $new, 'first' => $first,
        ];
    }
}
