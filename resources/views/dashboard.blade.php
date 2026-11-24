@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    $done = collect($steps)->filter(fn (array $step) => $step[1])->count();
@endphp

@section('content')
<x-page-header :title="__('app.dashboard.title')" icon="gauge-high" />

@include('partials.dashboard-hero')

<div class="dc-stats mb-3">
    @foreach ($tiles as [$label, $count, $icon, $tone, $url])
        @if ($url)
            <a class="dc-stat is-link" href="{{ $url }}">
                <span class="icon-soft {{ $tone }}"><x-icon :name="$icon" /></span>
                <div class="min-w-0"><div class="dc-stat-value">{{ is_int($count) ? number_format($count) : $count }}</div><div class="dc-stat-label">{{ __($label) }}</div></div>
                <span class="dc-stat-go" aria-hidden="true"><x-icon name="arrow-right" class="flip-rtl" /></span>
            </a>
        @else
            <div class="dc-stat">
                <span class="icon-soft {{ $tone }}"><x-icon :name="$icon" /></span>
                <div class="min-w-0"><div class="dc-stat-value">{{ is_int($count) ? number_format($count) : $count }}</div><div class="dc-stat-label">{{ __($label) }}</div></div>
            </div>
        @endif
    @endforeach
</div>

<div class="row g-3 mb-3">
    @if ($steps)
        <div class="col-12 col-xl-7">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 card-title mb-0">{{ __('Get the clinic ready') }}</h2>
                    <span class="badge {{ $done === count($steps) ? 'bg-success' : 'bg-primary' }}" dir="ltr">{{ $done }} / {{ count($steps) }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($steps as [$label, $isDone, $url, $icon])
                        <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                            <span class="icon-soft {{ $isDone ? 'success' : 'primary' }}"><x-icon :name="$isDone ? 'check' : $icon" /></span>
                            <span class="flex-grow-1 {{ $isDone ? 'text-muted text-decoration-line-through' : 'fw-semibold' }}">{{ __($label) }}</span>
                            @unless ($isDone)<x-icon name="arrow-right" class="text-muted" />@endunless
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    <div class="col-12 {{ $steps ? 'col-xl-5' : '' }}">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Messages') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($notices as $notice)
                    <a href="{{ route('notices.open', ['id' => $notice->id]) }}" class="list-group-item list-group-item-action py-3">
                        <span class="d-block fw-semibold">{{ $notice->title }}</span>
                        @if ($notice->body)<span class="d-block small text-muted text-truncate">{{ $notice->body }}</span>@endif
                    </a>
                @empty
                    <div class="text-center text-muted small py-5">{{ __('No new messages') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@can('branches.view')
<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Branches') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Branch name') }}</th><th>{{ __('Branch type') }}</th><th>{{ __('City') }}</th><th>{{ __('Person in charge') }}</th><th>{{ __('Users') }}</th><th>{{ __('Active') }}</th></tr></thead>
            <tbody>
                @foreach ($branches as $branch)
                    <tr>
                        <td>{{ $branch->name }} <span class="text-muted small">{{ $branch->code }}</span></td>
                        <td>{{ __(\App\Models\Branch::TYPES[$branch->type] ?? $branch->type) }}</td>
                        <td>{{ $branch->city }}</td>
                        <td>{{ $branch->representative_name }}</td>
                        <td>{{ $branch->users_count }}</td>
                        <td>{{ $branch->is_active ? __('Yes') : __('No') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endcan
@endsection
