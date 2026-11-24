<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Central\Admin;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\SellerClaim;
use App\Models\Central\SellerSettlement;
use App\Support\Num;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The account of a reseller with us.
 * He owes us the money he took from clients; we owe him his commission. What he hands over
 * lowers what he owes, what we pay him lowers what we owe:
 *     balance = collected − commission − handed over + paid to him
 * Above zero he owes us; below zero we owe him.
 */
class Sellers
{
    /**
     * The commission of one payment of a client of his.
     * A percentage: of the amount paid. A flat amount: once for each sale, that is with the first
     * payment of an invoice (a payment on account, with no invoice, is a sale of its own).
     */
    public static function commissionFor(Seller $seller, string $amount, ?Invoice $invoice = null): string
    {
        if ($seller->commission_type === 'flat') {
            $counted = $invoice && Payment::where('invoice_id', $invoice->id)->where('seller_id', $seller->id)->whereNull('cancelled_at')->where('commission', '>', 0)->exists();

            return $counted ? '0.00' : Num::money($seller->commission_value);
        }

        return Num::money(Num::div(Num::mul($amount, (string) $seller->commission_value), '100'));
    }

    /**
     * The administrator confirms money a reseller reported: it becomes a payment of the client, taken
     * by the reseller (so he owes it to us) with his commission, and its invoice is settled.
     */
    public static function confirm(SellerClaim $claim, ?Admin $by = null): Payment
    {
        if (! $claim->isPending()) {
            throw new UserError(__('This report is already decided.'));
        }

        return DB::connection($claim->getConnectionName())->transaction(function () use ($claim, $by) {
            $invoice = $claim->invoice && $claim->invoice->status === 'unpaid' ? $claim->invoice : null;
            $payment = Payment::create([
                'tenant_id' => $claim->tenant_id, 'invoice_id' => $invoice?->id, 'amount' => $claim->amount, 'method' => $claim->method, 'reference' => $claim->reference,
                'paid_on' => $claim->paid_on, 'note' => $claim->note, 'admin_id' => $by?->id, 'number' => sprintf('RCP-%06d', ((int) Payment::max('id')) + 1),
                'seller_id' => $claim->seller_id, 'collected_by_seller' => true, 'commission' => self::commissionFor($claim->seller, Num::money($claim->amount), $invoice),
            ]);
            if ($invoice) {
                Billing::settle($invoice);
            }
            $claim->update(['status' => 'confirmed', 'payment_id' => $payment->id, 'decided_by' => $by?->id, 'decided_at' => now()]);

            return $payment;
        });
    }

    /** @return array{sales: string, collected: string, commission: string, received: string, paid_out: string, balance: string, clients: int} */
    public static function account(Seller $seller): array
    {
        $payments = Payment::where('seller_id', $seller->id)->whereNull('cancelled_at');
        $settled = fn (string $direction) => Num::money(SellerSettlement::where('seller_id', $seller->id)->whereNull('cancelled_at')->where('direction', $direction)->sum('amount'));
        $collected = Num::money((clone $payments)->where('collected_by_seller', true)->sum('amount'));
        $commission = Num::money((clone $payments)->sum('commission'));
        $received = $settled('in');
        $paidOut = $settled('out');

        return [
            'sales' => Num::money((clone $payments)->sum('amount')), 'collected' => $collected, 'commission' => $commission,
            'received' => $received, 'paid_out' => $paidOut,
            'balance' => Num::money(Num::add(Num::sub(Num::sub($collected, $commission), $received), $paidOut)),
            'clients' => $seller->tenants()->count(),
        ];
    }

    /**
     * Everything that moved his account, oldest first, with the balance after each line.
     *
     * @return Collection<int, array{date: string, what: string, detail: string, owes: string, earns: string, balance: string, cancelled: bool, settlement: ?SellerSettlement, payment: ?Payment}>
     */
    public static function statement(Seller $seller): Collection
    {
        $lines = collect();
        foreach (Payment::with(['tenant', 'invoice'])->where('seller_id', $seller->id)->whereNull('cancelled_at')->get() as $p) {
            $lines->push(['date' => $p->paid_on->toDateString(), 'order' => 'a'.$p->id, 'what' => $p->collected_by_seller ? 'Took a payment from a client' : 'A client paid us directly',
                'detail' => trim($p->number.' · '.($p->tenant?->name).($p->invoice ? ' · '.$p->invoice->number : '').' · $'.money($p->amount)),
                'owes' => $p->collected_by_seller ? Num::money($p->amount) : '0.00', 'earns' => Num::money($p->commission), 'settlement' => null, 'payment' => $p]);
        }
        foreach (SellerSettlement::where('seller_id', $seller->id)->whereNull('cancelled_at')->get() as $s) {
            $lines->push(['date' => $s->paid_on->toDateString(), 'order' => 'b'.$s->id, 'what' => SellerSettlement::DIRECTIONS[$s->direction],
                'detail' => trim($s->number.($s->reference ? ' · '.$s->reference : '').($s->note ? ' · '.$s->note : '')),
                'owes' => $s->direction === 'out' ? Num::money($s->amount) : '0.00', 'earns' => $s->direction === 'in' ? Num::money($s->amount) : '0.00', 'settlement' => $s, 'payment' => null]);
        }
        $balance = '0';

        return $lines->sortBy(fn (array $l) => $l['date'].$l['order'])->values()->map(function (array $l) use (&$balance) {
            $balance = Num::sub(Num::add($balance, $l['owes']), $l['earns']);

            return $l + ['balance' => Num::money($balance)];
        });
    }
}
