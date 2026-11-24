@extends('layouts.central', ['title' => __('Invoices')])

@section('content')
<x-page-header :title="__('Invoices')" icon="receipt" />

<div class="row g-3 mb-3">
    <div class="col-12">
        <form method="POST" action="{{ route('admin.invoices.store') }}" class="card mb-0">
            @csrf
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('New invoice') }}</h2>
                <span class="text-muted small" dir="ltr">${{ money($prices['main_first']) }} / ${{ money($prices['main_renewal']) }} · ${{ money($prices['branch_first']) }} / ${{ money($prices['branch_renewal']) }}@if (auth('admin')->user()->allows('settings')) · <a href="{{ route('admin.settings.edit') }}#prices">{{ __('Prices') }}</a>@endif</span></div>
            <div class="card-body row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="i-tenant">{{ __('Clinic') }}</label>
                    {{-- choosing a clinic shows what its invoice will hold --}}
                    <select class="form-select" id="i-tenant" name="tenant_id" required data-plain onchange="window.location = '{{ route('admin.invoices.index') }}?tenant=' + this.value">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected($chosen?->id === $tenant->id)>{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3"><label class="form-label" for="i-from">{{ __('From') }}</label><input type="date" class="form-control @error('from_date') is-invalid @enderror" id="i-from" name="from_date" value="{{ old('from_date', now()->toDateString()) }}" required></div>
                <div class="col-6 col-md-3"><label class="form-label" for="i-to">{{ __('To') }}</label><input type="date" class="form-control @error('to_date') is-invalid @enderror" id="i-to" name="to_date" value="{{ old('to_date', now()->addYear()->subDay()->toDateString()) }}" required></div>
                @if ($quote)
                    <div class="col-12">
                        <input type="hidden" name="only_new" value="{{ $onlyNew ? 1 : 0 }}">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <a class="btn btn-sm {{ $onlyNew ? 'btn-light-secondary' : 'btn-primary' }}" href="{{ route('admin.invoices.index', ['tenant' => $chosen->id]) }}">{{ $quote['first'] ? __('First year') : __('Yearly renewal') }}</a>
                            <a class="btn btn-sm {{ $onlyNew ? 'btn-primary' : 'btn-light-secondary' }}" href="{{ route('admin.invoices.index', ['tenant' => $chosen->id, 'only_new' => 1]) }}">{{ __('Only the branches added since the last invoice') }}</a>
                        </div>
                        <table class="table table-sm w-100 mb-0 keep-table">
                            <tbody>
                                @forelse ($quote['lines'] as $line)
                                    <tr><td>{{ __($line['label']) }}</td><td class="text-end" dir="ltr">{{ $line['quantity'] }} × ${{ money($line['price']) }}</td><td class="text-end fw-semibold" dir="ltr">${{ money($line['amount']) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">{{ __('There is nothing to invoice: this clinic has no new branch.') }}</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot><tr><th colspan="2">{{ __('Total') }}</th><th class="text-end" dir="ltr">${{ money($quote['total']) }}</th></tr></tfoot>
                        </table>
                    </div>
                @else
                    <div class="col-12 text-muted small">{{ __('Main branch: the first-year price once, then hosting every year. Every further branch the same, at its own two prices.') }}</div>
                @endif
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary" type="submit" @disabled($quote && ! $quote['lines'])><x-icon name="receipt" class="me-1" /> {{ __('Make invoice') }}</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h2 class="h5 card-title mb-0">{{ __('Invoices') }}</h2>
        <div class="seg">
            <a href="{{ route('admin.invoices.index') }}" class="{{ request('status') ? '' : 'active' }}">{{ __('All') }}</a>
            @foreach (\App\Models\Central\Invoice::STATUSES as $key => $label)<a href="{{ route('admin.invoices.index', ['status' => $key]) }}" class="{{ request('status') === $key ? 'active' : '' }}">{{ __($label) }}</a>@endforeach
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th colspan="2">{{ __('What it is for') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr class="{{ $invoice->status === 'cancelled' ? 'text-decoration-line-through text-muted' : '' }}">
                        <td>{{ $invoice->number }}</td><td><a href="{{ route('admin.tenants.show', ['id' => $invoice->tenant_id]) }}">{{ $invoice->tenant->name }}</a></td><td>{{ $invoice->from_date->format('Y-m-d') }}</td><td>{{ $invoice->to_date->format('Y-m-d') }}</td>
                        <td colspan="2">@foreach ($invoice->lines ?? [] as $line)<div class="small text-nowrap">{{ __($line['label']) }}: <span dir="ltr">{{ $line['quantity'] }} × ${{ money($line['price']) }}</span></div>@endforeach
                            @unless ($invoice->lines)<span dir="ltr">{{ money($invoice->main_branch_price) }} + {{ $invoice->branches }} × {{ money($invoice->branch_price) }}</span>@endunless</td><td class="text-end fw-semibold" dir="ltr">${{ money($invoice->total) }}</td>
                        <td><span class="badge {{ ['paid' => 'bg-success-subtle text-success', 'unpaid' => 'bg-warning-subtle text-warning'][$invoice->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __(\App\Models\Central\Invoice::STATUSES[$invoice->status]) }}</span>@if ($invoice->paid_on)<div class="small text-muted" dir="ltr">{{ $invoice->paid_on->format('Y-m-d') }}</div>@endif</td>
                        <td class="text-end text-nowrap no-print">
                            <div class="act-group">
                                <x-act kind="receipt" :href="route('admin.invoices.show', ['id' => $invoice->id])" :label="__('Print')" blank />
                                @if (auth('admin')->user()->allows('billing.manage'))
                                    @if ($invoice->status === 'unpaid')
                                        <a class="btn btn-sm btn-success" href="{{ route('admin.payments.index', ['tenant' => $invoice->tenant_id, 'invoice' => $invoice->id]) }}"><x-icon name="money-bill-wave" class="me-1" /> {{ __('Record a payment') }}</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.invoices.status', ['id' => $invoice->id]) }}" class="d-inline-flex gap-1">
                                        @csrf
                                        @if ($invoice->status === 'unpaid')
                                            <button class="btn btn-sm btn-light-danger" name="status" value="cancelled" type="submit">{{ __('Cancel') }}</button>
                                        @elseif ($invoice->status === 'paid')
                                            <button class="btn btn-sm btn-light-secondary" name="status" value="unpaid" type="submit">{{ __('Not paid') }}</button>
                                        @endif
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $invoices->links('partials.pagination') }}
    </div>
</div>
@endsection
