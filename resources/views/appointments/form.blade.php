@php
    $title = $appointment ? __('Move the appointment') : ($walkIn ? __('Walk-in patient') : __('New appointment'));
    $chosenPatient = old('patient_id', $patient?->id);
    $patientText = $patient ? $patient->name.($patient->father_name ? ' '.__('son/daughter of').' '.$patient->father_name : '') : '';
    $patientMeta = $patient ? implode(' · ', array_filter([$patient->number, $patient->ageText(), $patient->phone])) : '';
@endphp
@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" :icon="$walkIn ? 'person-walking' : 'calendar-plus'" :crumbs="[[__('Appointments'), route('appointments.index')], [$title]]" />

@if ($doctors->isEmpty())
    <div class="alert alert-warning">{{ __('Add the doctors of the clinic first; each gets a column on this calendar.') }}
        @can('users.manage')<a href="{{ route('doctors.create') }}">{{ __('Add a doctor') }}</a>@endcan</div>
@endif

<form method="POST" action="{{ $appointment ? route('appointments.update', ['id' => $appointment->id]) : route('appointments.store') }}" class="card">
    @csrf
    @if ($appointment) @method('PUT') @endif
    @if ($walkIn)<input type="hidden" name="walk_in" value="1">@endif
    <div class="card-body">
        @if ($walkIn)
            <p class="text-muted">{{ __('The patient is here now without an appointment: he gets the next token and waits in the queue of the doctor.') }}</p>
        @endif
        <div class="row g-3">
            {{-- the patient: typed and found as he is typed (name, father, phone, record number or tazkira) --}}
            <div class="col-12">
                <label class="form-label" for="patientSearch">{{ __('Patient') }} <span class="text-danger">*</span></label>
                <div class="patient-pick" data-patient-pick data-url="{{ route('patients.search') }}" data-empty="{{ __('No patient found.') }}">
                    <input type="hidden" name="patient_id" value="{{ $chosenPatient }}" data-patient-id>
                    <div class="patient-chosen {{ $chosenPatient ? '' : 'd-none' }}" data-patient-chosen>
                        <x-icon name="hospital-user" /> <strong data-patient-text>{{ old('patient_id') && ! $patient ? '' : $patientText }}</strong> <span class="small text-muted" data-patient-meta>{{ $patientMeta }}</span>
                        @unless ($appointment)<button type="button" class="btn btn-sm btn-link" data-patient-clear>{{ __('Change') }}</button>@endunless
                    </div>
                    <div class="{{ $chosenPatient ? 'd-none' : '' }}" data-patient-find>
                        <input type="search" id="patientSearch" class="form-control @error('patient_id') is-invalid @enderror" autocomplete="off"
                            placeholder="{{ __('Name, phone or record number') }}" aria-describedby="patientHelp" @if (! $chosenPatient) autofocus @endif>
                        <div class="list-group patient-results" data-patient-results role="listbox" hidden></div>
                        @error('patient_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text" id="patientHelp">
                            {{ __('Type at least two letters.') }}
                            @can('patients.manage')<a href="{{ route('patients.create') }}">{{ __('New patient? Register him first.') }}</a>@endcan
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="doctor_id">{{ __('Doctor') }} <span class="text-danger">*</span></label>
                <select class="form-select @error('doctor_id') is-invalid @enderror" id="doctor_id" name="doctor_id" required data-plain>
                    <option value="">—</option>
                    @foreach ($doctors as $d)
                        <option value="{{ $d->id }}" @selected((int) old('doctor_id', $doctorId) === $d->id)>{{ $d->name() }}{{ $d->specialty ? ' · '.$d->specialty : '' }}</option>
                    @endforeach
                </select>
                @error('doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="appointment_type_id">{{ __('Appointment type') }}</label>
                <select class="form-select @error('appointment_type_id') is-invalid @enderror" id="appointment_type_id" name="appointment_type_id" data-plain>
                    <option value="" data-minutes="">—</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}" data-minutes="{{ $t->minutes }}" @selected((int) old('appointment_type_id', $appointment?->appointment_type_id) === $t->id)>{{ $t->label() }} · {{ __(':count minutes', ['count' => $t->minutes]) }}</option>
                    @endforeach
                </select>
                @error('appointment_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @unless ($walkIn)
                <div class="col-6 col-md-4">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', $date)" :required="true" id="f-date" class="{{ $errors->has('date') ? 'is-invalid' : '' }}" />
                    @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="time">{{ __('Time') }} <span class="text-danger">*</span></label>
                    <input type="time" class="form-control @error('time') is-invalid @enderror" id="time" name="time" value="{{ old('time', $time) }}" step="300" required dir="ltr">
                    @error('time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="minutes">{{ __('Minutes') }}</label>
                    <input type="number" class="form-control @error('minutes') is-invalid @enderror" id="minutes" name="minutes" min="5" max="480" step="5" dir="ltr"
                        value="{{ old('minutes', $appointment ? (int) $appointment->starts_at->diffInMinutes($appointment->ends_at) : '') }}" placeholder="{{ setting('slot_minutes', '15') }}">
                    @error('minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @unless ($appointment)
                    {{-- a course: physiotherapy, dressings, injections ... --}}
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="repeat">{{ __('Repeat') }}</label>
                        <select class="form-select" id="repeat" name="repeat" data-repeat>
                            <option value="">{{ __('Only once') }}</option>
                            <option value="daily" @selected(old('repeat') === 'daily')>{{ __('Every day') }}</option>
                            <option value="weekly" @selected(old('repeat') === 'weekly')>{{ __('Every week') }}</option>
                            <option value="every" @selected(old('repeat') === 'every')>{{ __('Every few days') }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4" data-repeat-show>
                        <label class="form-label" for="repeat_every">{{ __('Every how many days') }}</label>
                        <input type="number" class="form-control" id="repeat_every" name="repeat_every" min="1" max="60" value="{{ old('repeat_every', 2) }}" dir="ltr">
                    </div>
                    <div class="col-6 col-md-4" data-repeat-show>
                        <label class="form-label" for="repeat_count">{{ __('How many appointments') }}</label>
                        <input type="number" class="form-control @error('repeat_count') is-invalid @enderror" id="repeat_count" name="repeat_count" min="2" max="30" value="{{ old('repeat_count', 6) }}" dir="ltr">
                    </div>
                @endunless
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="outside_hours" value="0">
                        <input class="form-check-input" type="checkbox" id="outside_hours" name="outside_hours" value="1" @checked(old('outside_hours', $appointment?->outside_hours))>
                        <label class="form-check-label" for="outside_hours">{{ __('Book outside the hours of the doctor (he agreed)') }}</label>
                    </div>
                </div>
            @endunless

            <div class="col-12">
                <label class="form-label" for="reason">{{ __('Why does the patient come?') }}</label>
                <input type="text" class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" maxlength="255" value="{{ old('reason', $appointment?->reason) }}" placeholder="{{ __('For example: fever for 3 days') }}">
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('appointments.index') }}">{{ __('Back') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ $walkIn ? __('Put in the queue') : ($appointment ? __('Move') : __('Book')) }}</button>
    </div>
</form>

<script>
/* the patient box: asks the server as letters are typed, and keeps the chosen patient's number */
(function () {
    var box = document.querySelector('[data-patient-pick]'); if (!box) return;
    var input = box.querySelector('#patientSearch'), list = box.querySelector('[data-patient-results]'), id = box.querySelector('[data-patient-id]');
    var chosen = box.querySelector('[data-patient-chosen]'), find = box.querySelector('[data-patient-find]'), timer = null, last = 0;
    var pick = function (row) {
        id.value = row.id; box.querySelector('[data-patient-text]').textContent = row.text; box.querySelector('[data-patient-meta]').textContent = row.meta;
        chosen.classList.remove('d-none'); find.classList.add('d-none'); list.hidden = true;
        var doctor = document.getElementById('doctor_id'); if (doctor && !doctor.value) doctor.focus();
    };
    var clear = box.querySelector('[data-patient-clear]');
    if (clear) clear.addEventListener('click', function () { id.value = ''; chosen.classList.add('d-none'); find.classList.remove('d-none'); input.value = ''; input.focus(); });
    input.addEventListener('input', function () {
        var q = input.value.trim(), n = ++last; clearTimeout(timer);
        if (q.length < 2) { list.hidden = true; return; }
        timer = setTimeout(function () {
            fetch(box.getAttribute('data-url') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .then(function (d) {
                    if (n !== last) return;
                    list.innerHTML = '';
                    if (!d.results.length) { var e = document.createElement('div'); e.className = 'list-group-item small text-muted'; e.textContent = box.getAttribute('data-empty'); list.appendChild(e); }
                    d.results.forEach(function (row) {
                        var b = document.createElement('button'); b.type = 'button'; b.className = 'list-group-item list-group-item-action'; b.setAttribute('role', 'option');
                        var s = document.createElement('strong'); s.textContent = row.text; var m = document.createElement('span'); m.className = 'd-block small text-muted'; m.textContent = row.meta;
                        b.appendChild(s); b.appendChild(m); b.addEventListener('click', function () { pick(row); }); list.appendChild(b);
                    });
                    list.hidden = false;
                }).catch(function () {});
        }, 250);
    });
    // the length of the chosen type, as a hint in the minutes box
    var type = document.getElementById('appointment_type_id'), minutes = document.getElementById('minutes');
    if (type && minutes) type.addEventListener('change', function () { var m = type.options[type.selectedIndex].getAttribute('data-minutes'); if (m) minutes.placeholder = m; });
})();
(function () {
    var r = document.querySelector('[data-repeat]'); if (!r) return;
    function show() {
        document.querySelectorAll('[data-repeat-show]').forEach(function (el, i) { el.hidden = r.value === '' || (i === 0 && r.value !== 'every'); });
    }
    r.addEventListener('change', show); show();
})();
</script>
@endsection
