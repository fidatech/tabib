@extends('layouts.central', ['title' => __('Dashboard')])

@section('content')
<x-page-header :title="__('Dashboard')" icon="gauge-high" :subtitle="__('How the service stands today')" />

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><a class="kpi kpi-blue" href="{{ route('admin.tenants.index') }}"><span class="kpi-icon"><x-icon name="house-medical" /></span><div><div class="kpi-value">{{ $counts['active'] }}</div><div class="kpi-label">{{ __('Active clinics') }}</div><div class="kpi-note">{{ $counts['suspended'] }} {{ __('suspended') }} · {{ $counts['branches'] }} {{ __('branches') }}</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-green" href="{{ route('admin.payments.index') }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($money['year']) }}</div><div class="kpi-label">{{ __('Received this year') }}</div><div class="kpi-note" dir="ltr">${{ money($money['month']) }} {{ __('this month') }}</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-amber" href="{{ route('admin.invoices.index', ['status' => 'unpaid']) }}"><span class="kpi-icon"><x-icon name="file-invoice-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($money['due']) }}</div><div class="kpi-label">{{ __('Still to be paid') }}</div><div class="kpi-note">{{ $money['unpaid'] }} {{ __('unpaid invoices') }}</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-red" href="{{ route('admin.tickets.index', ['status' => 'open']) }}"><span class="kpi-icon"><x-icon name="headset" /></span><div><div class="kpi-value">{{ $counts['tickets'] }}</div><div class="kpi-label">{{ __('Tickets waiting for us') }}</div><div class="kpi-note">{{ $licences->count() }} {{ __('licences to look at') }}</div></div></a></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Money received, month by month') }}</h2><span class="text-muted small">{{ __('US dollars') }}</span></div>
            <div class="card-body">
                @php $top = max(1, max($months)); @endphp
                <div class="bars" role="img" aria-label="{{ __('Money received, month by month') }}">
                    @foreach ($months as $month => $value)
                        <div class="bars-col" title="{{ $month }}: ${{ money($value) }}">
                            <span class="bars-value">{{ $value > 0 ? number_format($value) : '' }}</span>
                            <span class="bars-bar" style="height: {{ max(1, round($value / $top * 100)) }}%"></span>
                            <span class="bars-label" dir="ltr">{{ substr($month, 5) }}/{{ substr($month, 2, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Licences to look at') }}</h2><span class="text-muted small">{{ __('Reminder: :days days before', ['days' => $rules['remind_days']]) }}</span></div>
            <div class="card-body">
                @forelse ($licences->take(7) as $row)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                        <div><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $row['tenant']->id]) }}">{{ $row['tenant']->name }}</a><div class="small text-muted" dir="ltr">{{ $row['tenant']->licence_expires_on->format('Y-m-d') }}</div></div>
                        @if ($row['state'] === 'soon')<span class="badge bg-warning-subtle text-warning">{{ __(':days days left', ['days' => $row['days']]) }}</span>
                        @elseif ($row['state'] === 'grace')<span class="badge bg-danger-subtle text-danger">{{ __('Ended; locks in :days days', ['days' => $row['lock_in']]) }}</span>
                        @else<span class="badge bg-danger">{{ __('Ended :days days ago', ['days' => abs($row['days'])]) }}</span>@endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4"><x-icon name="circle-check" class="text-success me-1" /> {{ __('Every licence is in order.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Unpaid invoices') }}</h2><a class="small" href="{{ route('admin.invoices.index', ['status' => 'unpaid']) }}">{{ __('All') }}</a></div>
            <div class="card-body">
                @forelse ($unpaid as $invoice)
                    <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><div><a class="fw-semibold" href="{{ route('admin.payments.index', ['tenant' => $invoice->tenant_id, 'invoice' => $invoice->id]) }}">{{ $invoice->number }}</a><div class="small text-muted">{{ $invoice->tenant->name }}</div></div><strong dir="ltr">${{ money($invoice->total) }}</strong></div>
                @empty
                    <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Last payments') }}</h2><a class="small" href="{{ route('admin.payments.index') }}">{{ __('All') }}</a></div>
            <div class="card-body">
                @forelse ($payments as $payment)
                    <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><div><span class="fw-semibold">{{ $payment->tenant->name }}</span><div class="small text-muted" dir="ltr">{{ $payment->paid_on->format('Y-m-d') }} · {{ __(\App\Models\Central\Payment::METHODS[$payment->method] ?? $payment->method) }}</div></div><strong class="text-success" dir="ltr">${{ money($payment->amount) }}</strong></div>
                @empty
                    <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Tickets waiting for us') }}</h2><a class="small" href="{{ route('admin.tickets.index') }}">{{ __('All') }}</a></div>
            <div class="card-body">
                @forelse ($tickets as $ticket)
                    <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><div><a class="fw-semibold" href="{{ route('admin.tickets.show', ['id' => $ticket->id]) }}">{{ $ticket->subject }}</a><div class="small text-muted">{{ $ticket->tenant->name }} · {{ $ticket->last_message_at?->diffForHumans() }}</div></div>@if ($ticket->priority === 'urgent')<span class="badge bg-danger align-self-start">{{ __('Urgent') }}</span>@endif</div>
                @empty
                    <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
