@extends('layouts.central', ['title' => __('Payments')])

@section('content')
<x-page-header :title="__('Payments')" icon="money-bill-wave" :subtitle="__('Money received from the clinics, in US dollars')" />

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <form method="POST" action="{{ route('admin.payments.store') }}" class="card h-100 mb-0">
            @csrf
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Record a payment') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="p-tenant">{{ __('Clinic') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="p-tenant" name="tenant_id" required data-plain onchange="window.location = '{{ route('admin.payments.index') }}?tenant=' + this.value">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($tenants as $tenant)<option value="{{ $tenant->id }}" @selected($chosen?->id === $tenant->id)>{{ $tenant->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6"><label class="form-label" for="p-invoice">{{ __('For the invoice') }}</label>
                    <select class="form-select" id="p-invoice" name="invoice_id" data-plain>
                        <option value="">{{ __('On account (no invoice)') }}</option>
                        @foreach ($open as $row)<option value="{{ $row['invoice']->id }}" @selected(old('invoice_id', $invoice) == $row['invoice']->id) data-left="{{ $row['left'] }}">{{ $row['invoice']->number }} · ${{ money($row['left']) }} {{ __('left') }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3"><label class="form-label" for="p-amount">{{ __('Amount') }} <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control @error('amount') is-invalid @enderror" id="p-amount" name="amount" value="{{ old('amount', $open->firstWhere('invoice.id', $invoice)['left'] ?? '') }}" dir="ltr" required></div>@error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-6 col-md-3"><label class="form-label" for="p-method">{{ __('Paid by') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="p-method" name="method" required data-plain>@foreach (\App\Models\Central\Payment::METHODS as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ __($label) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label" for="p-date">{{ __('Date') }} <span class="text-danger">*</span></label><input type="date" class="form-control @error('paid_on') is-invalid @enderror" id="p-date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required></div>
                <div class="col-6 col-md-3"><label class="form-label" for="p-ref">{{ __('Slip or transaction number') }}</label><input type="text" class="form-control" id="p-ref" name="reference" value="{{ old('reference') }}" dir="ltr"></div>
                {{-- the reseller of this sale: his commission is worked out by itself, and the money may be in his hands --}}
                @if ($sellers->isNotEmpty())
                    @php $mine = old('seller_id', $chosen?->seller_id); @endphp
                    <div class="col-12 col-md-5"><label class="form-label" for="p-seller">{{ __('Reseller') }}</label>
                        <select class="form-select" id="p-seller" name="seller_id" data-plain>
                            <option value="">{{ __('No reseller (direct sale)') }}</option>
                            @foreach ($sellers as $seller)<option value="{{ $seller->id }}" @selected((string) $mine === (string) $seller->id)>{{ $seller->name }} · {{ $seller->commissionLabel() }}</option>@endforeach
                        </select></div>
                    <div class="col-6 col-md-4"><label class="form-label" for="p-holder">{{ __('The money was received by') }}</label>
                        <select class="form-select" id="p-holder" name="collected_by_seller" data-plain>
                            <option value="0" @selected(! old('collected_by_seller'))>{{ __('Us, directly') }}</option>
                            <option value="1" @selected((bool) old('collected_by_seller'))>{{ __('The reseller (he hands it over later)') }}</option>
                        </select></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="p-commission">{{ __('Commission') }}</label><div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control @error('commission') is-invalid @enderror" id="p-commission" name="commission" value="{{ old('commission') }}" dir="ltr" autocomplete="off" placeholder="{{ __('Automatic') }}"></div>@error('commission')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                @endif
                <div class="col-12"><label class="form-label" for="p-note">{{ __('Note') }}</label><input type="text" class="form-control" id="p-note" name="note" value="{{ old('note') }}"></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary" type="submit" @disabled(! $chosen)><x-icon name="check" class="me-1" /> {{ __('Save the payment') }}</button></div>
        </form>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ $chosen ? $chosen->name : __('Balance of a clinic') }}</h2></div>
            <div class="card-body">
                @if ($balance)
                    <dl class="facts">
                        <div><dt>{{ __('Invoiced') }}</dt><dd dir="ltr">${{ money($balance['invoiced']) }}</dd></div>
                        <div><dt>{{ __('Paid') }}</dt><dd dir="ltr" class="text-success">${{ money($balance['paid']) }}</dd></div>
                        <div><dt>{{ __('Still to be paid') }}</dt><dd dir="ltr" class="{{ \App\Support\Num::isPositive($balance['due']) ? 'text-danger' : '' }}">${{ money($balance['due']) }}</dd></div>
                        <div><dt>{{ __('Unpaid invoices') }}</dt><dd>{{ $open->count() }}</dd></div>
                    </dl>
                    <a class="btn btn-outline-soft w-100 mt-3" href="{{ route('admin.tenants.show', ['id' => $chosen->id]) }}">{{ __('Open the page of this clinic') }}</a>
                @else
                    <p class="text-muted mb-0">{{ __('Choose a clinic to see what it was invoiced, what it paid and what is left.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ $chosen ? __('Payments of :name', ['name' => $chosen->name]) : __('All payments') }}</h2>@if ($chosen)<a class="small" href="{{ route('admin.payments.index') }}">{{ __('All payments') }}</a>@endif</div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Paid by') }}</th><th>{{ __('Slip or transaction number') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Reseller') }}</th><th>{{ __('Recorded by') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="{{ $payment->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                        <td>{{ $payment->number }}</td><td dir="ltr">{{ $payment->paid_on->format('Y-m-d') }}</td><td><a href="{{ route('admin.tenants.show', ['id' => $payment->tenant_id]) }}">{{ $payment->tenant->name }}</a></td>
                        <td>{{ $payment->invoice?->number ?? __('On account') }}</td><td>{{ __(\App\Models\Central\Payment::METHODS[$payment->method] ?? $payment->method) }}</td><td dir="ltr">{{ $payment->reference }}</td>
                        <td class="text-end fw-semibold" dir="ltr">${{ money($payment->amount) }}</td>
                        <td>@if ($payment->seller)<a href="{{ route('admin.sellers.show', ['id' => $payment->seller_id]) }}">{{ $payment->seller->name }}</a><div class="small text-muted"><span dir="ltr">${{ money($payment->commission) }}</span> {{ __('commission') }}@if ($payment->collected_by_seller) · {{ __('with him') }}@endif</div>@endif</td>
                        <td>{{ $payment->admin?->name }}</td>
                        <td class="text-end text-nowrap no-print"><div class="act-group">
                            @if ($payment->isCancelled())<span class="badge bg-secondary-subtle text-secondary">{{ __('Cancelled') }}</span>@endif
                            <x-act kind="receipt" :href="route('admin.payments.receipt', ['id' => $payment->id])" blank />
                            @if (! $payment->isCancelled() && auth('admin')->user()->allows('billing.manage'))
                                <form method="POST" action="{{ route('admin.payments.destroy', ['id' => $payment->id]) }}" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">@csrf @method('DELETE')<x-act kind="cancel" /></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $payments->links('partials.pagination') }}
    </div>
</div>
@endsection
