@extends('layouts.app', ['title' => __('Import patients')])

@php $F = \App\Services\PatientImport::FIELDS; $fields = array_values(array_unique($map)); @endphp

@section('content')
<x-page-header :title="__('Import patients')" icon="file-import" :crumbs="[[__('Patients'), route('patients.index')], [__('Import')]]" :subtitle="$name" />

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Ready to register') }}</div><div class="fs-3 fw-bold text-success">{{ $good }}</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Look like patients already here') }}</div><div class="fs-3 fw-bold text-warning-emphasis">{{ $duplicates }}</div></div></div></div>
    <div class="col-6 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('With a mistake (left out)') }}</div><div class="fs-3 fw-bold text-danger">{{ $errors }}</div></div></div></div>
</div>

<form method="POST" action="{{ route('patients.import.run') }}" class="card mb-3" data-confirm="{{ __('Register these patients?') }}" data-confirm-ok="{{ __('Register') }}">
    @csrf
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('3. The check') }}</h2></div>
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        @if ($duplicates)
            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="imp-dup" name="with_duplicates" value="1">
                <label class="form-check-label" for="imp-dup">{{ __('Register the :count that look like patients already here too', ['count' => $duplicates]) }}</label></div>
        @endif
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-light" href="{{ route('patients.import.map') }}">{{ __('Change the columns') }}</a>
            <button class="btn btn-primary" type="submit" @disabled($good + $duplicates === 0)><x-icon name="check" class="me-1" /> {{ __('Register :count patients', ['count' => $good]) }}</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>{{ __('Line') }}</th><th>{{ __('Check') }}</th>@foreach ($fields as $f)<th>{{ __($F[$f][0]) }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach (collect($checked)->sortBy(fn ($r) => $r['error'] ? 0 : ($r['duplicate'] ? 1 : 2))->take(500) as $r)
                    <tr class="{{ $r['error'] ? 'table-danger' : ($r['duplicate'] ? 'table-warning' : '') }}">
                        <td>{{ $r['line'] }}</td>
                        <td class="small">{{ $r['error'] ?? $r['duplicate'] ?? __('Ready') }}</td>
                        @foreach ($fields as $f)
                            <td class="small" dir="auto">{{ match ($f) { 'gender' => isset($r['data']['gender']) ? __(ucfirst($r['data']['gender'])) : '', 'age' => isset($r['data']['date_of_birth']) && ! empty($r['data']['dob_estimated']) ? \Illuminate\Support\Carbon::parse($r['data']['date_of_birth'])->age : '',
                                'date_of_birth' => isset($r['data']['date_of_birth']) && empty($r['data']['dob_estimated']) ? fmt_date($r['data']['date_of_birth']) : '', default => \Illuminate\Support\Str::limit((string) ($r['data'][$f] ?? ''), 40) } }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (count($checked) > 500)<div class="card-body small text-muted">{{ __('The first 500 rows are shown: the problems first.') }}</div>@endif
</div>
@endsection
