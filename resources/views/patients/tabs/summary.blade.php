{{-- The page a doctor reads in thirty seconds. --}}
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Active problems') }}</h2>
                <a class="small" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'history']) }}#h-problems">{{ __('All') }}</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($problems as $p)
                    <li class="list-group-item d-flex justify-content-between gap-2"><span>{{ $p->description }} @if ($p->icd10)<span class="badge bg-light text-dark" dir="ltr">{{ $p->icd10 }}</span>@endif</span>
                        @if ($p->onset_on)<span class="small text-muted">{{ __('since :date', ['date' => fmt_date($p->onset_on)]) }}</span>@endif</li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('None written.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Medicines taken now') }}</h2>
                <a class="small" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'history']) }}#h-medications">{{ __('All') }}</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($medications as $m)
                    <li class="list-group-item">{{ $m->summary() }}</li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('None written.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Last vital signs') }}</h2>
                <a class="small" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'vitals']) }}">{{ __('All') }}</a></div>
            <div class="card-body">
                @if ($lastVital)
                    <div class="vital-tiles">
                        @if ($lastVital->bloodPressure())<div><span>{{ __('BP') }}</span><b dir="ltr">{{ $lastVital->bloodPressure() }}</b></div>@endif
                        @foreach (['pulse', 'temperature', 'spo2', 'weight', 'blood_sugar'] as $m)
                            @if ($lastVital->{$m} !== null)<div><span>{{ __(\App\Models\Vital::MEASURES[$m][0]) }}</span><b dir="ltr">{{ (float) $lastVital->{$m} }} <small>{{ \App\Models\Vital::MEASURES[$m][1] }}</small></b></div>@endif
                        @endforeach
                        @if ($lastVital->bmi())<div><span>{{ __('BMI') }}</span><b dir="ltr">{{ $lastVital->bmi() }}</b></div>@endif
                    </div>
                    <p class="small text-muted mb-0 mt-2">{{ fmt_date($lastVital->taken_at) }} {{ $lastVital->taken_at->format('H:i') }}</p>
                @else
                    <p class="text-muted small mb-0">{{ __('None written.') }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Last visits') }}</h2>
                <a class="small" href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'visits']) }}">{{ __('All') }}</a></div>
            <div class="list-group list-group-flush">
                @forelse ($encounters as $e)
                    <a class="list-group-item list-group-item-action" href="{{ route('encounters.show', ['id' => $e->id]) }}">
                        <span class="d-flex justify-content-between"><b>{{ fmt_date($e->started_at) }}</b><span class="small text-muted">{{ $e->doctor?->name() }}</span></span>
                        <span class="d-block small text-muted text-truncate">{{ $e->diagnoses->map->label()->implode(', ') ?: $e->complaint }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted small">{{ __('No visit yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Coming appointments') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($upcoming as $a)
                    @include('appointments.row', ['a' => $a, 'showDate' => true])
                @empty
                    <div class="text-center text-muted small py-3">{{ __('No coming appointment.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
