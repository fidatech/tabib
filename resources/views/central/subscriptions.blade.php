@extends('layouts.central', ['title' => __('Subscriptions')])

{{-- Which clinic paid, and which of its branches paid or is due. --}}
@section('content')
@php $manage = auth('admin')->user()->allows('billing.manage'); $N = \App\Support\Num::class; @endphp
<x-page-header :title="__('Subscriptions')" icon="list-check" :subtitle="__('Which clinic paid, and which of its branches paid or is due')" />

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="house-medical" /></span><div><div class="kpi-value">{{ $totals['clinics'] }}</div><div class="kpi-label">{{ __('Clinics') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="circle-check" /></span><div><div class="kpi-value">{{ $totals['clear'] }}</div><div class="kpi-label">{{ __('Fully paid') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $totals['with_due'] ? 'kpi-red' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="triangle-exclamation" /></span><div><div class="kpi-value">{{ $totals['with_due'] }}</div><div class="kpi-label">{{ __('With something due') }}</div><div class="kpi-note">{{ $totals['branches_due'] }} {{ __('branches') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $N::isPositive($totals['left']) ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['left']) }}</div><div class="kpi-label">{{ __('Unpaid invoices') }}</div></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="btn-group btn-group-sm no-print" role="group">
            @foreach (['all' => 'All', 'due' => 'Due', 'paid' => 'Fully paid'] as $key => $label)
                <a class="btn {{ $show === $key ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.subscriptions.index', array_filter(['show' => $key === 'all' ? null : $key, 'q' => $search])) }}">{{ __($label) }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="d-flex gap-2 no-print" role="search">@if ($show !== 'all')<input type="hidden" name="show" value="{{ $show }}">@endif
            <input type="search" class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="{{ __('Search a clinic…') }}" aria-label="{{ __('Search') }}"><button class="btn btn-sm btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></form>
    </div>
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="list-check" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Main branch') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Licence until') }}</th><th class="text-end">{{ __('Still to be paid') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $t = $row['tenant']; $main = collect($row['rows'])->firstWhere('main', true); $deps = collect($row['rows'])->where('main', false)->where('counts', true); $S = \App\Services\Subscriptions::class; @endphp
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $t->id]) }}">{{ $t->name }}</a><div class="small text-muted"><span dir="ltr">{{ $t->domain }}</span>@if ($t->seller) · {{ $t->seller->name }}@endif</div></td>
                            <td>@if ($main)<span class="badge bg-{{ $S::TONES[$main['state']] }}-subtle text-{{ $S::TONES[$main['state']] }}">{{ __($S::STATES[$main['state']]) }}</span>@endif</td>
                            <td>@if ($deps->isEmpty())<span class="text-muted">—</span>@else<span class="fw-semibold">{{ $deps->where('state', 'paid')->count() }}</span> / {{ $deps->count() }} {{ __('paid') }}@if ($deps->where('state', '!=', 'paid')->count()) <span class="badge bg-danger-subtle text-danger">{{ $deps->where('state', '!=', 'paid')->count() }} {{ __('due') }}</span>@endif @endif</td>
                            <td dir="ltr">{{ $t->licence_expires_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-end fw-semibold {{ $N::isPositive($row['left']) ? 'text-danger' : '' }}" dir="ltr">${{ money($row['left']) }}</td>
                            <td class="text-end no-print"><div class="act-group">
                                <button type="button" class="btn btn-sm btn-light-secondary" data-bs-toggle="collapse" data-bs-target="#sub{{ $t->id }}" aria-expanded="false" aria-controls="sub{{ $t->id }}">{{ __('Branches') }}</button>
                                @if ($manage)<a class="btn btn-sm btn-light-secondary" href="{{ route('admin.payments.index', ['tenant' => $t->id]) }}">{{ __('Record a payment') }}</a>@endif
                            </div></td>
                        </tr>
                        <tr class="collapse {{ $search !== '' && $rows->count() === 1 ? 'show' : '' }}" id="sub{{ $t->id }}"><td colspan="6" class="bg-body-tertiary">
                            @if ($row['error'])<p class="small text-danger mb-2">{{ __('The database of this clinic could not be opened.') }}</p>@endif
                            @include('central.partials.subscription-rows', ['rows' => $row['rows'], 'invoiceRoute' => 'admin.invoices.show'])
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links() }}
        @endif
    </div>
</div>
@endsection
