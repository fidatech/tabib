@extends('layouts.central', ['title' => __('Recurring payments')])

{{-- The yearly hosting of every clinic: when it comes next, how much, and whether its invoice is made. --}}
@section('content')
@php $manage = auth('admin')->user()->allows('billing.manage'); $N = \App\Support\Num::class; @endphp
<x-page-header :title="__('Recurring payments')" icon="rotate" :subtitle="__('The yearly renewal of every clinic: when it is due and how much')" />

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="rotate" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['yearly']) }}</div><div class="kpi-label">{{ __('Comes back every year') }}</div><div class="kpi-note">{{ $totals['clinics'] }} {{ __('Clinics') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $totals['overdue'] ? 'kpi-red' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="triangle-exclamation" /></span><div><div class="kpi-value">{{ $totals['overdue'] }}</div><div class="kpi-label">{{ __('Overdue') }}</div><div class="kpi-note" dir="ltr">${{ money($totals['overdue_amount']) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $totals['soon'] ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="calendar-days" /></span><div><div class="kpi-value">{{ $totals['soon'] }}</div><div class="kpi-label">{{ __('Due in 30 days') }}</div><div class="kpi-note" dir="ltr">${{ money($totals['soon_amount']) }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="chart-column" /></span><div><div class="kpi-value" dir="ltr">${{ money($N::div($totals['yearly'], '12')) }}</div><div class="kpi-label">{{ __('A month, on average') }}</div></div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="btn-group btn-group-sm no-print" role="group">
            @foreach (['overdue' => 'Overdue', '30' => 'In 30 days', '90' => 'In 90 days', 'all' => 'All'] as $key => $label)
                <a class="btn {{ $within === $key ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.renewals.index', ['within' => $key]) }}">{{ __($label) }}</a>
            @endforeach
        </div>
    </div>
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="rotate" /></div><h2 class="h3">{{ __('Nothing is due in this period') }}</h2></div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Renewal date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Branches') }}</th><th class="text-end">{{ __('Yearly amount') }}</th><th>{{ __('Invoice') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $t = $row['tenant']; @endphp
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $t->id]) }}">{{ $t->name }}</a><div class="small text-muted"><span dir="ltr">{{ $t->domain }}</span>@if ($t->seller) · {{ $t->seller->name }}@endif</div></td>
                            <td dir="ltr">{{ $row['date']?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                @if ($row['state'] === 'overdue')<span class="badge bg-danger-subtle text-danger">{{ __(':days days overdue', ['days' => abs($row['days'])]) }}</span>
                                @elseif ($row['state'] === 'soon')<span class="badge bg-warning-subtle text-warning">{{ __('in :days days', ['days' => $row['days']]) }}</span>
                                @elseif ($row['state'] === 'later')<span class="badge bg-success-subtle text-success">{{ __('in :days days', ['days' => $row['days']]) }}</span>
                                @else<span class="badge bg-secondary-subtle text-secondary">{{ __('Not invoiced') }}</span>@endif
                            </td>
                            <td>{{ $row['branches'] }}</td>
                            <td class="text-end fw-semibold" dir="ltr">${{ money($row['yearly']) }}</td>
                            <td>@if ($row['open'])<a href="{{ route('admin.invoices.show', ['id' => $row['open']->id]) }}">{{ $row['open']->number }}</a><div class="small text-danger"><span dir="ltr">${{ money($row['left']) }}</span> {{ __('not paid') }}</div>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-end no-print"><div class="act-group">
                                @if ($manage)
                                    @if ($row['open'])
                                        <a class="btn btn-sm btn-primary" href="{{ route('admin.payments.index', ['tenant' => $t->id, 'invoice' => $row['open']->id]) }}">{{ __('Record a payment') }}</a>
                                    @else
                                        @php $from = ($row['date'] ?? now())->copy()->addDay(); @endphp
                                        <form method="POST" action="{{ route('admin.invoices.store') }}" data-confirm="{{ __('Make the renewal invoice of :name?', ['name' => $t->name]) }}" data-confirm-ok="{{ __('app.actions.yes') }}" data-confirm-style="primary">@csrf
                                            <input type="hidden" name="tenant_id" value="{{ $t->id }}"><input type="hidden" name="from_date" value="{{ $from->toDateString() }}"><input type="hidden" name="to_date" value="{{ $from->copy()->addYear()->subDay()->toDateString() }}">
                                            <button class="btn btn-sm btn-light-secondary" type="submit"><x-icon name="receipt" class="me-1" /> {{ __('Make the invoice') }}</button></form>
                                    @endif
                                @endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
