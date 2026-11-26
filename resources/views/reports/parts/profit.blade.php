@php $C = \App\Models\Expense::CATEGORIES; $N = \App\Support\Num::class; @endphp
<div class="row g-3 mb-3">
    @foreach ([['Money taken', $totals['taken'], ''], ['Given back', $totals['returned'], ''], ['Expenses', $totals['spent'], ''], ['Profit', $totals['profit'], $N::cmp($totals['profit'], '0') < 0 ? 'text-danger' : 'text-success']] as [$label, $amount, $tone])
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __($label) }}</div><div class="fs-4 fw-bold {{ $tone }}" dir="ltr">{{ money($amount, $cur) }}</div></div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By month') }}</h2></div>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Money taken') }}</th><th class="text-end">{{ __('Given back') }}</th><th class="text-end">{{ __('Expenses') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
                <tbody>
                    @forelse ($months as $m => $r)
                        @php $p = $N::sub($N::sub($r['taken'], $r['returned']), $r['spent']); @endphp
                        <tr><td><a href="{{ route('expenses.index', ['month' => $m]) }}" dir="ltr">{{ $m }}</a></td><td class="text-end" dir="ltr">{{ money($r['taken']) }}</td><td class="text-end" dir="ltr">{{ money($r['returned']) }}</td>
                            <td class="text-end" dir="ltr">{{ money($r['spent']) }}</td><td class="text-end fw-semibold {{ $N::cmp($p, '0') < 0 ? 'text-danger' : '' }}" dir="ltr">{{ money($p) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">{{ __('Nothing in this period.') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($months)
                    <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($totals['taken']) }}</td><td class="text-end" dir="ltr">{{ money($totals['returned']) }}</td>
                        <td class="text-end" dir="ltr">{{ money($totals['spent']) }}</td><td class="text-end" dir="ltr">{{ money($totals['profit']) }}</td></tr></tfoot>
                @endif
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Expenses by kind') }}</h2></div>
            <table class="table table-sm mb-0"><tbody>
                @forelse ($categories as $k => $amount)<tr><td>{{ __($C[$k] ?? $k) }}</td><td class="text-end" dir="ltr">{{ money($amount) }}</td></tr>@empty<tr><td class="text-muted">—</td></tr>@endforelse
            </tbody></table>
        </div>
        <p class="small text-muted mt-2">{{ __('The profit counts money when it is taken or paid out; invoices not yet paid are not in it.') }}</p>
    </div>
</div>
