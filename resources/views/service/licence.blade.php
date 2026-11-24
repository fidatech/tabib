@extends('layouts.app', ['title' => __('Subscription')])

@section('content')
<x-page-header :title="__('Subscription')" icon="file-invoice-dollar" :subtitle="__('Your service from :company', ['company' => $company['company_name']])">
    <a class="btn btn-primary" href="{{ route('service.tickets') }}"><x-icon name="headset" class="me-1" /> {{ __('Support') }}</a>
</x-page-header>

@if ($tenant->isPending())
    {{-- registered on the website, not approved yet: this is the only page that opens --}}
    <div class="service-strip is-warning mb-3"><x-icon name="hourglass-half" /><div><strong>{{ __('Your registration is waiting for approval.') }}</strong>
        <div>{{ __('Until it is approved only this page and Support open. To start sooner, contact :company.', ['company' => $company['company_name']]) }}
            <span dir="ltr">{{ implode(' · ', array_filter([$company['company_phone'], $company['company_email']])) }}</span></div></div></div>
@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="kpi {{ ['ok' => 'kpi-green', 'soon' => 'kpi-amber'][$licence['state']] ?? 'kpi-red' }}"><span class="kpi-icon"><x-icon name="calendar-days" /></span><div><div class="kpi-value">{{ $tenant->licence_expires_on ? fmt_date($tenant->licence_expires_on->toDateString()) : '—' }}</div><div class="kpi-label">{{ __('Licence until') }}</div><div class="kpi-note">@if ($licence['days'] === null){{ __('No date') }}@elseif ($licence['days'] >= 0){{ __(':days days left', ['days' => $licence['days']]) }}@else{{ __('Ended :days days ago', ['days' => abs($licence['days'])]) }}@endif</div></div></div></div>
    <div class="col-6 col-md-4"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="receipt" /></span><div><div class="kpi-value" dir="ltr">${{ money($balance['invoiced']) }}</div><div class="kpi-label">{{ __('Invoiced') }}</div><div class="kpi-note" dir="ltr">${{ money($balance['paid']) }} {{ __('paid') }}</div></div></div></div>
    <div class="col-6 col-md-4"><div class="kpi {{ \App\Support\Num::isPositive($balance['due']) ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($balance['due']) }}</div><div class="kpi-label">{{ __('Still to be paid') }}</div></div></div></div>
</div>

{{-- branch by branch: which is paid, until when, and which is due --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 card-title mb-0">{{ __('Subscription of each branch') }}</h2>
        <span class="small text-muted">{{ $subscription['paid'] }} / {{ $subscription['units'] }} {{ __('paid') }}@if ($subscription['due']) · <span class="text-danger fw-semibold">{{ $subscription['due'] }} {{ __('due') }}</span>@endif · <span dir="ltr">${{ money($subscription['yearly']) }}</span> {{ __('a year from the second year') }}</span></div>
    <div class="card-body">@include('central.partials.subscription-rows', ['rows' => $subscription['rows'], 'invoiceRoute' => 'service.invoice', 'invoiceBlank' => true])</div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Invoices') }}</h2></div><div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr><td>{{ $invoice->number }}</td><td>{{ fmt_date($invoice->from_date->toDateString()) }}</td><td>{{ fmt_date($invoice->to_date->toDateString()) }}</td><td class="text-end" dir="ltr">${{ money($invoice->total) }}</td>
                            <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ __(\App\Models\Central\Invoice::STATUSES[$invoice->status]) }}</span></td>
                            <td class="text-end no-print"><div class="act-group"><x-act kind="receipt" :href="route('service.invoice', ['id' => $invoice->id])" :label="__('Print')" blank /></div></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Payments') }}</h2></div><div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Paid by') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr><td>{{ $payment->number }}</td><td>{{ fmt_date($payment->paid_on->toDateString()) }}</td><td>{{ __(\App\Models\Central\Payment::METHODS[$payment->method] ?? $payment->method) }}</td><td class="text-end" dir="ltr">${{ money($payment->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($company['invoice_note'])<div class="service-strip is-info mt-3 mb-0"><x-icon name="circle-info" /><div style="white-space:pre-line">{{ $company['invoice_note'] }}</div></div>@endif
        </div></div>
    </div>
</div>
@endsection
