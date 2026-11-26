@extends('layouts.app', ['title' => __('Statement of :name', ['name' => $doctor->name()])])

@php $cur = setting('currency'); @endphp

@section('content')
<x-page-header :title="__('Statement of :name', ['name' => $doctor->name()])" icon="file-invoice-dollar" :subtitle="\App\Services\Commission::describe($doctor)"
    :crumbs="[[__('Doctor commission'), route('commission.index', ['month' => $month])], [$doctor->name()]]">
    <button type="button" class="btn btn-outline-soft no-print" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

@include('expenses._month', ['route' => 'commission.statement', 'params' => ['doctor' => $doctor->id]])

<div class="row g-3 mb-3">
    @foreach ([['Money taken', $s['collected']], ['Patients seen', $s['visits']], ['Due', $s['due']], ['Paid', $s['paid']], ['Left to pay', $s['left']]] as [$label, $v])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __($label) }}</div>
            <div class="fs-5 fw-bold" dir="ltr">{{ is_int($v) ? $v : money($v, $cur) }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Money taken for his patients') }}</h2></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Patient') }}</th><th>{{ __('Invoice') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($s['payments'] as $p)
                            <tr><td>{{ fmt_date($p->received_at) }}</td><td dir="ltr">{{ $p->number }}</td><td>{{ $p->patient?->name }}</td><td dir="ltr">{{ $p->invoice?->number }}</td>
                                <td class="text-end {{ $p->isRefund() ? 'text-danger' : '' }}" dir="ltr">{{ money($p->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">{{ __('No money taken in this month.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($s['collected']) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Paid to him for this month') }}</h2></div>
            <ul class="list-group list-group-flush">
                @forelse ($paid as $e)
                    <li class="list-group-item d-flex small"><span class="flex-grow-1">{{ fmt_date($e->spent_on) }} · <span dir="ltr">{{ $e->number }}</span></span><b dir="ltr">{{ money($e->amount) }}</b></li>
                @empty
                    <li class="list-group-item small text-muted">{{ __('Nothing paid yet.') }}</li>
                @endforelse
            </ul>
            @can('billing.manage')
                @if (\App\Support\Num::cmp($s['left'], '0') > 0)
                    <form method="POST" action="{{ route('commission.pay', ['doctor' => $doctor->id]) }}" class="card-body border-top row g-2 no-print">@csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <div class="col-6"><label class="form-label small" for="pay-amount">{{ __('Amount') }}</label>
                            <input type="number" class="form-control" id="pay-amount" name="amount" value="{{ \App\Support\Num::round($s['left'], 2) }}" min="0.01" max="{{ $s['left'] }}" step="0.01" required dir="ltr"></div>
                        <div class="col-6"><label class="form-label small" for="pay-method">{{ __('Paid by') }}</label>
                            <select class="form-select" id="pay-method" name="method">@foreach (\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                        <div class="col-12"><button class="btn btn-primary w-100" type="submit">{{ __('Pay the doctor') }}</button></div>
                    </form>
                @endif
            @endcan
        </div>
        <div class="small text-muted mt-4 d-none d-print-block">{{ __('Signature of the doctor') }}: ______________________</div>
    </div>
</div>
@endsection
