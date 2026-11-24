@extends('layouts.reseller', ['title' => __('Commissions')])

@section('content')
<x-page-header :title="__('Commissions')" icon="percent" :subtitle="__('Your commission').': '.$me->commissionLabel().' · '.__(\App\Models\Central\Seller::COMMISSIONS[$me->commission_type])" />

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="money-bill-wave" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['sales']) }}</div><div class="kpi-label">{{ __('Sales') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="percent" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['commission']) }}</div><div class="kpi-label">{{ __('Commission earned') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="hand-holding-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['paid_out']) }}</div><div class="kpi-label">{{ __('Paid to you') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-amber"><span class="kpi-icon"><x-icon name="house-medical" /></span><div><div class="kpi-value">{{ $account['clients'] }}</div><div class="kpi-label">{{ __('My clinics') }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By clinic') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle mb-0">
                    <thead><tr><th>{{ __('Clinic') }}</th><th class="text-end">{{ __('Sales') }}</th><th class="text-end">{{ __('Commission') }}</th></tr></thead>
                    <tbody>
                        @forelse ($byClient as $row)
                            <tr><td><span class="fw-semibold">{{ $row['tenant']?->name }}</span><div class="small text-muted">{{ $row['count'] }} {{ __('payments') }}</div></td><td class="text-end" dir="ltr">${{ money($row['sales']) }}</td><td class="text-end text-success fw-semibold" dir="ltr">${{ money($row['commission']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Payment by payment') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle mb-0">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Receipt') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Commission') }}</th></tr></thead>
                    <tbody>
                        @forelse ($lines as $p)
                            <tr><td dir="ltr" class="text-nowrap">{{ $p->paid_on->format('Y-m-d') }}</td><td>{{ $p->tenant?->name }}</td><td>{{ $p->number }}<div class="small text-muted">{{ $p->invoice?->number ?? __('On account') }}</div></td><td class="text-end" dir="ltr">${{ money($p->amount) }}</td><td class="text-end text-success fw-semibold" dir="ltr">${{ money($p->commission) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
