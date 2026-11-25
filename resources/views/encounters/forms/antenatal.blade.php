{{-- The pregnancy form: the due date and the weeks are worked out from the last period. --}}
@php
    $F = \App\Support\Forms::class;
    $x = (array) ($encounter->extra ?? []);
    $v = fn (string $k) => old('extra.'.$k, $x[$k] ?? null);
    $preg = $F::pregnancy($x['lmp'] ?? null, $encounter->started_at);
    $grades = $F::GRADES;
@endphp
@if ($preg)
    <div class="alert alert-info py-2 mb-2"><x-icon name="person-pregnant" class="me-1" />
        {{ __(':weeks weeks :days days pregnant; due on :edd.', ['weeks' => $preg['weeks'], 'days' => $preg['days'], 'edd' => fmt_date($preg['edd'])]) }}</div>
@endif
@if ($edit)
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4"><label class="form-label small" for="an-lmp">{{ __('First day of the last period') }}</label><x-date name="extra[lmp]" :value="$v('lmp')" id="an-lmp" /></div>
        @foreach (['gravida' => 'Pregnancies (G)', 'para' => 'Births (P)', 'abortions' => 'Miscarriages (A)'] as $k => $label)
            <div class="col-4 col-md-2"><label class="form-label small" for="an-{{ $k }}">{{ __($label) }}</label><input type="number" class="form-control" id="an-{{ $k }}" name="extra[{{ $k }}]" value="{{ $v($k) }}" min="0" max="25" dir="ltr"></div>
        @endforeach
        <div class="col-6 col-md-3"><label class="form-label small" for="an-fh">{{ __('Fundal height (cm)') }}</label><input type="number" step="0.5" class="form-control" id="an-fh" name="extra[fundal_height]" value="{{ $v('fundal_height') }}" min="5" max="50" dir="ltr"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="an-fhr">{{ __('Fetal heart (per minute)') }}</label><input type="number" class="form-control" id="an-fhr" name="extra[fetal_heart]" value="{{ $v('fetal_heart') }}" min="60" max="220" dir="ltr"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="an-pres">{{ __('Presentation') }}</label>
            <select class="form-select" id="an-pres" name="extra[presentation]"><option value=""></option>@foreach ($F::PRESENTATIONS as $k => $l)<option value="{{ $k }}" @selected($v('presentation') === $k)>{{ __($l) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="an-mov">{{ __('Fetal movements') }}</label>
            <select class="form-select" id="an-mov" name="extra[movements]"><option value=""></option><option value="yes" @selected($v('movements') === 'yes')>{{ __('Yes') }}</option><option value="no" @selected($v('movements') === 'no')>{{ __('No') }}</option></select></div>
        @foreach (['urine_protein' => 'Protein in urine', 'urine_sugar' => 'Sugar in urine', 'oedema' => 'Oedema'] as $k => $label)
            <div class="col-4"><label class="form-label small" for="an-{{ $k }}">{{ __($label) }}</label>
                <select class="form-select" id="an-{{ $k }}" name="extra[{{ $k }}]"><option value=""></option>@foreach ($grades as $g => $gl)<option value="{{ $g }}" @selected($v($k) === $g)>{{ $gl }}</option>@endforeach</select></div>
        @endforeach
    </div>
@elseif ($x)
    <dl class="row small mb-3">
        @foreach (['lmp' => 'First day of the last period', 'gravida' => 'Pregnancies (G)', 'para' => 'Births (P)', 'abortions' => 'Miscarriages (A)', 'fundal_height' => 'Fundal height (cm)',
            'fetal_heart' => 'Fetal heart (per minute)', 'presentation' => 'Presentation', 'movements' => 'Fetal movements', 'urine_protein' => 'Protein in urine', 'urine_sugar' => 'Sugar in urine', 'oedema' => 'Oedema'] as $k => $label)
            @if (filled($x[$k] ?? null))
                <dt class="col-6 col-md-4 fw-normal text-muted">{{ __($label) }}</dt>
                <dd class="col-6 col-md-2">{{ match ($k) { 'lmp' => fmt_date($x[$k]), 'presentation' => __($F::PRESENTATIONS[$x[$k]] ?? $x[$k]), 'movements' => __($x[$k] === 'yes' ? 'Yes' : 'No'),
                    'urine_protein', 'urine_sugar', 'oedema' => $grades[$x[$k]] ?? $x[$k], default => $x[$k] } }}</dd>
            @endif
        @endforeach
    </dl>
@endif
