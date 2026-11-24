@extends('layouts.central', ['title' => __('System health')])

@section('content')
@php $behind = collect($clients)->filter(fn ($c) => ! $c['ok'] || $c['pending'] > 0)->count(); @endphp
<x-page-header :title="__('System health')" icon="heart-pulse" :subtitle="__('Is the service well, and is every clinic on the newest version?')">
    <form method="POST" action="{{ route('admin.health.clear') }}">@csrf<button class="btn btn-outline-soft" type="submit"><x-icon name="rotate" class="me-1" /> {{ __('Clear the caches') }}</button></form>
    <form method="POST" action="{{ route('admin.health.migrate') }}" data-confirm="{{ __('Update the tables of every clinic now?') }}" data-confirm-ok="{{ __('Update') }}" data-confirm-style="primary">@csrf<button class="btn btn-primary" type="submit"><x-icon name="database" class="me-1" /> {{ __('Update every clinic') }}</button></form>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi {{ $behind ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="house-medical" /></span><div><div class="kpi-value">{{ count($clients) - $behind }} / {{ count($clients) }}</div><div class="kpi-label">{{ __('Clinics up to date') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $disk['used'] > 90 ? 'kpi-red' : ($disk['used'] > 75 ? 'kpi-amber' : 'kpi-green') }}"><span class="kpi-icon"><x-icon name="database" /></span><div><div class="kpi-value" dir="ltr">{{ human_size($disk['free']) }}</div><div class="kpi-label">{{ __('Free on the disk') }}</div><div class="meter {{ $disk['used'] > 90 ? 'is-bad' : ($disk['used'] > 75 ? 'is-warn' : '') }} mt-1" style="width:120px"><span style="width: {{ $disk['used'] }}%"></span></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $backupLate ? 'kpi-red' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="clock-rotate-left" /></span><div><div class="kpi-value" style="font-size:18px">{{ $lastBackup ? \Illuminate\Support\Carbon::parse($lastBackup)->diffForHumans() : __('Never') }}</div><div class="kpi-label">{{ __('Last backup') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ count($errors) ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="triangle-exclamation" /></span><div><div class="kpi-value">{{ count($errors) }}</div><div class="kpi-label">{{ __('Recent errors') }}</div><div class="kpi-note">{{ $failedJobs }} {{ __('failed jobs') }}</div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('The server') }}</h2></div><div class="card-body">
            <dl class="facts">
                @foreach ($facts as $label => $value)<div><dt>{{ __($label) }}</dt><dd dir="ltr">{{ $value }}</dd></div>@endforeach
                <div><dt>{{ __('Updates of the panel itself waiting') }}</dt><dd>{{ $centralPending }}</dd></div>
            </dl>
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Checks') }}</h2></div><div class="card-body">
            @foreach ($checks as $label => $ok)
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><span>{{ __($label) }}</span><span class="{{ $ok ? 'text-success' : 'text-danger' }} fw-semibold text-nowrap"><span class="dot {{ $ok ? 'dot-ok' : 'dot-bad' }}"></span>{{ $ok ? __('Yes') : __('No') }}</span></div>
            @endforeach
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Recent errors') }}</h2></div><div class="card-body">
            @forelse ($errors as $error)
                <div class="py-2 border-bottom"><div class="d-flex justify-content-between gap-3 small"><span class="badge bg-danger-subtle text-danger">{{ $error['level'] }}</span><span class="text-muted" dir="ltr">{{ $error['at'] }}</span></div><div class="small" dir="ltr" style="overflow-wrap:anywhere">{{ $error['text'] }}</div></div>
            @empty
                <div class="text-center text-muted py-4"><x-icon name="circle-check" class="text-success me-1" /> {{ __('No errors written lately.') }}</div>
            @endforelse
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('The databases of the clinics') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Address') }}</th><th>{{ __('Database') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updates waiting') }}</th></tr></thead>
            <tbody>
                @forelse ($clients as $row)
                    <tr>
                        <td><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $row['tenant']->id]) }}">{{ $row['tenant']->name }}</a></td><td dir="ltr">{{ $row['tenant']->domain }}</td><td dir="ltr">{{ $row['tenant']->database }}</td>
                        <td>@if ($row['ok'])<span class="dot dot-ok"></span>{{ __('Reachable') }}@else<span class="dot dot-bad"></span>{{ __('Cannot be opened') }}<div class="small text-danger" dir="ltr">{{ $row['error'] }}</div>@endif</td>
                        <td>@if ($row['pending'] === null)—@elseif ($row['pending'] > 0)<span class="badge bg-warning-subtle text-warning">{{ $row['pending'] }}</span>@else<span class="text-success">{{ __('Up to date') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
