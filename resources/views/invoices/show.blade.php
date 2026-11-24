@extends('layouts.app', ['title' => $invoice->number.' · '.$patient->name])

@php $cur = setting('currency'); @endphp

@section('content')
<x-page-header :title="__('Invoice').' '.$invoice->number" icon="file-invoice-dollar"
    :crumbs="[[__('Invoices'), route('invoices.index')], [$invoice->number]]"
    :subtitle="$patient->name.' · '.$patient->number.' · '.fmt_date($invoice->issued_on).($invoice->doctor ? ' · '.$invoice->doctor->name() : '')">
    <span class="badge {{ $invoice->statusClass() }} fs-6 align-self-center">{{ $invoice->statusLabel() }}</span>
    <a class="btn btn-outline-soft" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'billing']) }}"><x-icon name="hospital-user" class="me-1" /> {{ __('Chart') }}</a>
    <a class="btn btn-outline-soft" href="{{ route('invoices.print', ['id' => $invoice->id]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
</x-page-header>

@if (session('receipt'))
    <div class="alert alert-info d-flex align-items-center gap-2 flex-wrap"><x-icon name="receipt" /> {{ __('Give the patient the receipt.') }}
        <a class="btn btn-sm btn-primary ms-auto" href="{{ route('payments.print', ['payment' => session('receipt')]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print the receipt') }}</a></div>
@endif
@if ($invoice->isVoid())
    <div class="alert alert-secondary"><x-icon name="ban" class="me-1" /> {{ __('Voided by :name on :date: :reason', ['name' => $invoice->voider?->name, 'date' => fmt_date($invoice->voided_at), 'reason' => $invoice->void_reason]) }}</div>
@endif
@if (\App\Support\Num::cmp($owedElsewhere, '0') > 0)
    <div class="alert alert-warning"><x-icon name="triangle-exclamation" class="me-1" /> {{ __('The patient also owes :amount on other invoices.', ['amount' => money($owedElsewhere, $cur)]) }}
        <a href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'billing']) }}">{{ __('See them') }}</a></div>
