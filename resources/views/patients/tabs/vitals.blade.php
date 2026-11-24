{{-- The vital signs: a form for new ones, a small line for each measure, and every set taken. --}}
@php $measures = \App\Models\Vital::MEASURES; @endphp
@if ($canWrite)
    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Take vital signs') }}</h2></div>
        <div class="card-body">
            @include('patients.partials.vitals-form', ['encounterId' => null])
        </div>
    </div>
@endif

@if ($trends)
    <div class="row g-3 mb-3">
        @foreach ($trends as $m => $points)
            @php $line = \App\Support\Chart::sparkline($points); $lastValue = $points->last()['value']; @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 mb-0 vital-trend">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="fw-semibold">{{ __($measures[$m][0]) }}</span>
                            <span class="vital-trend-last" dir="ltr">{{ $lastValue + 0 }} <small>{{ $measures[$m][1] }}</small></span>
                        </div>
                        <svg viewBox="0 0 240 60" class="vital-spark" role="img" aria-label="{{ __($measures[$m][0]) }}: {{ $points->pluck('value')->map(fn ($v) => $v + 0)->implode(', ') }}">
                            <polyline points="{{ $line['points'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                            <circle cx="{{ $line['last'][0] }}" cy="{{ $line['last'][1] }}" r="3.5" fill="currentColor"/>
                        </svg>
                        <div class="d-flex justify-content-between small text-muted" dir="ltr"><span>{{ fmt_date($points->first()['at']) }}</span><span>{{ __('low') }} {{ $line['min'] + 0 }} · {{ __('high') }} {{ $line['max'] + 0 }}</span></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('All vital signs') }}</h2></div>
    <div class="card-body table-responsive">
        @if ($vitals->isEmpty())
            <p class="text-muted mb-0">{{ __('None written.') }}</p>
        @else
            <table class="table table-sm align-middle">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('BP') }}</th><th>{{ __('Pulse') }}</th><th>{{ __('Temperature') }}</th><th>SpO₂</th><th>{{ __('Weight') }}</th><th>{{ __('Height') }}</th><th>{{ __('BMI') }}</th><th>{{ __('Blood sugar') }}</th><th>{{ __('By') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($vitals as $v)
                        <tr class="{{ $v->status === 'entered_in_error' ? 'history-entry is-error' : '' }}">
                            <td class="text-nowrap">{{ fmt_date($v->taken_at) }} <span class="text-muted" dir="ltr">{{ $v->taken_at->format('H:i') }}</span></td>
                            <td dir="ltr">{{ $v->bloodPressure() }}</td><td dir="ltr">{{ $v->pulse }}</td><td dir="ltr">{{ $v->temperature }}</td><td dir="ltr">{{ $v->spo2 }}</td>
                            <td dir="ltr">{{ $v->weight }}</td><td dir="ltr">{{ $v->height }}</td><td dir="ltr">{{ $v->bmi() }}</td><td dir="ltr">{{ $v->blood_sugar }}</td>
                            <td class="small">{{ $v->author?->name }}</td>
                            <td>
                                @if ($v->status === 'entered_in_error')
                                    <span class="small text-danger">{{ __('Entered in error') }}: {{ $v->error_reason }}</span>
                                @elseif ($canWrite)
                                    <details class="small"><summary>{{ __('Mistake?') }}</summary>
                                        <form method="POST" action="{{ route('history.error', ['patient' => $patient->id, 'section' => 'vitals', 'id' => $v->id]) }}" class="d-flex gap-1 mt-1">@csrf
                                            <input type="text" name="reason" class="form-control form-control-sm" required maxlength="255" placeholder="{{ __('Why it is a mistake') }}" aria-label="{{ __('Why it is a mistake') }}">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Entered in error') }}</button></form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
