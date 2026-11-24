@extends('layouts.app', ['title' => __('Visit').' · '.$patient->name])

@section('content')
<x-page-header :title="__('Visit').' · '.$patient->name" icon="stethoscope"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id])], [fmt_date($encounter->started_at)]]"
    :subtitle="$encounter->doctor?->name().' · '.fmt_date($encounter->started_at).' '.$encounter->started_at->format('H:i')">
    @if ($encounter->isSigned())
        <span class="badge bg-success fs-6 align-self-center"><x-icon name="lock" /> {{ __('Signed') }}</span>
        <a class="btn btn-outline-soft" href="{{ route('encounters.show', ['id' => $encounter->id, 'print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @else
        <span class="badge bg-warning text-dark fs-6 align-self-center">{{ __('Draft') }}</span>
    @endif
</x-page-header>

@include('patients.partials.allergy-banner')

@if ($encounter->isSigned() && ! $encounter->signatureHolds())
    <div class="alert alert-danger"><x-icon name="triangle-exclamation" class="me-1" /> {{ __('This note was changed after it was signed. Tell the administrator of the clinic.') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-xl-8">
        {{-- the note --}}
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Note of the visit') }}</h2></div>
            <div class="card-body">
                @if ($canWrite)
                    <form method="POST" action="{{ route('encounters.update', ['id' => $encounter->id]) }}" id="noteForm">
                        @csrf @method('PUT')
                        @foreach (\App\Models\Encounter::PARTS as $part => $label)
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="n-{{ $part }}">{{ __($label) }}</label>
                                <textarea class="form-control @error($part) is-invalid @enderror" id="n-{{ $part }}" name="{{ $part }}" rows="{{ $part === 'complaint' ? 2 : 3 }}" data-autosave="note-{{ $encounter->id }}-{{ $part }}">{{ old($part, $encounter->{$part}) }}</textarea>
                            </div>
                        @endforeach
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="n-follow">{{ __('Come back on') }}</label>
                                <x-date name="follow_up_on" :value="old('follow_up_on', $encounter->follow_up_on?->toDateString())" id="n-follow" /></div>
                            <div class="col-12 col-md-8 text-md-end"><button type="submit" class="btn btn-outline-primary"><x-icon name="floppy-disk" class="me-1" /> {{ __('Save the draft') }}</button></div>
                        </div>
                    </form>
                @else
                    @foreach (\App\Models\Encounter::PARTS as $part => $label)
                        @if (filled($encounter->{$part}))
                            <h3 class="h6 text-muted mb-1">{{ __($label) }}</h3>
                            <p class="mb-3" style="white-space:pre-line">{{ $encounter->{$part} }}</p>
                        @endif
                    @endforeach
                    @if ($encounter->follow_up_on)<p class="mb-0"><b>{{ __('Come back on') }}:</b> {{ fmt_date($encounter->follow_up_on) }}</p>@endif
                @endif
            </div>
        </div>

        {{-- the diagnoses --}}
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Diagnoses') }}</h2></div>
            <ul class="list-group list-group-flush">
                @forelse ($encounter->diagnoses as $dx)
                    <li class="list-group-item d-flex align-items-center gap-2">
                        @if ($dx->icd10)<span class="badge bg-light text-dark" dir="ltr">{{ $dx->icd10 }}</span>@endif
                        <span class="flex-grow-1">{{ $dx->description }}</span>
                        @if ($dx->is_primary)<span class="badge bg-primary">{{ __('Main') }}</span>@endif
                        @if ($canWrite)
                            <form method="POST" action="{{ route('encounters.diagnoses.destroy', ['id' => $encounter->id, 'diagnosis' => $dx->id]) }}">@csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></form>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('None written.') }}</li>
                @endforelse
            </ul>
            @if ($canWrite)
                <div class="card-footer">
                    <form method="POST" action="{{ route('encounters.diagnoses.store', ['id' => $encounter->id]) }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="icd10" data-icd10-code>
                        <div class="col-12 col-md-8">
                            <label class="form-label small" for="dx-text">{{ __('Diagnosis') }}</label>
                            <div class="icd10-pick" data-icd10 data-url="{{ route('icd10.search') }}">
                                <input type="text" class="form-control form-control-sm" id="dx-text" name="description" maxlength="255" required autocomplete="off" placeholder="{{ __('Type a diagnosis or an ICD-10 code') }}" data-icd10-text>
                                <div class="list-group icd10-results" data-icd10-results hidden></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 d-flex gap-2 align-items-center">
                            <div class="form-check small mb-0"><input class="form-check-input" type="checkbox" id="dx-problem" name="to_problems" value="1"><label class="form-check-label" for="dx-problem">{{ __('Long-standing: add to the problems') }}</label></div>
                            <button type="submit" class="btn btn-sm btn-primary ms-auto" aria-label="{{ __('Add the diagnosis') }}" title="{{ __('Add the diagnosis') }}"><x-icon name="plus" /></button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- vital signs of this visit --}}
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Vital signs') }}</h2></div>
            <div class="card-body">
                @foreach ($encounter->vitals as $v)
                    <p class="small mb-2 {{ $v->status === 'entered_in_error' ? 'text-decoration-line-through text-muted' : '' }}"><b dir="ltr">{{ $v->taken_at->format('H:i') }}</b> · {{ $v->summary() }}</p>
                @endforeach
                @if ($canWrite)
                    @include('patients.partials.vitals-form', ['encounterId' => $encounter->id])
                @elseif ($encounter->vitals->isEmpty())
                    <p class="text-muted small mb-0">{{ __('None written.') }}</p>
                @endif
            </div>
        </div>

        {{-- signing, or what was added after --}}
        @if ($canWrite)
            <div class="card mb-3 border-primary">
                <div class="card-body">
                    <form method="POST" action="{{ route('encounters.sign', ['id' => $encounter->id]) }}" class="row g-2 align-items-end" data-sign-form>
                        @csrf
                        <div class="col-12"><p class="mb-2"><x-icon name="signature" class="me-1" /> {{ __('When the note is complete, sign it with your password. A signed visit is locked: later changes are added as addenda.') }}</p></div>
                        <div class="col-12 col-md-6"><label class="form-label small" for="sign-password">{{ __('Your password') }}</label>
                            <input type="password" class="form-control" id="sign-password" name="password" required autocomplete="current-password"></div>
                        <div class="col-12 col-md-6"><button type="submit" class="btn btn-gradient w-100"><x-icon name="lock" class="me-1" /> {{ __('Save and sign') }}</button></div>
                    </form>
                </div>
            </div>
        @elseif ($encounter->isSigned())
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Addenda') }}</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($encounter->addenda as $ad)
                        <li class="list-group-item"><span class="small text-muted">{{ fmt_date($ad->created_at) }} {{ $ad->created_at->format('H:i') }} · {{ $ad->author?->name }}</span>
                            <p class="mb-0" style="white-space:pre-line">{{ $ad->body }}</p></li>
                    @empty
                        <li class="list-group-item text-muted small">{{ __('Nothing added.') }}</li>
                    @endforelse
                </ul>
                @if ($canAdd)
                    <div class="card-footer">
                        <form method="POST" action="{{ route('encounters.addenda.store', ['id' => $encounter->id]) }}">
                            @csrf
                            <label class="form-label small" for="ad-body">{{ __('Add to this visit') }}</label>
                            <textarea class="form-control mb-2" id="ad-body" name="body" rows="2" required maxlength="5000"></textarea>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button>
                        </form>
                    </div>
                @endif
            </div>
            <p class="small text-muted"><x-icon name="lock" /> {{ __('Signed by :name on :date.', ['name' => $encounter->signer?->name, 'date' => fmt_date($encounter->signed_at).' '.$encounter->signed_at?->format('H:i')]) }}</p>
        @endif
    </div>

    {{-- what the doctor wants beside the note --}}
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h6 card-title mb-0">{{ __('Active problems') }}</h2></div>
            <ul class="list-group list-group-flush small">
                @forelse ($problems as $p)<li class="list-group-item">{{ $p->summary() }}</li>@empty<li class="list-group-item text-muted">{{ __('None written.') }}</li>@endforelse
            </ul>
        </div>
        <div class="card mb-3">
            <div class="card-header"><h2 class="h6 card-title mb-0">{{ __('Medicines taken now') }}</h2></div>
            <ul class="list-group list-group-flush small">
                @forelse ($medications as $m)<li class="list-group-item">{{ $m->summary() }}</li>@empty<li class="list-group-item text-muted">{{ __('None written.') }}</li>@endforelse
            </ul>
        </div>
        @if ($lastVital)
            <div class="card mb-3"><div class="card-body small"><b>{{ __('Last vital signs') }}</b> · {{ fmt_date($lastVital->taken_at) }}<br>{{ $lastVital->summary() }}</div></div>
        @endif
        <div class="card mb-3">
            <div class="card-header"><h2 class="h6 card-title mb-0">{{ __('Earlier visits') }}</h2></div>
            <div class="list-group list-group-flush small">
                @forelse ($previous as $e)
                    <a class="list-group-item list-group-item-action" href="{{ route('encounters.show', ['id' => $e->id]) }}"><b>{{ fmt_date($e->started_at) }}</b> · {{ $e->doctor?->name() }}
                        <span class="d-block text-muted text-truncate">{{ $e->diagnoses->map->label()->implode(', ') ?: $e->complaint }}</span></a>
                @empty
                    <div class="list-group-item text-muted">{{ __('No visit yet.') }}</div>
                @endforelse
            </div>
        </div>
        <a class="btn btn-outline-soft w-100" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'history']) }}"><x-icon name="notes-medical" class="me-1" /> {{ __('Full history') }}</a>
    </div>
