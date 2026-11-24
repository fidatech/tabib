{{-- The visits with the doctors: open one to read its note. --}}
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($encounters as $e)
            <a class="list-group-item list-group-item-action d-flex gap-3 align-items-start" href="{{ route('encounters.show', ['id' => $e->id]) }}">
                <span class="appt-row-time"><b>{{ fmt_date($e->started_at) }}</b><span class="d-block small text-muted" dir="ltr">{{ $e->started_at->format('H:i') }}</span></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="fw-semibold">{{ $e->doctor?->name() }}</span>
                    <span class="d-block small text-muted">{{ $e->diagnoses->map->label()->implode(', ') ?: $e->complaint }}</span>
                </span>
                @if ($e->isSigned())<span class="badge bg-success"><x-icon name="lock" /> {{ __('Signed') }}</span>@else<span class="badge bg-warning text-dark">{{ __('Draft') }}</span>@endif
            </a>
        @empty
            <div class="list-group-item text-muted">{{ __('No visit yet.') }}</div>
        @endforelse
    </div>
</div>
