@extends('layouts.app', ['title' => __($meta[0])])

@php $N = \App\Support\Num::class; $m = fn ($v) => money($v); @endphp

@section('content')
<x-page-header :title="__($meta[0])" :icon="$meta[1]" :crumbs="[[__('Reports'), route('reports.index')], [__($meta[0])]]"
    :subtitle="$branch->name.($report === 'owed' ? ' · '.__('today') : ' · '.fmt_date($from).' – '.fmt_date($to))">
    <button type="button" class="btn btn-outline-soft no-print" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    <a class="btn btn-outline-soft no-print" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="file-excel" class="me-1" /> Excel</a>
</x-page-header>

<form method="GET" class="row g-2 mb-3 align-items-end no-print">
    @if ($report !== 'owed')
        <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="rep-from">{{ __('From') }}</label><x-date name="from" :value="$from->toDateString()" id="rep-from" /></div>
        <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="rep-to">{{ __('To') }}</label><x-date name="to" :value="$to->toDateString()" id="rep-to" /></div>
    @endif
    @if ($branches->count() > 1)
        <div class="col-6 col-md-auto"><label class="form-label small mb-1" for="rep-branch">{{ __('Branch') }}</label>
            <select class="form-select" id="rep-branch" name="branch">@foreach ($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $branch->id)>{{ $b->name }}</option>@endforeach</select></div>
    @endif
    <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
</form>

@include('reports.parts.'.$report)
@endsection
