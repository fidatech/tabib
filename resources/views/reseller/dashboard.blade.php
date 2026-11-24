@extends('layouts.reseller', ['title' => __('Dashboard')])

@section('content')
@php $N = \App\Support\Num::class; @endphp
<x-page-header :title="__('Welcome, :name', ['name' => $me->name])" icon="gauge-high" :subtitle="__('Your commission').': '.$me->commissionLabel()">
    <a class="btn btn-primary" href="{{ route('reseller.activations') }}"><x-icon name="user-plus" class="me-1" /> {{ __('Open a new clinic') }}</a>
    <a class="btn btn-light-secondary" href="{{ route('reseller.payments') }}"><x-icon name="money-bill-wave" class="me-1" /> {{ __('Report a payment') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><a class="kpi kpi-blue text-decoration-none" href="{{ route('reseller.clients') }}"><span class="kpi-icon"><x-icon name="house-medical" /></span><div><div class="kpi-value">{{ $account['clients'] }}</div><div class="kpi-label">{{ __('My clinics') }}</div>@if ($waiting['activations'])<div class="kpi-note">{{ $waiting['activations'] }} {{ __('waiting for approval') }}</div>@endif</div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-blue text-decoration-none" href="{{ route('reseller.payments') }}"><span class="kpi-icon"><x-icon name="money-bill-wave" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['sales']) }}</div><div class="kpi-label">{{ __('Sales') }}</div>@if ($waiting['claims'])<div class="kpi-note">{{ $waiting['claims'] }} {{ __('waiting for confirmation') }}</div>@endif</div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi kpi-green text-decoration-none" href="{{ route('reseller.commissions') }}"><span class="kpi-icon"><x-icon name="percent" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['commission']) }}</div><div class="kpi-label">{{ __('Commission earned') }}</div><div class="kpi-note" dir="ltr">${{ money($account['paid_out']) }} {{ __('received') }}</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="kpi {{ $N::isPositive($account['balance']) ? 'kpi-red' : ($N::isNegative($account['balance']) ? 'kpi-green' : 'kpi-blue') }} text-decoration-none" href="{{ route('reseller.finances') }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($N::abs($account['balance'])) }}</div><div class="kpi-label">{{ $N::isPositive($account['balance']) ? __('You owe the company') : ($N::isNegative($account['balance']) ? __('The company owes you') : __('Settled')) }}</div></div></a></div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Renewals coming up') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle mb-0">
                    <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Licence until') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($renewals as $t)
                            @php $days = $t->daysLeft(); @endphp
                            <tr><td class="fw-semibold">{{ $t->name }}</td><td dir="ltr">{{ $t->licence_expires_on->format('Y-m-d') }}</td>
                                <td>@if ($days < 0)<span class="badge bg-danger-subtle text-danger">{{ __(':days days overdue', ['days' => abs($days)]) }}</span>@else<span class="badge bg-warning-subtle text-warning">{{ __('in :days days', ['days' => $days]) }}</span>@endif</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('Nothing is due in this period') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Latest payments') }}</h2><a class="small" href="{{ route('reseller.payments') }}">{{ __('All') }}</a></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Clinic') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Commission') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $p)
                            <tr><td dir="ltr" class="text-nowrap">{{ $p->paid_on->format('Y-m-d') }}</td><td>{{ $p->tenant?->name }}</td><td class="text-end" dir="ltr">${{ money($p->amount) }}</td><td class="text-end text-success fw-semibold" dir="ltr">${{ money($p->commission) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
