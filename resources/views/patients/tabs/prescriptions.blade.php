{{-- The prescriptions of the patient, newest first. --}}
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($prescriptions as $rx)
            <a class="list-group-item list-group-item-action d-flex gap-3 align-items-start" href="{{ route('prescriptions.show', ['id' => $rx->id]) }}">
                <span class="appt-row-time"><b>{{ $rx->number ?? __('Draft') }}</b><span class="d-block small text-muted">{{ fmt_date($rx->signed_at ?? $rx->created_at) }}</span></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="fw-semibold">{{ $rx->doctor?->name() }}</span>
                    <span class="d-block small text-muted">{{ $rx->items->map->label()->implode(' · ') ?: __('No medicine yet') }}</span>
                </span>
                @if ($rx->status === 'signed')<span class="badge bg-success"><x-icon name="lock" /> {{ __('Signed') }}</span>
                @elseif ($rx->status === 'cancelled')<span class="badge bg-danger">{{ __('Cancelled') }}</span>
                @else<span class="badge bg-warning text-dark">{{ __('Draft') }}</span>@endif
            </a>
        @empty
            <div class="list-group-item text-muted">{{ __('No prescription yet.') }}</div>
        @endforelse
    </div>
</div>
