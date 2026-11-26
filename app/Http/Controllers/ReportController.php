<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The reports of a clinic, each for a period (this month unless asked) and one branch: money,
 * appointments, diagnoses, medicines and what patients owe. Every table opens in Excel (?export=xlsx).
 */
class ReportController extends Controller
{
    public const REPORTS = [
        'income' => ['Income', 'chart-line', 'Invoiced, discounts and money taken, by day, by doctor and by service', 'billing.view'],
        'appointments' => ['Appointments', 'calendar-check', 'Booked, seen, did not come and cancelled, by doctor; new patients', 'appointments.view'],
        'diagnoses' => ['Diagnoses', 'virus', 'The most frequent diagnoses of signed visits (ICD-10)', 'clinical.view'],
        'medicines' => ['Medicines prescribed', 'pills', 'The medicines most written on signed prescriptions', 'clinical.view'],
        'profit' => ['Profit', 'scale-balanced', 'Money taken less refunds and expenses (doctor commission paid included), month by month', 'billing.view'],
        'owed' => ['Money owed', 'hand-holding-dollar', 'Patients who still owe, and for how long', 'billing.view'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        return view('reports.index', ['reports' => array_filter(self::REPORTS, fn (array $r) => $request->user()->can($r[3]))]);
    }

    public function show(Request $request, string $report): View
    {
        Gate::authorize('reports.view');
        abort_unless(isset(self::REPORTS[$report]), 404);
        Gate::authorize(self::REPORTS[$report][3]);
        [$from, $to] = $this->period($request);
        $branch = viewed_branch();

        return view('reports.show', [
            'report' => $report, 'meta' => self::REPORTS[$report], 'from' => $from, 'to' => $to, 'branch' => $branch,
            'branches' => can_oversee() ? \App\Models\Branch::withBooks()->orderBy('name')->get() : collect(),
            'cur' => setting('currency'),
        ] + $this->{$report}($from, $to, $branch->id));
    }

    private function income(Carbon $from, Carbon $to, int $branch): array
    {
        $invoices = Invoice::with('doctor.user')->where('branch_id', $branch)->where('status', '!=', Invoice::VOID)
            ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])->get();
        $payments = Payment::where('branch_id', $branch)->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();

        $days = [];
        foreach ($invoices as $i) {
            $d = $i->issued_on->toDateString();
            $days[$d] ??= ['count' => 0, 'invoiced' => '0', 'discount' => '0', 'taken' => '0', 'returned' => '0'];
            $days[$d]['count']++;
            $days[$d]['invoiced'] = Num::add($days[$d]['invoiced'], $i->total);
            $days[$d]['discount'] = Num::add($days[$d]['discount'], $i->discount);
        }
        foreach ($payments as $p) {
            $d = $p->received_at->toDateString();
            $days[$d] ??= ['count' => 0, 'invoiced' => '0', 'discount' => '0', 'taken' => '0', 'returned' => '0'];
            $p->isRefund() ? $days[$d]['returned'] = Num::add($days[$d]['returned'], Num::abs($p->amount)) : $days[$d]['taken'] = Num::add($days[$d]['taken'], $p->amount);
        }
        ksort($days);

        $byDoctor = $invoices->groupBy(fn (Invoice $i) => $i->doctor_id ?? 0)->map(fn (Collection $list) => [
            'name' => $list->first()->doctor?->name() ?? __('No doctor'), 'count' => $list->count(),
            'invoiced' => Num::sum($list->pluck('total')), 'owed' => Num::sum($list->map->balance()),
        ])->sortByDesc(fn ($r) => (float) $r['invoiced'])->values();

        $byService = InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.branch_id', $branch)->where('invoices.status', '!=', Invoice::VOID)
            ->whereBetween('invoices.issued_on', [$from->toDateString(), $to->toDateString()])
            ->leftJoin('services', 'services.id', '=', 'invoice_items.service_id')
            ->selectRaw("coalesce(services.name, case when invoices.appointment_id is not null and invoice_items.position = 1 then 'Consultation' else invoice_items.description end) as label,
                sum(invoice_items.quantity) as qty, sum(invoice_items.amount) as amount")
            ->groupBy('label')->orderByDesc('amount')->limit(30)->get();

        return ['days' => $days, 'byDoctor' => $byDoctor, 'byService' => $byService,
            'totals' => ['count' => $invoices->count(), 'invoiced' => Num::sum($invoices->pluck('total')), 'discount' => Num::sum($invoices->pluck('discount')),
                'taken' => Num::sum($payments->filter(fn ($p) => ! $p->isRefund())->pluck('amount')), 'returned' => Num::abs(Num::sum($payments->filter->isRefund()->pluck('amount'))),
                'owed' => Num::sum($invoices->map->balance())]];
    }

    private function appointments(Carbon $from, Carbon $to, int $branch): array
    {
        $rows = Appointment::where('branch_id', $branch)->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select('doctor_id', 'status', 'source', DB::raw('count(*) as n'))->groupBy('doctor_id', 'status', 'source')->get();
        $doctors = Doctor::with('user')->get()->keyBy('id');
        $byDoctor = [];
        $totals = array_fill_keys(array_keys(Appointment::STATUSES), 0) + ['walk_in' => 0, 'all' => 0];
        foreach ($rows as $r) {
            $byDoctor[$r->doctor_id] ??= array_fill_keys(array_keys(Appointment::STATUSES), 0) + ['walk_in' => 0, 'all' => 0, 'name' => $doctors->get($r->doctor_id)?->name() ?? '—'];
            foreach ([&$byDoctor[$r->doctor_id], &$totals] as &$t) {
                $t[$r->status] = ($t[$r->status] ?? 0) + $r->n;
                $t['all'] += $r->n;
                if ($r->source === 'walk_in') {
                    $t['walk_in'] += $r->n;
                }
            }
            unset($t);
        }
        uasort($byDoctor, fn ($a, $b) => $b['all'] <=> $a['all']);

        return ['byDoctor' => $byDoctor, 'totals' => $totals,
            'newPatients' => Patient::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count()];
    }

