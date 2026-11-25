@extends('layouts.app', ['title' => __('Merge a duplicate record')])

@section('content')
<x-page-header :title="__('Merge a duplicate record')" icon="code-merge"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id])], [__('Merge')]]" />

<div class="alert alert-info"><x-icon name="circle-info" class="me-1" />
    {{ __('Everything of the duplicate (visits, history, vitals, documents, prescriptions, invoices, appointments) moves to :name (:number). The duplicate record is closed, not deleted: opening it leads here.', ['name' => $patient->name, 'number' => $patient->number]) }}</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Choose the duplicate') }}</h2></div>
            <div class="card-body">
                <form method="GET" class="mb-3 d-flex gap-2 align-items-end">
                    <div class="flex-grow-1"><label class="form-label small" for="dup-search">{{ __('Search a patient') }}</label>
                        <input type="search" class="form-control" id="dup-search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, phone or record number') }}"></div>
                    <button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}" title="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button>
                </form>
                @if ($alike->isNotEmpty())
                    <p class="small text-muted mb-1">{{ request('q') ? __('Found') : __('Records that look like the same person') }}</p>
                    <div class="list-group">
                        @foreach ($alike as $p)
                            <a class="list-group-item list-group-item-action small {{ $duplicate?->id === $p->id ? 'active' : '' }}" href="{{ route('patients.merge', ['id' => $patient->id, 'duplicate' => $p->id]) }}">
                                <b>{{ $p->name }}</b> {{ $p->father_name }} · <span dir="ltr">{{ $p->number }}</span> · {{ $p->ageText() }} · <span dir="ltr">{{ $p->phone }}</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        @if ($duplicate)
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th></th><th>{{ __('Kept') }}</th><th>{{ __('Duplicate (closed)') }}</th></tr></thead>
                        <tbody>
                            @foreach (['Name' => 'name', "Father's name" => 'father_name', 'Number' => 'number', 'Phone' => 'phone', 'Tazkira number' => 'tazkira'] as $label => $f)
                                <tr><th class="fw-normal text-muted">{{ __($label) }}</th><td>{{ $patient->{$f} }}</td><td>{{ $duplicate->{$f} }}</td></tr>
                            @endforeach
                            <tr><th class="fw-normal text-muted">{{ __('Age') }}</th><td>{{ $patient->ageText() }}</td><td>{{ $duplicate->ageText() }}</td></tr>
                            @foreach ($counts[$patient->id] as $label => $n)
                                <tr><th class="fw-normal text-muted">{{ $label }}</th><td>{{ $n }}</td><td>{{ $counts[$duplicate->id][$label] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('patients.merge.store', ['id' => $patient->id]) }}" class="row g-2" data-confirm="{{ __('Merge :dup into :keep? This cannot be undone.', ['dup' => $duplicate->number, 'keep' => $patient->number]) }}" data-confirm-ok="{{ __('Merge') }}">
                        @csrf
                        <input type="hidden" name="duplicate_id" value="{{ $duplicate->id }}">
                        <div class="col-12"><label class="form-label small" for="merge-reason">{{ __('Why') }}</label>
                            <input type="text" class="form-control" id="merge-reason" name="reason" required maxlength="255" placeholder="{{ __('Registered twice at the front desk ...') }}"></div>
                        <div class="col-12 col-md-6"><label class="form-label small" for="merge-password">{{ __('Your password') }}</label>
                            <input type="password" class="form-control" id="merge-password" name="password" required autocomplete="current-password"></div>
                        <div class="col-12 col-md-6 d-flex align-items-end"><button class="btn btn-danger w-100" type="submit"><x-icon name="code-merge" class="me-1" /> {{ __('Merge into :number', ['number' => $patient->number]) }}</button></div>
                    </form>
                </div>
            </div>
        @else
            <p class="text-muted">{{ __('Choose the record to close; the record you came from is kept.') }}</p>
        @endif
    </div>
</div>
@endsection
