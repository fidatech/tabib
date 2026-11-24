@extends('layouts.app', ['title' => $patient->name])

@php
    $canBook = auth()->user()->can('appointments.manage');
    $tabNames = [
        'summary' => ['Summary', 'gauge-high'], 'timeline' => ['Timeline', 'timeline'], 'history' => ['History', 'notes-medical'],
        'vitals' => ['Vital signs', 'heart-pulse'], 'visits' => ['Visits', 'stethoscope'], 'documents' => ['Documents', 'paperclip'],
        'appointments' => ['Appointments', 'calendar-days'],
    ];
    $facts = array_filter([
        ['hashtag', $patient->number, true],
        ['venus-mars', __(\App\Models\Patient::GENDERS[$patient->gender] ?? $patient->gender), false],
        ['cake-candles', $patient->ageText(), true],
        ['phone', $patient->phone, true],
        ['droplet', $patient->blood_group, true],
    ], fn (array $f) => filled($f[1]));
@endphp

@section('content')
<x-page-header :title="$patient->name" icon="hospital-user" :crumbs="[[__('Patients'), route('patients.index')], [$patient->number]]"
    :subtitle="$patient->father_name ? __('son/daughter of').' '.$patient->father_name : null">
    @can('patients.manage')
        <a class="btn btn-outline-soft" href="{{ route('patients.edit', ['id' => $patient->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
    @endcan
    @if ($isDoctor && $canWrite)
        <form method="POST" action="{{ route('encounters.start') }}">@csrf<input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <button type="submit" class="btn btn-outline-soft"><x-icon name="stethoscope" class="me-1" /> {{ __('New visit') }}</button></form>
    @endif
    @if ($canBook)
        <a class="btn btn-primary" href="{{ route('appointments.create', ['patient' => $patient->id]) }}"><x-icon name="calendar-plus" class="me-1" /> {{ __('Book an appointment') }}</a>
    @endif
</x-page-header>

{{-- who he is, in one line, on every tab --}}
<div class="patient-strip mb-3">
    @foreach ($facts as [$icon, $value, $ltr])
        <span><x-icon :name="$icon" /> <b @if ($ltr) dir="ltr" @endif>{{ $value }}</b></span>
    @endforeach
</div>

@include('patients.partials.allergy-banner')

<nav aria-label="{{ __('Parts of the chart') }}"><ul class="nav nav-tabs chart-tabs mb-3">
    @foreach ($tabs as $key)
        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif href="{{ route('patients.show', ['id' => $patient->id, 'tab' => $key]) }}" aria-label="{{ __($tabNames[$key][0]) }}" title="{{ __($tabNames[$key][0]) }}"><x-icon :name="$tabNames[$key][1]" /> <span>{{ __($tabNames[$key][0]) }}</span></a></li>
    @endforeach
</ul></nav>

@include('patients.tabs.'.$tab)
@endsection
