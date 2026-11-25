@extends('layouts.app', ['title' => __('Import patients')])

@php $F = \App\Services\PatientImport::FIELDS; @endphp

@section('content')
<x-page-header :title="__('Import patients')" icon="file-import" :crumbs="[[__('Patients'), route('patients.index')], [__('Import')]]"
    :subtitle="$name.' · '.__(':count rows', ['count' => $count])" />

<form method="POST" action="{{ route('patients.import.map.store') }}" class="card">
    @csrf
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('2. Which column is which') }}</h2></div>
    <div class="card-body">
        <p class="small text-muted">{{ __('The columns were matched from their headings. Check them; a column you do not need is left as "Not used".') }}</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        @foreach ($headings as $i => $h)
                            <th class="import-col">
                                <span class="d-block small text-muted text-truncate" dir="auto">{{ $h !== '' ? $h : __('Column :n', ['n' => $i + 1]) }}</span>
                                <label class="visually-hidden" for="map-{{ $i }}">{{ __('Field of the column :n', ['n' => $i + 1]) }}</label>
                                <select class="form-select form-select-sm" id="map-{{ $i }}" name="map[{{ $i }}]">
                                    <option value="">{{ __('Not used') }}</option>
                                    @foreach ($F as $field => [$label])<option value="{{ $field }}" @selected(($map[$i] ?? '') === $field)>{{ __($label) }}</option>@endforeach
                                </select>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sample as $row)
                        <tr>@foreach ($headings as $i => $h)<td class="small" dir="auto">{{ \Illuminate\Support\Str::limit($row[$i] ?? '', 40) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <button class="btn btn-light" type="submit" form="imp-cancel">{{ __('Give up') }}</button>
        <button class="btn btn-primary" type="submit">{{ __('Check the rows') }}</button>
    </div>
</form>
<form method="POST" action="{{ route('patients.import.cancel') }}" id="imp-cancel">@csrf</form>
@endsection
