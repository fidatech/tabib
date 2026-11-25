@extends('layouts.app', ['title' => __('Import patients')])

@section('content')
<x-page-header :title="__('Import patients')" icon="file-import" :crumbs="[[__('Patients'), route('patients.index')], [__('Import')]]" />

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <form method="POST" action="{{ route('patients.import.read') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('1. The file') }}</h2></div>
            <div class="card-body">
                <label class="form-label" for="imp-file">{{ __('An Excel file (.xlsx) or a CSV file, up to 5,000 patients') }}</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="imp-file" name="file" required accept=".xlsx,.csv,.txt">
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-end mt-3"><button class="btn btn-primary" type="submit"><x-icon name="file-import" class="me-1" /> {{ __('Read the file') }}</button></div>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body small">
            <p class="fw-semibold mb-1">{{ __('How the file should look') }}</p>
            <ul class="mb-2">
                <li>{{ __('The first row holds the headings: name, father\'s name, sex, age or date of birth, phone, Tazkira ...') }}</li>
                <li>{{ __('Every other row is one patient. The name and the sex are needed; the rest is optional.') }}</li>
                <li>{{ __('Dates may be written 1990-05-20, 20/05/1990, or in the Shamsi calendar (1369/02/30).') }}</li>
            </ul>
            <p class="mb-0 text-muted">{{ __('Nothing is registered before you see the check. Rows that look like patients already here are kept apart.') }}</p>
        </div></div>
    </div>
</div>
@endsection
