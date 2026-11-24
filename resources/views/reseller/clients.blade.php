@extends('layouts.reseller', ['title' => __('My clinics')])

{{-- The clinics this reseller sold, each with its branches: paid or due. --}}
@section('content')
@php $N = \App\Support\Num::class; $S = \App\Services\Subscriptions::class; @endphp
<x-page-header :title="__('My clinics')" icon="house-medical" :subtitle="__('The clinics you sold: their licence, their branches and what is still to be paid')">
    <a class="btn btn-primary" href="{{ route('reseller.activations') }}"><x-icon name="user-plus" class="me-1" /> {{ __('Open a new clinic') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="house-medical" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2><p class="text-muted">{{ __('Ask for your first clinic to be opened from the Activation page.') }}</p></div>
        @else
            <table class="table table-hover w-100 align-middle">
                <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Status') }}</th><th>{{ __('Main branch') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Licence until') }}</th><th class="text-end">{{ __('Still to be paid') }}</th><th class="no-print"></th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $t = $row['tenant']; $main = collect($row['rows'])->firstWhere('main', true); $deps = collect($row['rows'])->where('main', false)->where('counts', true); @endphp
                        <tr>
                            <td><span class="fw-semibold">{{ $t->name }}</span><div class="small"><a href="//{{ $t->domain }}" target="_blank" rel="noopener" dir="ltr">{{ $t->domain }}</a></div></td>
                            <td>{{ __(\App\Models\Central\Tenant::STATUSES[$t->status] ?? $t->status) }}</td>
                            <td>@if ($main)<span class="badge bg-{{ $S::TONES[$main['state']] }}-subtle text-{{ $S::TONES[$main['state']] }}">{{ __($S::STATES[$main['state']]) }}</span>@endif</td>
                            <td>@if ($deps->isEmpty())<span class="text-muted">—</span>@else<span class="fw-semibold">{{ $deps->where('state', 'paid')->count() }}</span> / {{ $deps->count() }} {{ __('paid') }}@endif</td>
                            <td dir="ltr">{{ $t->licence_expires_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-end fw-semibold {{ $N::isPositive($row['left']) ? 'text-danger' : '' }}" dir="ltr">${{ money($row['left']) }}</td>
                            <td class="text-end no-print"><button type="button" class="btn btn-sm btn-light-secondary" data-bs-toggle="collapse" data-bs-target="#c{{ $t->id }}" aria-expanded="false" aria-controls="c{{ $t->id }}">{{ __('Branches') }}</button></td>
                        </tr>
                        <tr class="collapse" id="c{{ $t->id }}"><td colspan="7" class="bg-body-tertiary">
                            <div class="small text-muted mb-2">{{ __('Owner') }}: {{ $t->owner_name ?: '—' }} · <span dir="ltr">{{ $t->owner_phone }}</span> · {{ __('Invoiced') }} <span dir="ltr">${{ money($row['balance']['invoiced']) }}</span> · {{ __('Paid') }} <span dir="ltr">${{ money($row['balance']['paid']) }}</span></div>
                            @include('central.partials.subscription-rows', ['rows' => $row['rows']])
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
