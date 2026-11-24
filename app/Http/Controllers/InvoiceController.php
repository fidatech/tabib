<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Cashier;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Billing at the front desk: an invoice for the visit, the money taken for it (and a receipt),
 * money given back, a wrong invoice voided; the list of invoices and the cash of a day.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('billing.view');
        $term = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        [$from, $to] = [$this->date($request->query('from')), $this->date($request->query('to'))];
        $branch = viewed_branch();

        $rows = Invoice::with(['patient', 'doctor.user'])->where('branch_id', $branch->id)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%$term%")
                ->orWhereHas('patient', fn ($p) => $p->withTrashed()->where('name', 'like', "%$term%")->orWhere('number', 'like', "%$term%")->orWhere('phone', 'like', "%$term%"))))
            ->when(array_key_exists($status, Invoice::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($status === 'owed', fn ($q) => $q->whereIn('status', [Invoice::UNPAID, Invoice::PARTLY]))
            ->when($from, fn ($q) => $q->where('issued_on', '>=', $from))
            ->when($to, fn ($q) => $q->where('issued_on', '<=', $to))
            ->latest('id')->paginate(25)->withQueryString();

        return view('invoices.index', ['rows' => $rows, 'term' => $term, 'status' => $status, 'from' => $from, 'to' => $to, 'branch' => $branch,
            'canManage' => $request->user()->can('billing.manage')]);
    }

    /** From the queue (an appointment) or the chart (a patient). An appointment has one invoice: it is opened again. */
    public function start(Request $request): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $data = $request->validate(['patient_id' => ['required_without:appointment_id', 'nullable', 'integer'], 'appointment_id' => ['nullable', 'integer']]);
        $appointment = ! empty($data['appointment_id']) ? Appointment::findOrFail($data['appointment_id']) : null;
        if ($appointment && ($open = Invoice::where('appointment_id', $appointment->id)->where('status', '!=', Invoice::VOID)->first())) {
            return redirect()->route('invoices.show', ['id' => $open->id]);
        }
        $patient = $appointment ? $appointment->patient : Patient::findOrFail($data['patient_id']);
        $invoice = Cashier::open($patient, $appointment, $request->user()->id);
        audit('created', $invoice);

        return redirect()->route('invoices.show', ['id' => $invoice->id]);
    }

    public function show(Request $request, int $id): View
    {
        Gate::authorize('billing.view');
        $invoice = $this->find($id);

        return view('invoices.show', [
            'invoice' => $invoice,
            'patient' => $invoice->patient,
            'canManage' => $request->user()->can('billing.manage'),
            'editable' => $invoice->isEditable() && $request->user()->can('billing.manage'),
            'services' => Service::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'owedElsewhere' => Num::sub(Cashier::owed($invoice->patient), $invoice->balance()),
        ]);
    }

    public function addItem(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $invoice = $this->find($id);
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'description' => ['required_without:service_id', 'nullable', 'string', 'max:200'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);
        $service = ! empty($data['service_id']) ? Service::find($data['service_id']) : null;
        $price = ($data['unit_price'] ?? null) !== null && $data['unit_price'] !== '' ? $data['unit_price'] : ($service?->price ?? '0');
        Cashier::addLine($invoice, $service, ($data['description'] ?? null) ?: $service->label(), $data['quantity'] ?? 1, $price);

        return redirect()->route('invoices.show', ['id' => $invoice->id]);
    }

    public function removeItem(Request $request, int $id, int $item): RedirectResponse
    {
        Gate::authorize('billing.manage');
        Cashier::removeLine($this->find($id), $item);

        return redirect()->route('invoices.show', ['id' => $id]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $invoice = $this->find($id);
        $data = $request->validate(['discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'discount_reason' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:500']]);
        if ($invoice->isEditable()) {
            Cashier::discount($invoice, $data['discount'] ?? 0, $data['discount_reason'] ?? null);
        }
        $invoice->update(['note' => $data['note'] ?? null]);

        return redirect()->route('invoices.show', ['id' => $invoice->id])->with('success', __('Saved.'));
    }

    public function pay(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'reference' => ['nullable', 'string', 'max:80']]);
        $payment = Cashier::pay($this->find($id), $data['amount'], $data['method'], $data['reference'] ?? null, $request->user()->id);
        audit('created', $payment, $payment->number.' · '.money($payment->amount));

        return redirect()->route('invoices.show', ['id' => $id])->with('success', __('Receipt :number: :amount taken.', ['number' => $payment->number, 'amount' => money($payment->amount, setting('currency'))]))
            ->with('receipt', $payment->id);
    }

    public function refund(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'reason' => ['required', 'string', 'max:255']]);
        $payment = Cashier::refund($this->find($id), $data['amount'], $data['method'], $data['reason'], $request->user()->id);
        audit('refunded', $payment, $payment->number.' · '.money($payment->amount).' · '.$data['reason']);

        return redirect()->route('invoices.show', ['id' => $id])->with('success', __(':amount given back (:number).', ['number' => $payment->number, 'amount' => money(Num::abs($payment->amount), setting('currency'))]));
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $invoice = $this->find($id);
        Cashier::void($invoice, $reason, $request->user()->id);
        audit('voided', $invoice, $invoice->number.' · '.$reason);

        return redirect()->route('invoices.show', ['id' => $id])->with('success', __('The invoice is void.'));
    }

    public function print(Request $request, int $id): View
    {
        Gate::authorize('billing.view');

        return view('invoices.paper', ['invoice' => $this->find($id), 'payment' => null]);
    }

    public function receipt(Request $request, int $payment): View
    {
        Gate::authorize('billing.view');
        $payment = Payment::with('receiver')->findOrFail($payment);
        return view('invoices.paper', ['invoice' => $this->find($payment->invoice_id), 'payment' => $payment]);
    }

    /**
     * The cash of a day in one branch: every receipt and refund, totals by way of paying and by
     * the person who took the money, and the invoices of the day with what is still owed.
     */
    public function daily(Request $request): View
    {
        Gate::authorize('billing.view');
        $day = Carbon::parse($this->date($request->query('date')) ?? today_iso());
        $branch = viewed_branch();
        $payments = Payment::with(['patient', 'invoice', 'receiver'])->where('branch_id', $branch->id)
            ->whereBetween('received_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->orderBy('received_at')->orderBy('id')->get();
        $invoices = Invoice::where('branch_id', $branch->id)->where('issued_on', $day->toDateString())->get();

        $byMethod = [];
        $byUser = [];
        $taken = '0';
        $returned = '0';
        foreach ($payments as $p) {
            $byMethod[$p->method] = Num::add($byMethod[$p->method] ?? '0', $p->amount);
            $key = $p->received_by ?? 0;
            $byUser[$key] = ['name' => $p->receiver?->name ?? '—', 'amount' => Num::add($byUser[$key]['amount'] ?? '0', $p->amount), 'count' => ($byUser[$key]['count'] ?? 0) + 1];
            $p->isRefund() ? $returned = Num::add($returned, Num::abs($p->amount)) : $taken = Num::add($taken, $p->amount);
        }
        $live = $invoices->where('status', '!=', Invoice::VOID);

        return view('invoices.daily', [
            'day' => $day, 'branch' => $branch, 'payments' => $payments, 'byMethod' => $byMethod, 'byUser' => $byUser,
            'taken' => $taken, 'returned' => $returned, 'net' => Num::sub($taken, $returned),
            'invoiced' => Num::sum($live->pluck('total')), 'discounts' => Num::sum($live->pluck('discount')),
            'owed' => Num::sum($live->map(fn (Invoice $i) => $i->balance())), 'invoiceCount' => $live->count(), 'voided' => $invoices->where('status', Invoice::VOID)->count(),
            'branches' => can_oversee() ? Branch::withBooks()->orderBy('name')->get() : collect(),
        ]);
    }

    private function find(int $id): Invoice
    {
        $invoice = Invoice::with(['patient', 'doctor.user', 'items', 'payments.receiver', 'creator', 'voider', 'appointment.type', 'branch'])->findOrFail($id);
        abort_unless($invoice->branch_id === current_branch()->id || can_oversee(), 404);

        return $invoice;
    }

    private function date(mixed $value): ?string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }
}