    private function diagnoses(Carbon $from, Carbon $to, int $branch): array
    {
        $rows = DB::table('encounter_diagnoses')->join('encounters', 'encounters.id', '=', 'encounter_diagnoses.encounter_id')
            ->where('encounters.branch_id', $branch)->where('encounters.status', 'signed')
            ->whereBetween('encounters.started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('encounter_diagnoses.icd10 as code, min(encounter_diagnoses.description) as description, count(*) as n, count(distinct encounters.patient_id) as patients, sum(case when encounter_diagnoses.is_primary then 1 else 0 end) as main')
            ->groupBy('encounter_diagnoses.icd10', DB::raw('case when encounter_diagnoses.icd10 is null then encounter_diagnoses.description else \'\' end'))
            ->orderByDesc('n')->limit(50)->get();

        return ['rows' => $rows, 'visits' => DB::table('encounters')->where('branch_id', $branch)->where('status', 'signed')
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count()];
    }

    private function medicines(Carbon $from, Carbon $to, int $branch): array
    {
        $rows = DB::table('prescription_items')->join('prescriptions', 'prescriptions.id', '=', 'prescription_items.prescription_id')
            ->where('prescriptions.branch_id', $branch)->where('prescriptions.status', 'signed')
            ->whereBetween('prescriptions.signed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("prescription_items.name as name, coalesce(prescription_items.strength, '') as strength, coalesce(prescription_items.form, '') as form, count(*) as n, count(distinct prescriptions.patient_id) as patients")
            ->groupBy('prescription_items.name', DB::raw("coalesce(prescription_items.strength, '')"), DB::raw("coalesce(prescription_items.form, '')"))
            ->orderByDesc('n')->limit(50)->get();

        return ['rows' => $rows, 'prescriptions' => DB::table('prescriptions')->where('branch_id', $branch)->where('status', 'signed')
            ->whereBetween('signed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count()];
    }

    /** Money in and out by month: taken, given back, every kind of expense, and what is left. */
    private function profit(Carbon $from, Carbon $to, int $branch): array
    {
        $months = [];
        $blank = fn () => ['taken' => '0', 'returned' => '0', 'expenses' => [], 'spent' => '0'];
        foreach (Payment::where('branch_id', $branch)->whereBetween('received_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get(['amount', 'received_at']) as $p) {
            $m = $p->received_at->format('Y-m');
            $months[$m] ??= $blank();
            $p->isRefund() ? $months[$m]['returned'] = Num::add($months[$m]['returned'], Num::abs($p->amount)) : $months[$m]['taken'] = Num::add($months[$m]['taken'], $p->amount);
        }
        $categories = [];
        foreach (\App\Models\Expense::counted()->where('branch_id', $branch)->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->get(['amount', 'spent_on', 'category']) as $e) {
            $m = $e->spent_on->format('Y-m');
            $months[$m] ??= $blank();
            $months[$m]['expenses'][$e->category] = Num::add($months[$m]['expenses'][$e->category] ?? '0', $e->amount);
            $months[$m]['spent'] = Num::add($months[$m]['spent'], $e->amount);
            $categories[$e->category] = Num::add($categories[$e->category] ?? '0', $e->amount);
        }
        ksort($months);
        $totals = ['taken' => Num::sum(array_column($months, 'taken')), 'returned' => Num::sum(array_column($months, 'returned')), 'spent' => Num::sum(array_column($months, 'spent'))];
        $totals['profit'] = Num::sub(Num::sub($totals['taken'], $totals['returned']), $totals['spent']);
        arsort($categories);

        return ['months' => $months, 'categories' => $categories, 'totals' => $totals];
    }

    /** Owed now (the period is not used): by patient, with the age of the oldest unpaid invoice. */
    private function owed(Carbon $from, Carbon $to, int $branch): array
    {
        $today = Carbon::parse(today_iso());
        $buckets = ['0-30' => '0', '31-60' => '0', '61-90' => '0', '90+' => '0'];
        $patients = [];
        foreach (Invoice::with('patient')->where('branch_id', $branch)->whereIn('status', [Invoice::UNPAID, Invoice::PARTLY])->orderBy('issued_on')->get() as $i) {
            $owed = $i->balance();
            $days = (int) $i->issued_on->diffInDays($today);
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $buckets[$bucket] = Num::add($buckets[$bucket], $owed);
            $patients[$i->patient_id] ??= ['patient' => $i->patient, 'invoices' => 0, 'owed' => '0', 'oldest' => $i->issued_on, 'days' => $days];
            $patients[$i->patient_id]['invoices']++;
            $patients[$i->patient_id]['owed'] = Num::add($patients[$i->patient_id]['owed'], $owed);
        }
        uasort($patients, fn ($a, $b) => Num::cmp($b['owed'], $a['owed']));

        return ['patients' => $patients, 'buckets' => $buckets, 'total' => Num::sum($buckets)];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $parse = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && strtotime((string) $v) ? Carbon::parse($v) : null;
        $today = Carbon::parse(today_iso());
        $from = $parse($request->query('from')) ?? $today->copy()->startOfMonth();
        $to = $parse($request->query('to')) ?? $today->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 400) {
            $from = $to->copy()->subDays(400);
        }

        return [$from, $to];
    }
}
