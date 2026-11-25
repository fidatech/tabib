@extends('layouts.app', ['title' => __(\App\Models\Letter::KINDS[$kind][0]).' · '.$patient->name])

@section('content')
<x-page-header :title="__(\App\Models\Letter::KINDS[$kind][0]).' · '.$patient->name" icon="envelope-open-text"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id])], [__(\App\Models\Letter::KINDS[$kind][0])]]" />

{{-- the kind, the language and the days change the starting words: they are chosen first --}}
<form method="GET" class="card card-body mb-3 row g-2 flex-row align-items-end mx-0">
    <input type="hidden" name="patient" value="{{ $patient->id }}"><input type="hidden" name="visit" value="{{ $visit?->id }}">
    <div class="col-12 col-md-4"><label class="form-label small" for="l-kind">{{ __('Kind') }}</label>
        <select class="form-select" id="l-kind" name="kind">@foreach (\App\Models\Letter::KINDS as $k => $kk)<option value="{{ $k }}" @selected($kind === $k)>{{ __($kk[0]) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small" for="l-lang">{{ __('Language') }}</label>
        <select class="form-select" id="l-lang" name="lang">@foreach (['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $k => $l)<option value="{{ $k }}" @selected($lang === $k)>{{ $l }}</option>@endforeach</select></div>
    @if ($kind === 'sick_leave')
        <div class="col-6 col-md-2"><label class="form-label small" for="l-from">{{ __('Rest from') }}</label><x-date name="from" :value="$from" id="l-from" /></div>
        <div class="col-6 col-md-2"><label class="form-label small" for="l-to">{{ __('Rest until') }}</label><x-date name="to" :value="$to" id="l-to" /></div>
    @endif
    <div class="col-6 col-md-2"><button class="btn btn-light-secondary w-100" type="submit">{{ __('Write again') }}</button></div>
</form>

<form method="POST" action="{{ route('letters.store') }}" class="card">
    @csrf
    <input type="hidden" name="patient_id" value="{{ $patient->id }}"><input type="hidden" name="encounter_id" value="{{ $visit?->id }}">
    <input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="lang" value="{{ $lang }}">
    @if ($kind === 'sick_leave')<input type="hidden" name="rest_from" value="{{ $from }}"><input type="hidden" name="rest_to" value="{{ $to }}">@endif
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label" for="l-to-who">{{ $kind === 'referral' ? __('Referred to (doctor, hospital)') : __('Addressed to (optional)') }}</label>
            <input type="text" class="form-control" id="l-to-who" name="addressed_to" value="{{ old('addressed_to') }}" maxlength="200" @required($kind === 'referral')></div>
        <div class="col-12"><label class="form-label" for="l-body">{{ __('Text') }}</label>
            <textarea class="form-control" id="l-body" name="body" rows="12" maxlength="5000" required dir="{{ $lang === 'en' ? 'ltr' : 'rtl' }}" lang="{{ $lang }}">{{ old('body', $body) }}</textarea>
            <div class="form-text">{{ __('Check and change the words. Once given, a letter is not changed: it can only be cancelled.') }}</div></div>
        <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><x-icon name="print" class="me-1" /> {{ __('Give and print') }}</button></div>
    </div>
</form>
@endsection
