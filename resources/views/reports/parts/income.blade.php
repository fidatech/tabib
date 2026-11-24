<div class="row g-3 mb-3">
    @foreach ([['Invoiced', $totals['invoiced']], ['Discounts', $totals['discount']], ['Money taken', $totals['taken']], ['Given back', $totals['returned']], ['Still owed', $totals['owed']]] as [$label, $amount])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __($label) }}</div><div class="fs-5 fw-bold" dir="ltr">{{ money($amount, $cur) }}</div></div></div></div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By day') }}</h2></div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>{{ __('Day') }}</th><th class="text-end">{{ __('Invoices') }}</th><th class="text-end">{{ __('Invoiced') }}</th><th class="text-end">{{ __('Discounts') }}</th><th class="text-end">{{ __('Money taken') }}</th><th class="text-end">{{ __('Given back') }}</th></tr></thead>
        <tbody>
            @forelse ($days as $day => $d)
                <tr><td><a href="{{ route('billing.daily', ['date' => $day]) }}">{{ fmt_date($day) }}</a></td><td class="text-end">{{ $d['count'] }}</td><td class="text-end" dir="ltr">{{ money($d['invoiced']) }}</td>
                    <td class="text-end" dir="ltr">{{ money($d['discount']) }}</td><td class="text-end" dir="ltr">{{ money($d['taken']) }}</td><td class="text-end" dir="ltr">{{ money($d['returned']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('Nothing in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($days)
            <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end">{{ $totals['count'] }}</td><td class="text-end" dir="ltr">{{ money($totals['invoiced']) }}</td><td class="text-end" dir="ltr">{{ money($totals['discount']) }}</td>
                <td class="text-end" dir="ltr">{{ money($totals['taken']) }}</td><td class="text-end" dir="ltr">{{ money($totals['returned']) }}</td></tr></tfoot>
        @endif
    </table></div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By doctor') }}</h2></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Doctor') }}</th><th class="text-end">{{ __('Invoices') }}</th><th class="text-end">{{ __('Invoiced') }}</th><th class="text-end">{{ __('Still owed') }}</th></tr></thead>
                <tbody>
                    @forelse ($byDoctor as $r)
                        <tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end" dir="ltr">{{ money($r['invoiced']) }}</td><td class="text-end" dir="ltr">{{ money($r['owed']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">—</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By service') }}</h2></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Service') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @forelse ($byService as $r)
                        <tr><td>{{ __($r->label) }}</td><td class="text-end">{{ \App\Support\Num::format($r->qty, 2, true) }}</td><td class="text-end" dir="ltr">{{ money($r->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">—</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
