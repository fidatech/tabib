@extends('layouts.reseller', ['title' => __('Finances')])

{{-- His account with the company: what he holds, what he earned, what moved between them, and what is left. --}}
@section('content')
@php
    $N = \App\Support\Num::class;
    // the same lines the administrator reads, said from this side
    $mine = ['The reseller paid us' => 'You paid the company', 'We paid the reseller' => 'The company paid you', 'Took a payment from a client' => 'You took a payment from a client', 'A client paid us directly' => 'A client paid the company directly'];
@endphp
<x-page-header :title="__('Finances')" icon="wallet" :subtitle="__('Your account with the company')" />

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-amber"><span class="kpi-icon"><x-icon name="hand-holding-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['collected']) }}</div><div class="kpi-label">{{ __('Taken from clients') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="percent" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['commission']) }}</div><div class="kpi-label">{{ __('Commission earned') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="arrow-right-arrow-left" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['received']) }}</div><div class="kpi-label">{{ __('Handed over to the company') }}</div><div class="kpi-note" dir="ltr">${{ money($account['paid_out']) }} {{ __('paid to you') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $N::isPositive($account['balance']) ? 'kpi-red' : ($N::isNegative($account['balance']) ? 'kpi-green' : 'kpi-blue') }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($N::abs($account['balance'])) }}</div><div class="kpi-label">{{ $N::isPositive($account['balance']) ? __('You owe the company') : ($N::isNegative($account['balance']) ? __('The company owes you') : __('Settled')) }}</div></div></div></div>
</div>

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Statement of account') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100 align-middle">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What happened') }}</th><th class="text-end">{{ __('You owe') }}</th><th class="text-end">{{ __('You are credited') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                @forelse ($statement as $line)
                    <tr>
                        <td dir="ltr" class="text-nowrap">{{ $line['date'] }}</td>
                        <td>{{ __($mine[$line['what']] ?? $line['what']) }}<div class="small text-muted">{{ $line['detail'] }}</div></td>
                        <td class="text-end" dir="ltr">{{ $N::isZero($line['owes']) ? '' : '$'.money($line['owes']) }}</td>
                        <td class="text-end" dir="ltr">{{ $N::isZero($line['earns']) ? '' : '$'.money($line['earns']) }}</td>
                        <td class="text-end fw-semibold {{ $N::isPositive($line['balance']) ? 'text-danger' : ($N::isNegative($line['balance']) ? 'text-success' : '') }}" dir="ltr">{{ $N::isNegative($line['balance']) ? '−' : '' }}${{ money($N::abs($line['balance'])) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="small text-muted mb-0">{{ __('You owe what you take from clients and what the company pays you; you are credited your commission and what you hand over. A balance above zero is owed to the company; with a minus the company owes you.') }}</p>
    </div>
</div>
@endsection
