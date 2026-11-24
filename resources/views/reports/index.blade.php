@extends('layouts.app', ['title' => __('Reports')])

@section('content')
<x-page-header :title="__('Reports')" icon="chart-column" />
<div class="row g-3">
    @forelse ($reports as $key => [$name, $icon, $about])
        <div class="col-12 col-md-6 col-xl-4">
            <a class="card h-100 report-card" href="{{ route('reports.show', ['report' => $key]) }}">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="icon-soft primary"><x-icon :name="$icon" /></span>
                    <span><span class="d-block fw-semibold h6 mb-1">{{ __($name) }}</span><span class="small text-muted">{{ __($about) }}</span></span>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12"><p class="text-muted">{{ __('No report is open to you.') }}</p></div>
    @endforelse
</div>
@endsection
