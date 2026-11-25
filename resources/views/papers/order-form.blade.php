@extends('layouts.app', ['title' => __(\App\Models\Order::KINDS[$kind]).' · '.$patient->name])

@section('content')
<x-page-header :title="($kind === 'lab' ? __('Request lab tests') : __('Request imaging')).' · '.$patient->name" :icon="$kind === 'lab' ? 'flask' : 'x-ray'"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id])], [__(\App\Models\Order::KINDS[$kind])]]">
    <a class="btn btn-outline-soft" href="{{ route('orders.create', ['patient' => $patient->id, 'visit' => $visit?->id, 'kind' => $kind === 'lab' ? 'imaging' : 'lab']) }}">{{ $kind === 'lab' ? __('Imaging instead') : __('Lab tests instead') }}</a>
</x-page-header>

<form method="POST" action="{{ route('orders.store') }}" class="card">
    @csrf
    <input type="hidden" name="patient_id" value="{{ $patient->id }}"><input type="hidden" name="encounter_id" value="{{ $visit?->id }}"><input type="hidden" name="kind" value="{{ $kind }}">
    <div class="card-body">
        <div class="row g-3">
            @foreach (\App\Models\Order::COMMON[$kind] as $group => $tests)
                <fieldset class="col-12 col-md-6 col-xl-4 test-pick">
                    <legend class="h6 fw-semibold">{{ __($group) }}</legend>
                    @foreach ($tests as $test)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="tests[]" value="{{ $test }}" id="t-{{ md5($test) }}" @checked(in_array($test, (array) old('tests', []), true))>
                            <label class="form-check-label" for="t-{{ md5($test) }}" dir="ltr">{{ $test }}</label></div>
                    @endforeach
                </fieldset>
            @endforeach
            <div class="col-12"><label class="form-label" for="o-other">{{ __('Other tests (separate with commas)') }}</label>
                <input type="text" class="form-control" id="o-other" name="other" value="{{ old('other') }}" maxlength="300" dir="ltr"></div>
            <div class="col-12 col-md-8"><label class="form-label" for="o-note">{{ __('What the lab or radiologist should know') }}</label>
                <input type="text" class="form-control" id="o-note" name="clinical_note" value="{{ old('clinical_note', $visit?->diagnoses->map->label()->implode('; ')) }}" maxlength="500"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="o-urgency">{{ __('Urgency') }}</label>
                <select class="form-select" id="o-urgency" name="urgency"><option value="routine">{{ __('Routine') }}</option><option value="urgent" @selected(old('urgency') === 'urgent')>{{ __('Urgent') }}</option></select></div>
            <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><x-icon name="file-medical" class="me-1" /> {{ __('Make the request') }}</button></div>
        </div>
    </div>
</form>
@endsection
