<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Num;
use Illuminate\Support\Carbon;

/**
 * The share of a doctor for a month: a percent of the money taken in that month for his invoices
 * (refunds taken off), or an amount for every patient he saw (appointments done). What was paid to
 * him for that month is an expense of the clinic, so the profit counts it once.
 */
class Commission
{
    public const TYPES = ['none' => 'No commission', 'percent' => 'A percent of the money taken for his patients', 'per_visit' => 'An amount for every patient seen'];

    /** @return array{doctor: Doctor, collected: string, visits: int, due: string, paid: string, left: string, payments: \Illuminate\Support\Collection} */
    public static function statement(Doctor $doctor, string $month, int $branchId): array
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $payments = Payment::with(['invoice', 'patient'])->where('branch_id', $branchId)->whereBetween('received_at', [$from, $to])
            ->whereHas('invoice', fn ($q) => $q->where('doctor_id', $doctor->id)->where('status', '!=', Invoice::VOID))->orderBy('received_at')->get();
        $collected = Num::sum($payments->pluck('amount'));
        $visits = Appointment::where('doctor_id', $doctor->id)->where('branch_id', $branchId)->where('status', Appointment::COMPLETED)->whereBetween('starts_at', [$from, $to])->count();
        $due = match ($doctor->commission_type) {
            'percent' => Num::round(Num::div(Num::mul($collected, $doctor->commission_value), '100'), 2),
            'per_visit' => Num::round(Num::mul((string) $visits, $doctor->commission_value), 2),
            default => '0',
        };
        if (Num::cmp($due, '0') < 0) {
            $due = '0';   // a month of refunds only gives no share below nothing
        }
        $paid = Num::sum(Expense::counted()->where('doctor_id', $doctor->id)->where('commission_month', $month)->where('branch_id', $branchId)->pluck('amount'));

        return ['doctor' => $doctor, 'collected' => $collected, 'visits' => $visits, 'due' => $due, 'paid' => $paid, 'left' => Num::sub($due, $paid), 'payments' => $payments];
    }

    public static function describe(Doctor $doctor): string
    {
        return match ($doctor->commission_type) {
            'percent' => __(':value% of the money taken', ['value' => Num::format($doctor->commission_value, 2, true)]),
            'per_visit' => __(':value for every patient seen', ['value' => money($doctor->commission_value)]),
            default => __('No commission'),
        };
    }
}
