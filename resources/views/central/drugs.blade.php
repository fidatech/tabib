@extends('layouts.central', ['title' => __('Medicines')])

@php
    $forms = \App\Models\Medicine::FORMS;
    $e = $edit;
    $v = fn (string $k) => old($k, $e?->{$k});
@endphp

@section('content')
<x-page-header :title="__('Medicines')" icon="pills" :subtitle="__(':count medicines on the platform list; :off taken off.', ['count' => number_format($count), 'off' => number_format($off)])">
    <form method="POST" action="{{ route('admin.drugs.sync') }}">@csrf<button class="btn btn-outline-soft" type="submit"><x-icon name="rotate" class="me-1" /> {{ __('Send to every clinic now') }}</button></form>
</x-page-header>

<div class="alert alert-info"><x-icon name="circle-info" class="me-1" />
    {{ __('Clinics cannot change this list. A change here goes to every clinic at once; a medicine taken off cannot be prescribed anywhere. Clinics still write their own dose limits, switch off what they do not use and add their own medicines. Have a pharmacist check what you add.') }}</div>

<form method="POST" action="{{ $e ? route('admin.drugs.update', ['id' => $e->id]) : route('admin.drugs.store') }}" class="card mb-3">
    @csrf @if ($e) @method('PUT') <input type="hidden" name="back_q" value="{{ $term }}"> @endif
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ $e ? __('Change :name', ['name' => $e->label()]) : __('Add a medicine') }}</h2></div>
    <div class="card-body row g-3">
        <div class="col-12 col-md-4"><label class="form-label" for="d-name">{{ __('Generic name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="d-name" name="name" value="{{ $v('name') }}" required maxlength="150" dir="ltr"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="d-strength">{{ __('Strength') }}</label>
            <input type="text" class="form-control" id="d-strength" name="strength" value="{{ $v('strength') }}" maxlength="80" dir="ltr" placeholder="500 mg"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="d-form">{{ __('Form') }} <span class="text-danger">*</span></label>
            <select class="form-select" id="d-form" name="form" data-plain>@foreach ($forms as $f)<option value="{{ $f }}" @selected($v('form') === $f)>{{ __($f) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="d-route">{{ __('Route') }}</label>
            <input type="text" class="form-control" id="d-route" name="route" value="{{ $v('route') }}" maxlength="20" dir="ltr" placeholder="oral"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="d-class">{{ __('Class') }}</label>
            <input type="text" class="form-control" id="d-class" name="class" value="{{ $v('class') }}" maxlength="60" dir="ltr" placeholder="nsaid"></div>
        <div class="col-12 col-md-8"><label class="form-label" for="d-brands">{{ __('Brand names') }}</label>
            <input type="text" class="form-control @error('brands') is-invalid @enderror" id="d-brands" name="brands" value="{{ $v('brands') }}" maxlength="2000" dir="ltr" placeholder="PK: Panadol, Calpol | IN: Crocin | TR: Parol | US: Tylenol">
            @error('brands')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-6 col-md-2"><label class="form-label" for="d-mg">{{ __('mg in one unit') }}</label>
            <input type="number" step="any" min="0" class="form-control" id="d-mg" name="mg_per_unit" value="{{ $v('mg_per_unit') }}" dir="ltr"></div>
        <div class="col-6 col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="controlled" value="0">
            <input class="form-check-input" type="checkbox" id="d-controlled" name="controlled" value="1" @checked($v('controlled'))><label class="form-check-label" for="d-controlled">{{ __('Controlled') }}</label></div></div>
        <div class="col-12 d-flex gap-2 justify-content-end">
            @if ($e)<a class="btn btn-light-secondary" href="{{ route('admin.drugs.index', ['q' => $term]) }}">{{ __('Cancel') }}</a>@endif
            <button class="btn btn-primary" type="submit">{{ $e ? __('Save and send') : __('Add and send') }}</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-12 col-md"><label class="form-label small mb-1" for="d-q">{{ __('Search') }}</label>
                <input type="search" class="form-control" id="d-q" name="q" value="{{ $term }}" placeholder="{{ __('Generic name, brand, class ...') }}"></div>
            <div class="col-8 col-md-auto"><label class="form-label small mb-1" for="d-show">{{ __('Show') }}</label>
                <select class="form-select" id="d-show" name="show" data-plain><option value="">{{ __('All medicines') }}</option><option value="off" @selected($show === 'off')>{{ __('Taken off the list') }}</option></select></div>
            <div class="col-4 col-md-auto"><button class="btn btn-light-secondary w-100" type="submit">{{ __('Search') }}</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Medicine') }}</th><th>{{ __('Class') }}</th><th>{{ __('Brand names') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td dir="ltr"><strong>{{ $row->name }}</strong> {{ $row->strength }} <span class="text-muted">{{ $row->form }}</span>@if ($row->controlled) <span class="badge bg-danger">{{ __('Controlled') }}</span>@endif</td>
                            <td dir="ltr">{{ $row->class }}</td>
                            <td class="small" dir="ltr">{{ \Illuminate\Support\Str::limit((string) $row->brands, 90) }}</td>
                            <td><span class="dot {{ $row->is_active ? 'dot-ok' : '' }}"></span>{{ $row->is_active ? __('On the list') : __('Taken off') }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.drugs.index', ['q' => $term, 'show' => $show, 'edit' => $row->id, 'page' => $rows->currentPage()]) }}">{{ __('Change') }}</a>
                                <form method="POST" action="{{ route('admin.drugs.status', ['id' => $row->id]) }}" class="d-inline" data-confirm="{{ $row->is_active ? __('Take :name off the list in every clinic?', ['name' => $row->label()]) : __('Put :name back on the list?', ['name' => $row->label()]) }}" data-confirm-ok="{{ $row->is_active ? __('Take off') : __('Put back') }}">@csrf
                                    <button class="btn btn-sm {{ $row->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $row->is_active ? __('Take off') : __('Put back') }}</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ $count ? __('Nothing found') : __('The list is empty: run php artisan medicines:import') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links('partials.pagination') }}
    </div>
</div>
@endsection
