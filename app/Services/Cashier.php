<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * The money of the clinic: invoices made from a visit or by hand, payments and refunds, voiding.
 * The totals of an invoice are worked out here and nowhere else, in exact decimals.
 */
class Cashier
{
    /** A new invoice for a patient; from an appointment it starts with the fee of the visit. */
    public static function open(Patient $patient, ?Appointment $appointment, int $userId): Invoice
    {
        return DB::transaction(function () use ($patient, $appointment, $userId) {
            $invoice = Invoice::create([
                'number' => Sequence::next('INV'),
                'patient_id' => $patient->id,
                'appointment_id' => $appointment?->id,
                'doctor_id' => $appointment?->doctor_id,
                'branch_id' => $appointment?->branch_id ?? current_branch()->id,
                'issued_on' => today_iso(),
                'status' => Invoice::UNPAID,
                'created_by' => $userId,
            ]);
            if ($appointment) {
                $appointment->loadMissing(['type', 'doctor.user']);
                $price = $appointment->type && Num::cmp($appointment->type->price, '0') > 0 ? $appointment->type->price : ($appointment->doctor?->fee ?? '0');
                $what = $appointment->type?->label() ?: __('Consultation');
                $invoice->items()->create(['description' => mb_substr($what.' — '.$appointment->doctor?->name(), 0, 200), 'quantity' => 1, 'unit_price' => $price, 'amount' => $price, 'position' => 1]);
                self::total($invoice);
            }

            return $invoice;
        });
    }

    public static function addLine(Invoice $invoice, ?Service $service, string $description, mixed $quantity, mixed $price): InvoiceItem
    {
        self::editable($invoice);
        $quantity = Num::cmp($quantity, '0') > 0 ? Num::clean($quantity) : '1';
        $item = $invoice->items()->create([
            'service_id' => $service?->id,
            'description' => mb_substr($description, 0, 200),
            'quantity' => $quantity,
            'unit_price' => Num::clean($price),
            'amount' => Num::round(Num::mul($quantity, $price), 2),
            'position' => (int) $invoice->items()->max('position') + 1,
        ]);
        self::total($invoice);

        return $item;
    }

    public static function removeLine(Invoice $invoice, int $itemId): void
    {
        self::editable($invoice);
        $invoice->items()->whereKey($itemId)->delete();
        self::total($invoice);
    }

    /** A discount (an amount, not more than the lines) always with its reason. */
    public static function discount(Invoice $invoice, mixed $amount, ?string $reason): void
    {
        self::editable($invoice);
        $amount = Num::clean($amount);
        if (Num::cmp($amount, '0') < 0) {
            throw new UserError(__('A discount cannot be below zero.'));
        }
        if (! Num::isZero($amount) && blank($reason)) {
            throw new UserError(__('Write the reason of the discount.'));
        }
        $invoice->update(['discount' => $amount, 'discount_reason' => Num::isZero($amount) ? null : $reason]);
        self::total($invoice);
    }

    /** Money taken. Not more than what is still owed. */
    public static function pay(Invoice $invoice, mixed $amount, string $method, ?string $reference, int $userId): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $userId) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->isVoid()) {
                throw new UserError(__('This invoice is void.'));
            }
            $amount = Num::round($amount, 2);
            if (Num::cmp($amount, '0') <= 0) {
                throw new UserError(__('Write the amount taken.'));
            }
            if (Num::cmp($amount, $invoice->balance()) > 0) {
                throw new UserError(__('That is more than the :balance still owed.', ['balance' => money($invoice->balance())]));
            }

            return self::record($invoice, $amount, $method, $reference, null, $userId);
        });
    }

    /** Money given back: not more than was paid, always with a reason. */
    public static function refund(Invoice $invoice, mixed $amount, string $method, string $reason, int $userId): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reason, $userId) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $amount = Num::round($amount, 2);
            if (Num::cmp($amount, '0') <= 0) {
                throw new UserError(__('Write the amount given back.'));
            }
            if (Num::cmp($amount, $invoice->paid) > 0) {
                throw new UserError(__('Only :paid was paid on this invoice.', ['paid' => money($invoice->paid)]));
            }

            return self::record($invoice, Num::sub('0', $amount), $method, null, $reason, $userId);
        });
    }

    /** A wrong invoice: kept, marked void, once nothing is left paid on it. */
    public static function void(Invoice $invoice, string $reason, int $userId): void
    {
        DB::transaction(function () use ($invoice, $reason, $userId) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->isVoid()) {
                return;
            }
            if (! Num::isZero($invoice->paid)) {
                throw new UserError(__('Give back the :paid paid on it first, then void it.', ['paid' => money($invoice->paid)]));
            }
            $invoice->update(['status' => Invoice::VOID, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => $reason]);
        });
    }

    /** What a patient still owes on all his invoices. */
    public static function owed(Patient $patient): string
    {
        $owed = '0';
        foreach (Invoice::where('patient_id', $patient->id)->whereIn('status', [Invoice::UNPAID, Invoice::PARTLY])->get(['total', 'paid']) as $i) {
            $owed = Num::add($owed, Num::sub($i->total, $i->paid));
        }

        return Num::round($owed, 2);
    }

    private static function record(Invoice $invoice, string $amount, string $method, ?string $reference, ?string $note, int $userId): Payment
    {
        $payment = Payment::create([
            'number' => Sequence::next(Num::cmp($amount, '0') < 0 ? 'RF' : 'RC'),
            'invoice_id' => $invoice->id,
            'patient_id' => $invoice->patient_id,
            'branch_id' => current_branch()?->id ?? $invoice->branch_id,
            'amount' => $amount,
            'method' => array_key_exists($method, Payment::METHODS) ? $method : 'other',
            'reference' => $reference ?: null,
            'note' => $note,
            'received_at' => now(),
            'received_by' => $userId,
        ]);
        $paid = Num::round(Num::add($invoice->paid, $amount), 2);
        $invoice->update(['paid' => $paid, 'status' => self::status($invoice->total, $paid)]);

        return $payment;
    }

    private static function total(Invoice $invoice): void
    {
        $subtotal = '0';
        foreach ($invoice->items()->get(['amount']) as $item) {
            $subtotal = Num::add($subtotal, $item->amount);
        }
        $discount = Num::cmp($invoice->discount, $subtotal) > 0 ? $subtotal : Num::clean($invoice->discount);
        $total = Num::sub($subtotal, $discount);
        $invoice->update(['subtotal' => Num::round($subtotal, 2), 'discount' => Num::round($discount, 2), 'total' => Num::round($total, 2),
            'status' => self::status($total, $invoice->paid)]);
    }

    private static function status(mixed $total, mixed $paid): string
    {
        return match (true) {
            Num::cmp($paid, '0') > 0 && Num::cmp($paid, $total) >= 0 => Invoice::PAID,
            Num::isZero($total) => Invoice::PAID,          // nothing to pay: a free visit
            Num::cmp($paid, '0') > 0 => Invoice::PARTLY,
            default => Invoice::UNPAID,
        };
    }

    private static function editable(Invoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw new UserError($invoice->isVoid() ? __('This invoice is void.') : __('Money was taken on this invoice, so it is locked. Give the money back and void it to correct it.'));
        }
    }
}
