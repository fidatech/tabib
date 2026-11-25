@extends('layouts.app', ['title' => __('Prescription').' · '.$patient->name])

@php
    $P = \App\Services\Prescribing::class;
    $units = collect($P::UNITS)->mapWithKeys(fn ($u, $k) => [$k => __($u[1])]);
    $freqs = collect($P::FREQUENCIES)->mapWithKeys(fn ($f, $k) => [$k => __($f[1])]);
    $timings = collect($P::TIMINGS)->mapWithKeys(fn ($t, $k) => [$k => __($t[0])]);
@endphp

@section('content')
<x-page-header :title="__('Prescription').' · '.$patient->name" icon="prescription-bottle-medical"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id])], [$rx->number ?? __('Draft')]]"
    :subtitle="$rx->doctor?->name().' · '.$patient->ageText().($rx->weight_kg ? ' · '.$rx->weight_kg.' kg' : '')">
    @if ($rx->status !== 'draft' && auth()->user()->can('prescriptions.manage') && \App\Services\Prescribing::doctorOf(auth()->id()))
        <form method="POST" action="{{ route('prescriptions.renew', ['id' => $rx->id]) }}">@csrf<button class="btn btn-outline-soft" type="submit"><x-icon name="rotate-right" class="me-1" /> {{ __('Write again') }}</button></form>
    @endif
    @if ($rx->isSigned())
        <a class="btn btn-outline-soft" href="{{ route('prescriptions.card', ['id' => $rx->id]) }}" target="_blank" rel="noopener"><x-icon name="id-card" class="me-1" /> {{ __('Medicine card') }}</a>
        <span class="badge bg-success fs-6 align-self-center"><x-icon name="lock" /> {{ $rx->number }}</span>
        @foreach (['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $code => $lang)
            <a class="btn btn-outline-soft" href="{{ route('prescriptions.print', ['id' => $rx->id, 'lang' => $code]) }}" target="_blank" rel="noopener" lang="{{ $code }}"><x-icon name="print" class="me-1" /> {{ $lang }}</a>
        @endforeach
    @elseif ($rx->status === 'cancelled')
        <span class="badge bg-danger fs-6 align-self-center">{{ __('Cancelled') }}</span>
    @else
        <span class="badge bg-warning text-dark fs-6 align-self-center">{{ __('Draft') }}</span>
    @endif
</x-page-header>

@include('patients.partials.allergy-banner')

@if ($rx->status === 'cancelled')
    <div class="alert alert-danger"><x-icon name="ban" class="me-1" /> {{ __('Cancelled on :date: :reason', ['date' => fmt_date($rx->cancelled_at), 'reason' => $rx->cancel_reason]) }}</div>
@elseif ($rx->isSigned() && ! $rx->signatureHolds())
    <div class="alert alert-danger"><x-icon name="triangle-exclamation" class="me-1" /> {{ __('This prescription was changed after it was signed. Tell the administrator of the clinic.') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-xl-8">
        {{-- the medicines --}}
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Medicines') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($rx->items as $item)
                    <div class="list-group-item">
                        <div class="d-flex gap-2 align-items-start">
                            <span class="rx-num">{{ $loop->iteration }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">{{ $item->label() }} @if ($item->medicine?->controlled)<span class="badge bg-danger">{{ __('Controlled') }}</span>@endif</div>
                                <div class="small">{{ $item->directions() }}</div>
                                @if ($item->quantity)<div class="small text-muted">{{ __('Give') }}: {{ $item->quantity }}</div>@endif
                            </div>
                            @if ($canWrite)
                                <button class="btn btn-sm btn-outline-soft" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $item->id }}" aria-label="{{ __('Change') }}" title="{{ __('Change') }}"><x-icon name="pen" /></button>
                                <form method="POST" action="{{ route('prescriptions.items.destroy', ['id' => $rx->id, 'item' => $item->id]) }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-icon name="trash" /></button></form>
                            @endif
                        </div>
                        @if ($canWrite)
                            <div class="collapse mt-2" id="edit-{{ $item->id }}">
                                <form method="POST" action="{{ route('prescriptions.items.update', ['id' => $rx->id, 'item' => $item->id]) }}" class="row g-2">@csrf @method('PUT')
                                    <input type="hidden" name="medicine_id" value="{{ $item->medicine_id }}"><input type="hidden" name="name" value="{{ $item->name }}">
                                    <input type="hidden" name="strength" value="{{ $item->strength }}"><input type="hidden" name="form" value="{{ $item->form }}">
                                    @include('prescriptions.partials.dose', ['i' => $item, 'id' => 'e'.$item->id])
                                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary" type="submit">{{ __('Save') }}</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-muted">{{ __('No medicine yet.') }}</div>
                @endforelse
            </div>
        </div>

        @if ($canWrite)
            {{-- add a medicine: found by its generic name or a brand --}}
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Add a medicine') }}</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('prescriptions.items.store', ['id' => $rx->id]) }}" class="row g-2" data-rx-add>
                        @csrf
                        <input type="hidden" name="medicine_id" data-med-id>
                        <input type="hidden" name="strength" data-med-strength><input type="hidden" name="form" data-med-form><input type="hidden" name="route" data-med-route>
                        <div class="col-12 position-relative" data-med-box data-url="{{ route('medicines.search') }}">
                            <label class="form-label fw-semibold" for="med-name">{{ __('Medicine') }}</label>
                            <input type="text" class="form-control" id="med-name" name="name" required autocomplete="off" data-med-text placeholder="{{ __('Generic name or brand: paracetamol, Panadol, Calpol ...') }}">
                            <div class="list-group med-results shadow" data-med-results hidden></div>
                            <div class="form-text" data-med-picked></div>
                        </div>
                        @include('prescriptions.partials.dose', ['i' => null, 'id' => 'n'])
                        <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button></div>
                    </form>
                </div>
            </div>
        @endif

        {{-- the checks, and signing --}}
        @if ($canWrite)
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Check and sign') }}</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('prescriptions.sign', ['id' => $rx->id]) }}" class="row g-2">
                        @csrf
                        @forelse ($warnings as $w)
                            <div class="col-12">
                                <div class="alert {{ $w['kind'] === 'allergy' ? 'alert-danger' : 'alert-warning' }} mb-1 py-2"><x-icon name="triangle-exclamation" class="me-1" /> {{ $w['message'] }}</div>
                                <label class="visually-hidden" for="r-{{ $w['key'] }}">{{ __('Why you go on') }}</label>
                                <input type="text" class="form-control form-control-sm" id="r-{{ $w['key'] }}" name="reasons[{{ $w['key'] }}]" required maxlength="255" placeholder="{{ __('Why you go on') }}" value="{{ old('reasons.'.$w['key']) }}">
                            </div>
                        @empty
                            <div class="col-12"><p class="text-success mb-1"><x-icon name="circle-check" class="me-1" /> {{ __('No warning found.') }}</p></div>
                        @endforelse
                        <div class="col-12"><p class="small text-muted mb-1">{{ __('The checks use the allergies and medicines on the chart, the weight, and the dose limits your clinic wrote. They do not replace your judgement.') }}</p></div>
                        <div class="col-12 col-md-6"><label class="form-label small" for="sign-password">{{ __('Your password') }}</label>
                            <input type="password" class="form-control" id="sign-password" name="password" required autocomplete="current-password"></div>
                        <div class="col-12 col-md-6 d-flex align-items-end"><button type="submit" class="btn btn-gradient w-100" @disabled($rx->items->isEmpty())><x-icon name="lock" class="me-1" /> {{ __('Sign the prescription') }}</button></div>
                    </form>
                </div>
            </div>
        @elseif ($rx->warnings->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Warnings seen when signing') }}</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($rx->warnings as $w)
                        <li class="list-group-item small"><x-icon name="triangle-exclamation" class="text-warning me-1" /> {{ $w->message }}<br><b>{{ __('Reason') }}:</b> {{ $w->reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canCancel)
            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('prescriptions.cancel', ['id' => $rx->id]) }}" class="row g-2 align-items-end">@csrf
                        <div class="col-12 col-md-8"><label class="form-label small" for="cancel-reason">{{ __('Cancel this prescription (it stays on the chart; its medicines are stopped)') }}</label>
                            <input type="text" class="form-control" id="cancel-reason" name="reason" required maxlength="255" placeholder="{{ __('Why') }}"></div>
                        <div class="col-12 col-md-4"><button class="btn btn-outline-danger w-100" type="submit"><x-icon name="ban" class="me-1" /> {{ __('Cancel the prescription') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('For the checks') }}</h2></div>
            <div class="card-body">
                @if ($canWrite)
                    <form method="POST" action="{{ route('prescriptions.update', ['id' => $rx->id]) }}" class="row g-2">@csrf @method('PUT')
                        <div class="col-12"><label class="form-label small" for="rx-weight">{{ __('Weight (kg)') }}</label>
                            <input type="number" step="0.1" min="0.5" max="350" class="form-control" id="rx-weight" name="weight_kg" value="{{ old('weight_kg', $rx->weight_kg) }}" dir="ltr"></div>
                        <div class="col-12"><label class="form-label small" for="rx-notes">{{ __('Note on the prescription') }}</label>
                            <textarea class="form-control" id="rx-notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $rx->notes) }}</textarea></div>
                        <div class="col-12 text-end"><button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Save') }}</button></div>
                    </form>
                @else
                    <p class="small mb-1">{{ __('Weight (kg)') }}: {{ $rx->weight_kg ?? '—' }}</p>
                    @if ($rx->notes)<p class="small mb-0">{{ $rx->notes }}</p>@endif
                @endif
                @if ($rx->signed_at)
                    <p class="small text-muted mt-2 mb-0"><x-icon name="lock" /> {{ __('Signed by :name on :date.', ['name' => $rx->signer?->name, 'date' => fmt_date($rx->signed_at).' '.$rx->signed_at->format('H:i')]) }}</p>
                @endif
                @if ($rx->encounter)
                    <p class="small mt-2 mb-0"><a href="{{ route('encounters.show', ['id' => $rx->encounter_id]) }}">{{ __('Visit of :date', ['date' => fmt_date($rx->encounter->started_at)]) }}</a></p>
                @endif
            </div>
        </div>

        @if ($canWrite)
            <div class="card mb-3">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('My sets') }}</h2></div>
                <div class="card-body">
                    @if ($sets->isNotEmpty())
                        <form method="POST" action="{{ route('prescriptions.sets.apply', ['id' => $rx->id]) }}" class="d-flex gap-2 mb-3">@csrf
                            <label class="visually-hidden" for="set-id">{{ __('Set') }}</label>
                            <select class="form-select form-select-sm" id="set-id" name="set_id">@foreach ($sets as $set)<option value="{{ $set->id }}">{{ $set->name }} ({{ count($set->items) }})</option>@endforeach</select>
                            <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">{{ __('Add the set') }}</button>
                        </form>
                    @endif
                    @if ($rx->items->isNotEmpty())
                        <form method="POST" action="{{ route('prescriptions.sets.store', ['id' => $rx->id]) }}" class="d-flex gap-2">@csrf
                            <label class="visually-hidden" for="set-name">{{ __('Name of the set') }}</label>
                            <input type="text" class="form-control form-control-sm" id="set-name" name="name" required maxlength="120" placeholder="{{ __('Save these medicines as a set ...') }}">
                            <button class="btn btn-sm btn-outline-soft text-nowrap" type="submit">{{ __('Save') }}</button>
                        </form>
                    @elseif ($sets->isEmpty())
                        <p class="small text-muted mb-0">{{ __('Medicines you often write together can be saved as a set.') }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@if ($canWrite)
<script>
(function () {
    var box = document.querySelector('[data-med-box]'); if (!box) return;
    var form = box.closest('form'), input = box.querySelector('[data-med-text]'), list = box.querySelector('[data-med-results]'), picked = box.querySelector('[data-med-picked]');
    var id = form.querySelector('[data-med-id]'), timer = null, last = 0;
    function set(sel, v) { var el = form.querySelector(sel); if (el) el.value = v || ''; }
    input.addEventListener('input', function () {
        id.value = ''; picked.textContent = ''; set('[data-med-strength]'); set('[data-med-form]'); set('[data-med-route]');
        var q = input.value.trim(), n = ++last; clearTimeout(timer);
        if (q.length < 2) { list.hidden = true; return; }
        timer = setTimeout(function () {
            fetch(box.getAttribute('data-url') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .then(function (d) {
                    if (n !== last) return;
                    list.innerHTML = '';
                    d.results.forEach(function (m) {
                        var b = document.createElement('button'); b.type = 'button'; b.className = 'list-group-item list-group-item-action small';
                        var t = document.createElement('b'); t.textContent = m.label; b.appendChild(t);
                        if (m.brands) { var s = document.createElement('span'); s.className = 'd-block text-muted'; s.textContent = m.brands; b.appendChild(s); }
                        b.addEventListener('click', function () {
                            input.value = m.name; id.value = m.id; set('[data-med-strength]', m.strength); set('[data-med-form]', m.form); set('[data-med-route]', m.route);
                            picked.textContent = m.label; list.hidden = true;
                            var u = form.querySelector('[name="dose_unit"]'); if (u && m.unit) u.value = m.unit;
                            var a = form.querySelector('[name="dose_amount"]'); if (a) a.focus();
                        });
                        list.appendChild(b);
                    });
                    list.hidden = d.results.length === 0;
                }).catch(function () {});
        }, 250);
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) list.hidden = true; });
})();
</script>
@endif
@endsection
