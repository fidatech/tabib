{{-- Everything that happened to the patient, newest first, with a filter by kind. --}}
@php
    $kinds = ['' => 'Everything', 'visits' => 'Visits', 'history' => 'History', 'vitals' => 'Vital signs', 'documents' => 'Documents', 'appointments' => 'Appointments'];
    $only = (string) request('only', '');
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach ($kinds as $key => $label)
        <a class="btn btn-sm {{ $only === $key ? 'btn-primary' : 'btn-outline-soft' }}" href="{{ route('patients.show', array_filter(['id' => $patient->id, 'tab' => 'timeline', 'only' => $key])) }}">{{ __($label) }}</a>
    @endforeach
</div>
<div class="card">
    <div class="card-body">
        @if ($events->isEmpty())
            <p class="text-muted mb-0">{{ __('Nothing yet.') }}</p>
        @else
            <ol class="chart-timeline">
                @foreach ($events as $e)
                    <li class="{{ $e['error'] ? 'is-error' : '' }}">
                        <span class="chart-timeline-icon"><x-icon :name="$e['icon']" /></span>
                        <div class="min-w-0">
                            <span class="small text-muted">{{ fmt_date($e['at']) }} {{ $e['at']?->format('H:i') }}</span>
                            <div class="fw-semibold">@if ($e['url'])<a href="{{ $e['url'] }}">{{ $e['title'] }}</a>@else{{ $e['title'] }}@endif
                                @if ($e['badge'])<span class="badge bg-light text-dark ms-1">{{ $e['badge'] }}</span>@endif</div>
                            @if ($e['detail'])<div class="small text-muted">{{ $e['detail'] }}</div>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