@endif

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('What is charged') }}</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ __('Description') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Amount') }}</th>@if ($editable)<th class="no-print"><span class="visually-hidden">{{ __('Actions') }}</span></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-end" dir="ltr">{{ \App\Support\Num::format($item->quantity, 2, true) }}</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($item->unit_price) }}</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($item->amount) }}</td>
                                @if ($editable)
                                    <td class="text-end no-print"><form method="POST" action="{{ route('invoices.items.destroy', ['id' => $invoice->id, 'item' => $item->id]) }}">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-icon name="trash" /></button></form></td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">{{ __('Nothing charged yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">{{ __('Subtotal') }}</th><td class="text-end text-nowrap" dir="ltr">{{ money($invoice->subtotal) }}</td>@if ($editable)<td></td>@endif</tr>
                        @if (! \App\Support\Num::isZero($invoice->discount))
                            <tr><th colspan="3" class="text-end">{{ __('Discount') }} <span class="small fw-normal text-muted">{{ $invoice->discount_reason }}</span></th><td class="text-end text-nowrap" dir="ltr">−{{ money($invoice->discount) }}</td>@if ($editable)<td></td>@endif</tr>
                        @endif
                        <tr class="fs-5"><th colspan="3" class="text-end">{{ __('Total') }}</th><th class="text-end text-nowrap" dir="ltr">{{ money($invoice->total, $cur) }}</th>@if ($editable)<td></td>@endif</tr>
                        <tr><th colspan="3" class="text-end">{{ __('Paid') }}</th><td class="text-end text-nowrap" dir="ltr">{{ money($invoice->paid, $cur) }}</td>@if ($editable)<td></td>@endif</tr>
                        <tr class="fw-bold {{ \App\Support\Num::cmp($invoice->balance(), '0') > 0 ? 'text-danger' : 'text-success' }}"><th colspan="3" class="text-end">{{ __('Owed') }}</th><td class="text-end text-nowrap" dir="ltr">{{ money($invoice->balance(), $cur) }}</td>@if ($editable)<td></td>@endif</tr>
                    </tfoot>
                </table>
            </div>
            @if ($editable)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('invoices.items.store', ['id' => $invoice->id]) }}" class="row g-2 align-items-end">@csrf
                        <div class="col-12 col-md-5"><label class="form-label small" for="it-service">{{ __('From the price list') }}</label>
                            <select class="form-select" id="it-service" name="service_id" data-price-pick>
                                <option value="">{{ __('Something else (write it)') }}</option>
                                @foreach ($services->groupBy('category') as $cat => $list)
                                    <optgroup label="{{ __(\App\Models\Service::CATEGORIES[$cat] ?? $cat) }}">
                                        @foreach ($list as $s)<option value="{{ $s->id }}" data-price="{{ $s->price }}">{{ $s->label() }} — {{ money($s->price) }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select></div>
                        <div class="col-12 col-md-7"><label class="form-label small" for="it-desc">{{ __('Description') }}</label>
                            <input type="text" class="form-control" id="it-desc" name="description" maxlength="200" placeholder="{{ __('Empty: the name from the price list') }}"></div>
                        <div class="col-4 col-md-2"><label class="form-label small" for="it-qty">{{ __('Quantity') }}</label>
                            <input type="number" class="form-control" id="it-qty" name="quantity" value="1" min="0.01" step="any" dir="ltr"></div>
                        <div class="col-8 col-md-3"><label class="form-label small" for="it-price">{{ __('Price') }}</label>
                            <input type="number" class="form-control" id="it-price" name="unit_price" min="0" step="any" dir="ltr" placeholder="{{ __('From the price list') }}"></div>
                        <div class="col-12 col-md-auto ms-auto"><button class="btn btn-primary w-100" type="submit"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button></div>
                    </form>
                </div>
            @endif
        </div>

        {{-- money taken and given back --}}
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Payments') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($invoice->payments as $p)
                    <div class="list-group-item d-flex gap-2 align-items-center flex-wrap">
                        <span class="fw-semibold" dir="ltr">{{ $p->number }}</span>
                        <span class="small text-muted">{{ fmt_date($p->received_at) }} {{ $p->received_at->format('H:i') }} · {{ $p->methodLabel() }}{{ $p->reference ? ' · '.$p->reference : '' }} · {{ $p->receiver?->name }}{{ $p->note ? ' · '.$p->note : '' }}</span>
                        <span class="ms-auto fw-semibold {{ $p->isRefund() ? 'text-danger' : 'text-success' }}" dir="ltr">{{ money($p->amount, $cur) }}</span>
                        <a class="btn btn-sm btn-outline-soft" href="{{ route('payments.print', ['payment' => $p->id]) }}" target="_blank" rel="noopener" aria-label="{{ __('Print the receipt') }}" title="{{ __('Print the receipt') }}"><x-icon name="print" /></a>
                    </div>
                @empty
                    <div class="list-group-item text-muted">{{ __('Nothing paid yet.') }}</div>
                @endforelse
            </div>
            @if ($canManage && ! $invoice->isVoid() && \App\Support\Num::cmp($invoice->balance(), '0') > 0)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('invoices.pay', ['id' => $invoice->id]) }}" class="row g-2 align-items-end">@csrf
                        <div class="col-6 col-md-3"><label class="form-label small" for="pay-amount">{{ __('Amount') }}</label>
                            <input type="number" class="form-control fw-bold" id="pay-amount" name="amount" value="{{ \App\Support\Num::round($invoice->balance(), 2) }}" min="0.01" max="{{ $invoice->balance() }}" step="0.01" required dir="ltr"></div>
                        <div class="col-6 col-md-3"><label class="form-label small" for="pay-method">{{ __('Paid by') }}</label>
                            <select class="form-select" id="pay-method" name="method">@foreach (\App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></div>
                        <div class="col-12 col-md-3"><label class="form-label small" for="pay-ref">{{ __('Reference') }}</label>
                            <input type="text" class="form-control" id="pay-ref" name="reference" maxlength="80" placeholder="{{ __('Slip or transfer number') }}"></div>
                        <div class="col-12 col-md-3"><button class="btn btn-success w-100" type="submit"><x-icon name="money-bill-wave" class="me-1" /> {{ __('Take the money') }}</button></div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-xl-4">
        @if ($canManage && ! $invoice->isVoid())
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ $editable ? __('Discount and note') : __('Note') }}</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('invoices.update', ['id' => $invoice->id]) }}" class="row g-2">@csrf @method('PUT')
                        @if ($editable)
                            <div class="col-5"><label class="form-label small" for="inv-discount">{{ __('Discount') }}</label>
                                <input type="number" class="form-control" id="inv-discount" name="discount" value="{{ \App\Support\Num::isZero($invoice->discount) ? '' : $invoice->discount }}" min="0" step="0.01" dir="ltr"></div>
                            <div class="col-7"><label class="form-label small" for="inv-dreason">{{ __('Why') }}</label>
                                <input type="text" class="form-control" id="inv-dreason" name="discount_reason" value="{{ $invoice->discount_reason }}" maxlength="255" placeholder="{{ __('Poor family, staff ...') }}"></div>
                        @endif
                        <div class="col-12"><label class="form-label small" for="inv-note">{{ __('Note on the invoice') }}</label>
                            <textarea class="form-control" id="inv-note" name="note" rows="2" maxlength="500">{{ $invoice->note }}</textarea></div>
                        <div class="col-12 text-end"><button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Save') }}</button></div>
                    </form>
                </div>
            </div>

            @if (\App\Support\Num::cmp($invoice->paid, '0') > 0)
                <div class="card mb-3">
                    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Give money back') }}</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('invoices.refund', ['id' => $invoice->id]) }}" class="row g-2" data-confirm="{{ __('Give this money back?') }}" data-confirm-ok="{{ __('Give back') }}">@csrf
                            <div class="col-6"><label class="form-label small" for="rf-amount">{{ __('Amount') }}</label>
                                <input type="number" class="form-control" id="rf-amount" name="amount" min="0.01" max="{{ $invoice->paid }}" step="0.01" required dir="ltr"></div>
                            <div class="col-6"><label class="form-label small" for="rf-method">{{ __('Given back by') }}</label>
                                <select class="form-select" id="rf-method" name="method">@foreach (\App\Models\Payment::METHODS as $k => $m)<option value="{{ $k }}">{{ __($m) }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label small" for="rf-reason">{{ __('Why') }}</label>
                                <input type="text" class="form-control" id="rf-reason" name="reason" required maxlength="255"></div>
                            <div class="col-12"><button class="btn btn-outline-danger w-100" type="submit"><x-icon name="rotate-left" class="me-1" /> {{ __('Give back') }}</button></div>
                        </form>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="POST" action="{{ route('invoices.void', ['id' => $invoice->id]) }}" class="row g-2" data-confirm="{{ __('Void this invoice? It stays in the books, marked void.') }}" data-confirm-ok="{{ __('Void') }}">@csrf
                            <div class="col-12"><label class="form-label small" for="void-reason">{{ __('Void this invoice (it stays, marked void)') }}</label>
                                <input type="text" class="form-control" id="void-reason" name="reason" required maxlength="255" placeholder="{{ __('Why') }}"></div>
                            <div class="col-12"><button class="btn btn-outline-danger w-100" type="submit"><x-icon name="ban" class="me-1" /> {{ __('Void the invoice') }}</button></div>
                        </form>
                    </div>
                </div>
            @endif
        @elseif ($invoice->note)
            <div class="card mb-3"><div class="card-body small">{{ $invoice->note }}</div></div>
        @endif
        <p class="small text-muted">{{ __('Made by :name on :date.', ['name' => $invoice->creator?->name, 'date' => fmt_date($invoice->created_at).' '.$invoice->created_at->format('H:i')]) }}
            @if ($invoice->appointment)<br>{{ __('For the appointment of :date', ['date' => fmt_date($invoice->appointment->starts_at).' '.$invoice->appointment->starts_at->format('H:i')]) }}@endif</p>
    </div>
</div>

@if ($editable)
<script>
(function () {
    var pick = document.querySelector('[data-price-pick]'), price = document.getElementById('it-price');
    if (!pick || !price) return;
    pick.addEventListener('change', function () { var o = pick.options[pick.selectedIndex]; price.value = o && o.getAttribute('data-price') ? o.getAttribute('data-price') : ''; });
})();
</script>
@endif
@endsection
