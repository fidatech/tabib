@extends('layouts.app', ['title' => __('Cash of the day')])

@php $cur = setting('currency'); @endphp

@section('content')
<x-page-header :title="__('Cash of the day')" icon="cash-register" :subtitle="$branch->name.' · '.fmt_date($day)">
    <button type="button" class="btn btn-outline-soft no-print" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    <a class="btn btn-outline-soft no-print" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="file-excel" class="me-1" /> Excel</a>
</x-page-header>

<form method="GET" class="row g-2 mb-3 align-items-end no-print">
    <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="cash-date">{{ __('Day') }}</label><x-date name="date" :value="$day->toDateString()" id="cash-date" /></div>
    @if ($branches->count() > 1)
        <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="cash-branch">{{ __('Branch') }}</label>
            <select class="form-select" id="cash-branch" name="branch">@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach</select></div>
    @endif
    <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
</form>

<div class="row g-3 mb-3">
    @foreach ([['Taken', $taken, 'money-bill-wave', 'success'], ['Given back', $returned, 'rotate-left', 'danger'], ['In the drawer', $net, 'cash-register', 'primary'], ['Still owed on the day\'s invoices', $owed, 'file-invoice-dollar', 'warning']] as [$label, $amount, $icon, $tone])
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted"><x-icon :name="$icon" class="text-{{ $tone }} me-1" /> {{ __($label) }}</div>
                <div class="fs-4 fw-bold" dir="ltr">{{ money($amount, $cur) }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By way of paying') }}</h2></div>
            <table class="table mb-0">
                <tbody>
                    @forelse ($byMethod as $method => $amount)
                        <tr><td>{{ __(\App\Models\Payment::METHODS[$method] ?? $method) }}</td><td class="text-end" dir="ltr">{{ money($amount, $cur) }}</td></tr>
                    @empty
                        <tr><td class="text-muted">{{ __('No money taken on this day.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By who took it') }}</h2></div>
            <table class="table mb-0">
                <tbody>
                    @forelse ($byUser as $row)
                        <tr><td>{{ $row['name'] }} <span class="small text-muted">({{ $row['count'] }})</span></td><td class="text-end" dir="ltr">{{ money($row['amount'], $cur) }}</td></tr>
                    @empty
                        <tr><td class="text-muted">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Receipts and refunds') }}</h2></div>
    @if ($payments->isEmpty())
        <div class="card-body text-muted">{{ __('No money taken on this day.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Time') }}</th><th>{{ __('Patient') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Paid by') }}</th><th>{{ __('Taken by') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($payments as $p)
                        <tr>
                            <td dir="ltr">{{ $p->number }}</td>
                            <td dir="ltr">{{ $p->received_at->format('H:i') }}</td>
                            <td>{{ $p->patient?->name }}</td>
                            <td><a href="{{ route('invoices.show', ['id' => $p->invoice_id]) }}" dir="ltr">{{ $p->invoice?->number }}</a></td>
                            <td>{{ $p->methodLabel() }}{{ $p->note ? ' · '.$p->note : '' }}</td>
                            <td>{{ $p->receiver?->name }}</td>
                            <td class="text-end {{ $p->isRefund() ? 'text-danger' : '' }}" dir="ltr">{{ money($p->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th colspan="6" class="text-end">{{ __('In the drawer') }}</th><th class="text-end" dir="ltr">{{ money($net) }}</th></tr></tfoot>
            </table>
        </div>
    @endif
</div>

<p class="small text-muted">{{ __(':count invoices made on this day for :total (discounts :discounts); :voided voided.', ['count' => $invoiceCount, 'total' => money($invoiced, $cur), 'discounts' => money($discounts, $cur), 'voided' => $voided]) }}</p>
@endsection
