@extends('layouts.reseller', ['title' => __('Payments')])

{{-- The payments of his clinics, and the money he took himself: reported here, counted when the administrator confirms. --}}
@section('content')
@php $C = \App\Models\Central\SellerClaim::class; @endphp
<x-page-header :title="__('Payments')" icon="money-bill-wave" :subtitle="__('What your clinics paid, and the money you took from them')" />

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-5">
        <form method="POST" action="{{ route('reseller.report') }}" class="card h-100 mb-0">
            @csrf
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Report a payment') }}</h2></div>
            <div class="card-body row g-3">
                @if ($clients->isEmpty())
                    <div class="col-12 text-muted">{{ __('You have no clinic yet.') }}</div>
                @else
                    <div class="col-12"><label class="form-label" for="r-tenant">{{ __('Clinic') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('tenant_id') is-invalid @enderror" id="r-tenant" name="tenant_id" required data-plain>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($clients as $t)<option value="{{ $t->id }}" @selected((string) old('tenant_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
                        </select></div>
                    <div class="col-12"><label class="form-label" for="r-invoice">{{ __('For the invoice') }}</label>
                        {{-- only the invoices of the chosen clinic are offered --}}
                        <select class="form-select @error('invoice_id') is-invalid @enderror" id="r-invoice" name="invoice_id" data-plain>
                            <option value="" data-tenant="">{{ __('On account (no invoice)') }}</option>
                            @foreach ($open as $row)<option value="{{ $row['invoice']->id }}" data-tenant="{{ $row['invoice']->tenant_id }}" data-left="{{ $row['left'] }}" @selected((string) old('invoice_id') === (string) $row['invoice']->id)>{{ $row['invoice']->number }} · {{ $row['invoice']->tenant->name }} · ${{ money($row['left']) }} {{ __('left') }}</option>@endforeach
                        </select>@error('invoice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-6"><label class="form-label" for="r-amount">{{ __('Amount') }} <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control @error('amount') is-invalid @enderror" id="r-amount" name="amount" value="{{ old('amount') }}" required dir="ltr" autocomplete="off"></div>@error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="col-6"><label class="form-label" for="r-method">{{ __('Paid by') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="r-method" name="method" required data-plain>@foreach (\App\Models\Central\Payment::METHODS as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ __($label) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="r-date">{{ __('Date') }} <span class="text-danger">*</span></label><input type="date" class="form-control @error('paid_on') is-invalid @enderror" id="r-date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required></div>
                    <div class="col-6"><label class="form-label" for="r-ref">{{ __('Slip or transaction number') }}</label><input type="text" class="form-control" id="r-ref" name="reference" value="{{ old('reference') }}" dir="ltr"></div>
                    <div class="col-12"><label class="form-label" for="r-note">{{ __('Note') }}</label><input type="text" class="form-control" id="r-note" name="note" value="{{ old('note') }}" maxlength="500"></div>
                    <div class="col-12 small text-muted">{{ __('The administrator confirms it. Then it counts as paid by the clinic, you owe the amount to the company, and your commission is added.') }}</div>
                @endif
            </div>
            @if ($clients->isNotEmpty())<div class="card-footer text-end"><button class="btn btn-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send the report') }}</button></div>@endif
        </form>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('My reports') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Clinic') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th class="no-print"></th></tr></thead>
                    <tbody>
                        @forelse ($claims as $claim)
                            <tr>
                                <td dir="ltr" class="text-nowrap">{{ $claim->paid_on->format('Y-m-d') }}</td>
                                <td>{{ $claim->tenant?->name }}<div class="small text-muted">{{ $claim->invoice?->number ?? __('On account') }}</div></td>
                                <td class="text-end fw-semibold" dir="ltr">${{ money($claim->amount) }}</td>
                                <td><span class="badge bg-{{ $C::TONES[$claim->status] }}-subtle text-{{ $C::TONES[$claim->status] }}">{{ __($C::STATUSES[$claim->status]) }}</span>
                                    @if ($claim->status === 'rejected')<div class="small text-muted">{{ $claim->reason }}</div>@elseif ($claim->payment)<div class="small text-muted">{{ $claim->payment->number }}</div>@endif</td>
                                <td class="text-end no-print">@if ($claim->isPending())<form method="POST" action="{{ route('reseller.withdraw', ['id' => $claim->id]) }}" data-confirm="{{ __('Take this report back?') }}" data-confirm-ok="{{ __('app.actions.yes') }}">@csrf @method('DELETE')<x-act kind="cancel" :label="__('Take back')" /></form>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Payments of my clinics') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100 align-middle">
            <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('The money was received by') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Commission') }}</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr class="{{ $p->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                        <td>{{ $p->number }}</td><td dir="ltr" class="text-nowrap">{{ $p->paid_on->format('Y-m-d') }}</td><td>{{ $p->tenant?->name }}</td><td>{{ $p->invoice?->number ?? __('On account') }}</td>
                        <td>{{ $p->collected_by_seller ? __('Me') : __('The company') }}</td>
                        <td class="text-end fw-semibold" dir="ltr">${{ money($p->amount) }}</td><td class="text-end text-success fw-semibold" dir="ltr">${{ money($p->commission) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $payments->links() }}
    </div>
</div>
{{-- choosing a clinic leaves only its invoices; choosing an invoice fills what is left of it --}}
<script>
(function () {
    var tenant = document.getElementById('r-tenant'), invoice = document.getElementById('r-invoice'), amount = document.getElementById('r-amount');
    if (!tenant || !invoice) return;
    var sync = function () {
        Array.prototype.forEach.call(invoice.options, function (o) { var t = o.getAttribute('data-tenant'); o.hidden = o.disabled = t !== '' && t !== tenant.value; });
        if (invoice.selectedOptions[0] && invoice.selectedOptions[0].disabled) invoice.value = '';
    };
    tenant.addEventListener('change', sync);
    invoice.addEventListener('change', function () { var o = invoice.selectedOptions[0], left = o && o.getAttribute('data-left'); if (left && !amount.value) amount.value = left; });
    sync();
})();
</script>
@endsection
