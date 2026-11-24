@extends('layouts.app', ['title' => __('Invoices')])

@section('content')
<x-page-header :title="__('Invoices')" icon="file-invoice-dollar" :subtitle="$branch->name">
    <a class="btn btn-outline-soft" href="{{ route('billing.daily') }}"><x-icon name="cash-register" class="me-1" /> {{ __('Cash of the day') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-12 col-md">
                <label class="form-label small mb-1" for="inv-q">{{ __('Search') }}</label>
                <input type="search" class="form-control" id="inv-q" name="q" value="{{ $term }}" placeholder="{{ __('Invoice number, patient name, number or phone') }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="inv-status">{{ __('Status') }}</label>
                <select class="form-select" id="inv-status" name="status">
                    <option value="">{{ __('All') }}</option>
                    <option value="owed" @selected($status === 'owed')>{{ __('Not fully paid') }}</option>
                    @foreach (\App\Models\Invoice::STATUSES as $key => $s)<option value="{{ $key }}" @selected($status === $key)>{{ __($s[0]) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="inv-from">{{ __('From') }}</label><x-date name="from" :value="$from" id="inv-from" /></div>
            <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="inv-to">{{ __('To') }}</label><x-date name="to" :value="$to" id="inv-to" /></div>
            <div class="col-6 col-md-auto"><button class="btn btn-light-secondary w-100" type="submit"><x-icon name="magnifying-glass" class="me-1" /> {{ __('Search') }}</button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="file-invoice-dollar" /></div>
                <h2 class="h3">{{ $term !== '' || $status !== '' || $from || $to ? __('Nothing found') : __('No invoice yet') }}</h2>
                <p>{{ __('Invoices are made from the queue (the "Bill" button) or from the chart of a patient.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Patient') }}</th><th>{{ __('Doctor') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Owed') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $inv)
                            <tr>
                                <td><a href="{{ route('invoices.show', ['id' => $inv->id]) }}" dir="ltr">{{ $inv->number }}</a></td>
                                <td class="text-nowrap">{{ fmt_date($inv->issued_on) }}</td>
                                <td>{{ $inv->patient?->name }} <span class="small text-muted">{{ $inv->patient?->number }}</span></td>
                                <td>{{ $inv->doctor?->name() }}</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($inv->total) }}</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($inv->paid) }}</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($inv->balance()) }}</td>
                                <td><span class="badge {{ $inv->statusClass() }}">{{ $inv->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
