{{-- The requests for tests and the letters given to the patient. --}}
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Lab and imaging requests') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($orders as $o)
                    <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="{{ route('orders.show', ['id' => $o->id]) }}">
                        <span class="appt-row-time"><b dir="ltr">{{ $o->number }}</b><span class="d-block small text-muted">{{ fmt_date($o->created_at) }}</span></span>
                        <span class="flex-grow-1 min-w-0 small" dir="ltr">{{ \Illuminate\Support\Str::limit($o->summary(), 110) }}</span>
                        <span class="badge {{ $o->statusClass() }}">{{ $o->statusLabel() }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">{{ __('No request yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Letters and certificates') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($letters as $l)
                    <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="{{ route('letters.show', ['id' => $l->id]) }}" target="_blank" rel="noopener">
                        <span class="appt-row-time"><b dir="ltr">{{ $l->number }}</b><span class="d-block small text-muted">{{ fmt_date($l->created_at) }}</span></span>
                        <span class="flex-grow-1 min-w-0 small">{{ $l->kindLabel() }}{{ $l->addressed_to ? ' · '.$l->addressed_to : '' }}{{ $l->restDays() ? ' · '.__(':n days', ['n' => $l->restDays()]) : '' }}</span>
                        @if ($l->status === 'cancelled')<span class="badge bg-secondary">{{ __('Cancelled') }}</span>@endif
                    </a>
                @empty
                    <div class="list-group-item text-muted">{{ __('No letter yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
