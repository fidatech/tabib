@extends('layouts.central', ['title' => __('Income reports')])

@section('content')
<x-page-header :title="__('Income reports')" icon="chart-column" :subtitle="__('US dollars')">
    <form method="GET"><select class="form-select" name="year" data-plain onchange="this.form.submit()" aria-label="{{ __('Year') }}">@foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach</select></form>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="file-invoice-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['invoiced']) }}</div><div class="kpi-label">{{ __('Invoiced in :year', ['year' => $year]) }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['received']) }}</div><div class="kpi-label">{{ __('Received in :year', ['year' => $year]) }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="kpi kpi-amber"><span class="kpi-icon"><x-icon name="hourglass-half" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['due']) }}</div><div class="kpi-label">{{ __('Still to be paid, all years') }}</div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Month by month') }}</h2><div class="legend"><span><i style="background:#c9d6ea"></i>{{ __('Invoiced') }}</span><span><i style="background:#2a78d6"></i>{{ __('Received') }}</span></div></div>
            <div class="card-body">
                @php $top = max(1, max(array_map(fn ($m) => max((float) $m['invoiced'], (float) $m['received']), $months))); @endphp
                <div class="bars" role="img" aria-label="{{ __('Month by month') }}">
                    @foreach ($months as $month => $row)
                        <div class="bars-col" title="{{ $month }}: ${{ money($row['invoiced']) }} / ${{ money($row['received']) }}">
                            <div class="bars-pair"><span class="bars-bar is-alt" style="height: {{ max(1, round((float) $row['invoiced'] / $top * 100)) }}%"></span><span class="bars-bar" style="height: {{ max(1, round((float) $row['received'] / $top * 100)) }}%"></span></div>
                            <span class="bars-label" dir="ltr">{{ substr($month, 5) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('How it was paid') }}</h2></div><div class="card-body">
            <dl class="facts">@foreach ($methods as $key => $amount)<div><dt>{{ __(\App\Models\Central\Payment::METHODS[$key]) }}</dt><dd dir="ltr">${{ money($amount) }}</dd></div>@endforeach</dl>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Clinic by clinic') }}</h2></div><div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Clinic') }}</th><th class="text-end">{{ __('Invoiced in :year', ['year' => $year]) }}</th><th class="text-end">{{ __('Received in :year', ['year' => $year]) }}</th><th class="text-end">{{ __('Still to be paid') }}</th></tr></thead>
                <tbody>
                    @forelse ($clients as $row)
                        <tr><td><a href="{{ route('admin.tenants.show', ['id' => $row['tenant']->id]) }}">{{ $row['tenant']->name }}</a></td><td class="text-end" dir="ltr">${{ money($row['invoiced']) }}</td><td class="text-end" dir="ltr">${{ money($row['received']) }}</td><td class="text-end {{ \App\Support\Num::isPositive($row['due']) ? 'text-danger fw-semibold' : '' }}" dir="ltr">${{ money($row['due']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><th>{{ __('Total') }}</th><th class="text-end" dir="ltr">${{ money($totals['invoiced']) }}</th><th class="text-end" dir="ltr">${{ money($totals['received']) }}</th><th class="text-end" dir="ltr">${{ money($totals['due']) }}</th></tr></tfoot>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Renewals in the next 90 days') }}</h2></div><div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Licence until') }}</th><th class="text-end">{{ __('Days') }}</th></tr></thead>
                <tbody>
                    @forelse ($renewals as $row)
                        <tr><td><a href="{{ route('admin.invoices.index', ['tenant' => $row['tenant']->id]) }}">{{ $row['tenant']->name }}</a></td><td dir="ltr">{{ $row['tenant']->licence_expires_on->format('Y-m-d') }}</td><td class="text-end"><span class="badge {{ $row['days'] < 0 ? 'bg-danger' : ($row['days'] <= 30 ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary') }}">{{ $row['days'] }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
</div>
@endsection
