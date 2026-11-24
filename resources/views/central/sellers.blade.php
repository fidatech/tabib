@extends('layouts.central', ['title' => __('Resellers')])

{{-- The salesmen and marketing agents: their clients, sales and commission, and who owes whom. --}}
@section('content')
@php $me = auth('admin')->user(); @endphp
<x-page-header :title="__('Resellers')" icon="handshake" :subtitle="__('Salesmen and marketing agents: their clients, their commission and their account with us')">
    @if ($me->allows('billing.manage'))<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSeller"><x-icon name="plus" class="me-1" /> {{ __('New reseller') }}</button>@endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="money-bill-wave" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['sales']) }}</div><div class="kpi-label">{{ __('Sales of resellers') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="percent" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['commission']) }}</div><div class="kpi-label">{{ __('Commission earned') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ \App\Support\Num::isPositive($totals['owed']) ? 'kpi-red' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['owed']) }}</div><div class="kpi-label">{{ __('Resellers owe us') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ \App\Support\Num::isPositive($totals['owing']) ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="hand-holding-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($totals['owing']) }}</div><div class="kpi-label">{{ __('We owe resellers') }}</div></div></div></div>
</div>

@include('central.partials.seller-claims', ['claims' => $claims])

<div class="row g-3">
    <div class="col-12">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Resellers') }}</h2></div>
            <div class="card-body">
                @if ($sellers->isEmpty())
                    <div class="empty-state"><div class="es-icon"><x-icon name="handshake" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
                @else
                    <table class="table table-hover w-100 align-middle">
                        <thead><tr><th>{{ __('Reseller') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Clinics') }}</th><th class="text-end">{{ __('Sales') }}</th><th class="text-end">{{ __('Commission earned') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="no-print"></th></tr></thead>
                        <tbody>
                            @foreach ($sellers as $row)
                                <tr class="{{ $row['seller']->is_active ? '' : 'text-muted' }}">
                                    <td><a class="fw-semibold" href="{{ route('admin.sellers.show', ['id' => $row['seller']->id]) }}">{{ $row['seller']->name }}</a>@unless ($row['seller']->is_active) <span class="badge bg-secondary-subtle text-secondary">{{ __('Inactive') }}</span>@endunless @if ($row['seller']->canSignIn()) <span class="badge bg-primary-subtle text-primary" title="{{ __('Can sign in to the panel of the resellers') }}"><x-icon name="key" /></span>@endif
                                        <div class="small text-muted" dir="ltr">{{ implode(' · ', array_filter([$row['seller']->phone, $row['seller']->email])) }}</div></td>
                                    <td>{{ $row['seller']->commissionLabel() }}</td>
                                    <td>{{ $row['clients'] }}</td>
                                    <td class="text-end" dir="ltr">${{ money($row['sales']) }}</td>
                                    <td class="text-end" dir="ltr">${{ money($row['commission']) }}</td>
                                    <td class="text-end">@include('central.partials.seller-balance', ['balance' => $row['balance']])</td>
                                    <td class="text-end no-print"><x-act kind="view" :href="route('admin.sellers.show', ['id' => $row['seller']->id])" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@if ($me->allows('billing.manage'))
    {{-- a new reseller; it opens by itself again when the form came back with an error --}}
    <div class="modal fade" id="newSeller" tabindex="-1" aria-labelledby="newSellerTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable"><form method="POST" action="{{ route('admin.sellers.store') }}" class="modal-content" autocomplete="off">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="newSellerTitle">{{ __('New reseller') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">@include('central.partials.seller-fields', ['seller' => null])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
        </form></div>
    </div>
    @if ($errors->any())
        <script>document.addEventListener('DOMContentLoaded', function () { if (window.ERP) ERP.showModal('#newSeller'); });</script>
    @endif
@endif
@endsection
