{{-- The invoices of the patient and what he still owes. --}}
<div class="card">
    <div class="card-header d-flex align-items-center"><h2 class="h5 card-title mb-0 flex-grow-1">{{ __('Invoices') }}</h2>
        <span class="fw-semibold {{ \App\Support\Num::cmp($owed, '0') > 0 ? 'text-danger' : 'text-success' }}">{{ __('Owed') }}: <span dir="ltr">{{ money($owed, setting('currency')) }}</span></span></div>
    <div class="list-group list-group-flush">
        @forelse ($invoices as $inv)
            <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="{{ route('invoices.show', ['id' => $inv->id]) }}">
                <span class="appt-row-time"><b dir="ltr">{{ $inv->number }}</b><span class="d-block small text-muted">{{ fmt_date($inv->issued_on) }}</span></span>
                <span class="flex-grow-1 min-w-0 small text-muted">{{ $inv->doctor?->name() }}</span>
                <span class="text-end text-nowrap" dir="ltr">{{ money($inv->total) }}@if (\App\Support\Num::cmp($inv->balance(), '0') > 0)<span class="d-block small text-danger">{{ money($inv->balance()) }}</span>@endif</span>
                <span class="badge {{ $inv->statusClass() }}">{{ $inv->statusLabel() }}</span>
            </a>
        @empty
            <div class="list-group-item text-muted">{{ __('No invoice yet.') }}</div>
        @endforelse
    </div>
</div>
