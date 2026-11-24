@extends('layouts.app', ['title' => $patient->name])

@php
    $canBook = auth()->user()->can('appointments.manage');
    $facts = array_filter([
        [__('Record number'), $patient->number, 'hashtag'],
        [__('Gender'), __(\App\Models\Patient::GENDERS[$patient->gender] ?? $patient->gender), 'venus-mars'],
        [__('Age'), $patient->ageText() ?: null, 'cake-candles'],
        [__('Date of birth'), $patient->date_of_birth && ! $patient->dob_estimated ? fmt_date($patient->date_of_birth) : null, 'calendar-day'],
        [__('Phone'), $patient->phone, 'phone'],
        [__('Blood group'), $patient->blood_group, 'droplet'],
        [__('Tazkira number'), $patient->tazkira, 'id-card'],
    ], fn (array $f) => filled($f[1]));
    $noneKnown = in_array(mb_strtolower(trim((string) $patient->allergies)), ['none', 'none known', 'nkda', 'no', '-', 'ندارد', 'نشته'], true);
@endphp

@section('content')
<x-page-header :title="$patient->name" icon="hospital-user" :crumbs="[[__('Patients'), route('patients.index')], [$patient->number]]"
    :subtitle="$patient->father_name ? __('son/daughter of').' '.$patient->father_name : null">
    @can('patients.manage')
        <a class="btn btn-outline-soft" href="{{ route('patients.edit', ['id' => $patient->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>
    @endcan
    @if ($canBook)
        <a class="btn btn-outline-soft" href="{{ route('appointments.create', ['patient' => $patient->id, 'walk_in' => 1]) }}"><x-icon name="person-walking" class="me-1" /> {{ __('Walk-in') }}</a>
        <a class="btn btn-primary" href="{{ route('appointments.create', ['patient' => $patient->id]) }}"><x-icon name="calendar-plus" class="me-1" /> {{ __('Book an appointment') }}</a>
    @endif
</x-page-header>

{{-- the allergies stay on top of the patient's page, in red, so nobody misses them --}}
@if (filled($patient->allergies) && ! $noneKnown)
    <div class="alert alert-danger d-flex gap-2 align-items-start patient-allergy" role="alert">
        <x-icon name="triangle-exclamation" class="mt-1" />
        <div><strong>{{ __('Allergies') }}:</strong> {{ $patient->allergies }}</div>
    </div>
@elseif ($noneKnown)
    <div class="alert alert-success py-2 small" role="note"><x-icon name="circle-check" class="me-1" /> {{ __('No known allergies') }}</div>
@else
    <div class="alert alert-warning py-2 small" role="note"><x-icon name="circle-info" class="me-1" /> {{ __('Allergies not asked yet.') }} @can('patients.manage')<a href="{{ route('patients.edit', ['id' => $patient->id]) }}">{{ __('Write them') }}</a>@endcan</div>
@endif

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('About the patient') }}</h2></div>
            <div class="card-body">
                <dl class="patient-facts">
                    @foreach ($facts as [$label, $value, $icon])
                        <div><dt><x-icon :name="$icon" /> {{ $label }}</dt><dd @if (in_array($icon, ['phone', 'hashtag', 'id-card'], true)) dir="ltr" @endif>{{ $value }}</dd></div>
                    @endforeach
                    @if ($patient->address || $patient->province)
                        <div><dt><x-icon name="location-dot" /> {{ __('Address') }}</dt><dd>{{ implode(', ', array_filter([$patient->address, $patient->province])) }}</dd></div>
                    @endif
                    @if ($patient->emergency_name || $patient->emergency_phone)
                        <div><dt><x-icon name="user-shield" /> {{ __('Emergency contact') }}</dt><dd>{{ $patient->emergency_name }} <span dir="ltr">{{ $patient->emergency_phone }}</span></dd></div>
                    @endif
                </dl>
                @if ($patient->notes)
                    <p class="small text-muted mb-0 mt-3" style="white-space:pre-line">{{ $patient->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Coming appointments') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($upcoming as $a)
                    @include('appointments.row', ['a' => $a, 'showDate' => true])
                @empty
                    <div class="text-center text-muted small py-4">{{ __('No coming appointment.') }}</div>
                @endforelse
            </div>
        </div>

        <div class="card mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Visits') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($past as $a)
                    @include('appointments.row', ['a' => $a, 'showDate' => true])
                @empty
                    <div class="text-center text-muted small py-4">{{ __('No visit yet.') }}</div>
                @endforelse
            </div>
            <div class="card-footer small text-muted">{{ __('The full history (problems, medicines, vitals, notes) comes with the next part of the system.') }}</div>
        </div>
    </div>
</div>
@endsection