</div>

@if ($canWrite)
    @include('partials.icd10-search')
    {{-- the note is kept in the browser as it is typed, so a power cut or a lost connection loses nothing; signing sends the note with it --}}
    <script>
    (function () {
        var boxes = document.querySelectorAll('[data-autosave]'), store = null;
        try { store = window.localStorage; } catch (e) { return; }
        boxes.forEach(function (box) {
            var key = 'tabib-' + box.getAttribute('data-autosave'), kept = null;
            try { kept = store.getItem(key); } catch (e) {}
            if (kept !== null && kept !== box.value && box.value === box.defaultValue) box.value = kept;
            box.addEventListener('input', function () { try { store.setItem(key, box.value); } catch (e) {} });
        });
        var forget = function () { boxes.forEach(function (b) { try { store.removeItem('tabib-' + b.getAttribute('data-autosave')); } catch (e) {} }); };
        var note = document.getElementById('noteForm'), sign = document.querySelector('[data-sign-form]');
        if (note) note.addEventListener('submit', forget);
        if (sign && note) sign.addEventListener('submit', function () {
            // what is in the boxes is signed, even when "Save the draft" was not pressed
            boxes.forEach(function (b) { var h = document.createElement('input'); h.type = 'hidden'; h.name = b.name; h.value = b.value; sign.appendChild(h); });
            var f = note.querySelector('[name="follow_up_on"]'); if (f) { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'follow_up_on'; h.value = f.value; sign.appendChild(h); }
            note.querySelectorAll('[name="_shamsi[]"]').forEach(function (s) { var h = document.createElement('input'); h.type = 'hidden'; h.name = '_shamsi[]'; h.value = s.value; sign.appendChild(h); });
            forget();
        });
    })();
    </script>
@endif
@endsection
