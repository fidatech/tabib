@extends('layouts.app', ['title' => __('Queue')])

@section('content')
<x-page-header :title="__('Queue')" icon="list-ol" :subtitle="__('Today').' · '.fmt_date(today_iso())">
    @if ($canManage)
        <a class="btn btn-primary" href="{{ route('appointments.create', ['walk_in' => 1]) }}"><x-icon name="person-walking" class="me-1" /> {{ __('Walk-in') }}</a>
    @endif
</x-page-header>

@if ($board->isEmpty())
    <div class="card"><div class="card-body"><div class="empty-state">
        <div class="es-icon"><x-icon name="user-doctor" /></div>
        <h2 class="h3">{{ __('No doctor yet') }}</h2>
        <p class="text-muted">{{ __('Add the doctors of the clinic first; each gets a column on this calendar.') }}</p>
    </div></div></div>
@endif

<div class="row g-3" data-queue>
    @foreach ($board as $row)
        @php $d = $row['doctor']; @endphp
        <div class="col-12 col-lg-6 col-xxl-4">
            <div class="card h-100 mb-0 queue-card {{ $mine === $d->id ? 'is-mine' : '' }}">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="appt-dot {{ $d->colourClass() }}"></span>
                    <h2 class="h5 card-title mb-0 flex-grow-1">{{ $d->name() }} @if ($d->room)<small class="text-muted fw-normal">· {{ __('Room') }} {{ $d->room }}</small>@endif</h2>
                    <span class="small text-muted">{{ __(':count done', ['count' => $row['done']]) }}</span>
                </div>
                <div class="card-body">
                    {{-- with the doctor now --}}
                    <div class="queue-now">
                        <span class="queue-label">{{ __('With the doctor') }}</span>
                        @forelse ($row['current'] as $a)
                            <div class="queue-now-patient">
                                <span class="queue-token" dir="ltr">#{{ $a->token }}</span>
                                <a href="{{ route('patients.show', ['id' => $a->patient_id]) }}" class="fw-semibold">{{ $a->patient?->name }}</a>
                                @if ($a->reason)<span class="d-block small text-muted">{{ $a->reason }}</span>@endif
                                <div class="mt-1">@include('appointments.bill')</div>
                                @if ($mine === $d->id)
                                    <form method="POST" action="{{ route('encounters.start') }}" class="mt-2">@csrf<input type="hidden" name="appointment_id" value="{{ $a->id }}">
                                        <button type="submit" class="btn btn-sm btn-primary"><x-icon name="stethoscope" class="me-1" /> {{ __('Open the visit') }}</button></form>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small">{{ __('Nobody') }}</div>
                        @endforelse
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('queue.next', ['doctor' => $d->id]) }}" class="my-3">
                            @csrf
                            <button type="submit" class="btn btn-gradient w-100" @disabled($row['waiting']->isEmpty())>
                                <x-icon name="bullhorn" class="me-1" /> {{ __('Call the next patient') }}
                                @if ($row['waiting']->isNotEmpty())<span class="ms-1" dir="ltr">(#{{ $row['waiting']->first()->token }})</span>@endif
                            </button>
                        </form>
                    @endif

                    <span class="queue-label">{{ __('Waiting') }} <span class="badge bg-warning text-dark">{{ $row['waiting']->count() }}</span></span>
                    <ol class="queue-list">
                        @forelse ($row['waiting'] as $a)
                            <li>
                                <span class="queue-token" dir="ltr">#{{ $a->token }}</span>
                                <span class="flex-grow-1 min-w-0"><a href="{{ route('patients.show', ['id' => $a->patient_id]) }}">{{ $a->patient?->name }}</a>
                                    <span class="d-block small text-muted">{{ $a->source === 'walk_in' ? __('Walk-in') : __('Booked for :time', ['time' => $a->starts_at->format('H:i')]) }} · {{ __('waiting since :time', ['time' => $a->arrived_at?->format('H:i')]) }}</span></span>
                                @include('appointments.bill')
                            </li>
                        @empty
                            <li class="text-muted small">{{ __('Nobody is waiting.') }}</li>
                        @endforelse
                    </ol>

                    @if ($row['coming']->isNotEmpty())
                        <span class="queue-label mt-3">{{ __('Still to come today') }}</span>
                        <div class="list-group list-group-flush">
                            @foreach ($row['coming'] as $a)
                                @include('appointments.row', ['a' => $a])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- the queue changes while nobody touches the screen: it is read again every 30 seconds, unless a form is being used --}}
<script>
(function () {
    setInterval(function () {
        var a = document.activeElement;
        if (document.hidden || (a && /INPUT|SELECT|TEXTAREA/.test(a.tagName)) || document.querySelector('.modal.show')) return;
        window.location.reload();
    }, 30000);
})();
</script>
@endsection
