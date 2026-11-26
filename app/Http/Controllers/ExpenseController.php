<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\Commission;
use App\Services\Sequence;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** What the clinic spends, month by month, and the commission of the doctors with its statement and payment. */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('billing.view');
        $month = $this->month($request);
        $from = Carbon::parse($month.'-01');
        $branch = viewed_branch();
        $category = (string) $request->query('category', '');
        $rows = Expense::with(['creator', 'doctor.user'])->where('branch_id', $branch->id)->whereBetween('spent_on', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])
            ->when(isset(Expense::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))->orderByDesc('spent_on')->orderByDesc('id')->get();
        $counted = $rows->whereNull('voided_at');

        return view('expenses.index', ['rows' => $rows, 'month' => $month, 'category' => $category, 'branch' => $branch,
            'total' => Num::sum($counted->pluck('amount')),
            'byCategory' => $counted->groupBy('category')->map(fn ($l) => Num::sum($l->pluck('amount')))->sortByDesc(fn ($v) => (float) $v),
            'canManage' => $request->user()->can('billing.manage')]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $data = $request->validate([
            'spent_on' => ['required', 'date', 'before_or_equal:today'], 'category' => ['required', Rule::in(array_diff(array_keys(Expense::CATEGORIES), ['commission']))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'], 'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_to' => ['nullable', 'string', 'max:150'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $e = Expense::create($data + ['number' => Sequence::next('EX'), 'branch_id' => current_branch()->id, 'created_by' => $request->user()->id]);

        return redirect()->route('expenses.index', ['month' => $e->spent_on->format('Y-m')])->with('success', __('Expense :number saved.', ['number' => $e->number]));
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $e = Expense::where('branch_id', current_branch()->id)->findOrFail($id);
        if ($e->voided_at) {
            throw new UserError(__('This expense is void already.'));
        }
        $e->update(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']]);

        return back()->with('success', __('Expense :number is void.', ['number' => $e->number]));
    }

    /* ------------------------------------------------------------ commission */

    public function commission(Request $request): View
    {
        Gate::authorize('billing.view');
        $month = $this->month($request);
        $branch = viewed_branch();
        $doctors = Doctor::with('user')->where(fn ($q) => $q->where('is_active', true)->orWhere('commission_type', '!=', 'none'))->get()->sortBy(fn ($d) => $d->name());

        return view('expenses.commission', ['month' => $month, 'branch' => $branch,
            'statements' => $doctors->map(fn (Doctor $d) => Commission::statement($d, $month, $branch->id))->values(),
            'canManage' => $request->user()->can('billing.manage')]);
    }

    public function statement(Request $request, int $doctor): View
    {
        Gate::authorize('billing.view');
        $month = $this->month($request);
        $d = Doctor::with('user')->findOrFail($doctor);

        return view('expenses.statement', ['s' => Commission::statement($d, $month, viewed_branch()->id), 'month' => $month, 'doctor' => $d,
            'paid' => Expense::counted()->where('doctor_id', $d->id)->where('commission_month', $month)->orderBy('spent_on')->get()]);
    }

    /** Paying a doctor his share: it becomes an expense of the clinic for that month, not more than is left. */
    public function pay(Request $request, int $doctor): RedirectResponse
    {
        Gate::authorize('billing.manage');
        $d = Doctor::with('user')->findOrFail($doctor);
        $month = $this->month($request);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'note' => ['nullable', 'string', 'max:255']]);
        $s = Commission::statement($d, $month, current_branch()->id);
        if (Num::cmp($data['amount'], $s['left']) > 0) {
            throw new UserError(__('Only :left is left to pay for this month.', ['left' => money($s['left'])]));
        }
        $e = Expense::create(['number' => Sequence::next('EX'), 'branch_id' => current_branch()->id, 'spent_on' => today_iso(), 'category' => 'commission',
            'amount' => Num::round($data['amount'], 2), 'method' => $data['method'], 'paid_to' => $d->name(), 'note' => $data['note'] ?? __('Commission of :month', ['month' => $month]),
            'doctor_id' => $d->id, 'commission_month' => $month, 'created_by' => $request->user()->id]);

        return redirect()->route('commission.statement', ['doctor' => $d->id, 'month' => $month])->with('success', __(':amount paid to :name (:number).', ['amount' => money($e->amount, setting('currency')), 'name' => $d->name(), 'number' => $e->number]));
    }

    private function month(Request $request): string
    {
        $m = (string) $request->input('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : Carbon::parse(today_iso())->format('Y-m');
    }
}
