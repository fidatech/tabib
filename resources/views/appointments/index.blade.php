@extends('layouts.app', ['title' => __('Appointments')])

@php
    $isToday = $day->toDateString() === today_iso();
    $nowSlot = $isToday ? now()->format('H:i') : null;
    $link = fn (array $q) => route('appointments.index', $q + ['date' => $day->toDateString(), 'doctor' => $chosen]);
@endphp

@section('content')
<x-page-header :title="__('Appointments')" icon="calendar-days" :subtitle="fmt_date($day).' · '.__($day->format('l'))">
    @if ($canManage)
        <a class="btn btn-outline-soft" href="{{ route('appointments.create', ['walk_in' => 1]) }}"><x-icon name="person-walking" class="me-1" /> {{ __('Walk-in') }}</a>
        <a class="btn btn-primary" href="{{ route('appointments.create', ['date' => $day->toDateString()]) }}"><x-icon name="calendar-plus" class="me-1" /> {{ __('New appointment') }}</a>
    @endif
</x-page-header>

{{-- the day and the doctors shown --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group" role="group" aria-label="{{ __('Day') }}">
            <a class="btn btn-outline-soft" href="{{ $link(['date' => $day->copy()->subDay()->toDateString()]) }}" title="{{ __('Previous day') }}" aria-label="{{ __('Previous day') }}"><x-icon name="chevron-left" class="flip-rtl" /></a>
            <a class="btn btn-outline-soft {{ $isToday ? 'active' : '' }}" href="{{ $link(['date' => today_iso()]) }}">{{ __('Today') }}</a>
            <a class="btn btn-outline-soft" href="{{ $link(['date' => $day->copy()->addDay()->toDateString()]) }}" title="{{ __('Next day') }}" aria-label="{{ __('Next day') }}"><x-icon name="chevron-right" class="flip-rtl" /></a>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="doctor" value="{{ $chosen }}">
            <x-date name="date" :value="$day->toDateString()" aria-label="{{ __('Date') }}" style="max-width:170px" />
            <button class="btn btn-light-secondary" type="submit">{{ __('Go') }}</button>
        </form>
        @if ($doctors->count() > 1)
            <form method="GET" class="ms-md-auto">
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                <select class="form-select" name="doctor" aria-label="{{ __('Doctor') }}" onchange="this.form.submit()" data-plain>
                    <option value="all" @selected($chosen === 'all')>{{ __('All doctors') }}</option>
                    @foreach ($doctors as $d)
                        <option value="{{ $d->id }}" @selected((string) $chosen === (string) $d->id)>{{ $d->name() }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <div class="appt-counts {{ $doctors->count() > 1 ? '' : 'ms-md-auto' }}">
            <span><b>{{ $counts['all'] }}</b> {{ __('appointments') }}</span>
            <span><b>{{ $counts['waiting'] }}</b> {{ __('waiting') }}</span>
            <span><b>{{ $counts['done'] }}</b> {{ __('done') }}</span>
        </div>
    </div>
</div>

@if ($doctors->isEmpty())
    <div class="card"><div class="card-body">
        <div class="empty-state">
            <div class="es-icon"><x-icon name="user-doctor" /></div>
            <h2 class="h3">{{ __('No doctor yet') }}</h2>
            <p class="text-muted">{{ __('Add the doctors of the clinic first; each gets a column on this calendar.') }}</p>
            @can('users.manage')<a class="btn btn-primary" href="{{ route('doctors.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add a doctor') }}</a>@endcan
        </div>
    </div></div>
@else
    {{-- a column for every doctor, a row for every slot; an empty slot books that time with one click --}}
    <div class="card">
        <div class="appt-grid-wrap">
            <table class="appt-grid" style="--doctors: {{ $shown->count() }}">
                <thead>
                    <tr>
                        <th class="appt-time" scope="col"><span class="visually-hidden">{{ __('Time') }}</span></th>
                        @foreach ($shown as $d)
                            <th scope="col"><span class="appt-dot {{ $d->colourClass() }}"></span>{{ $d->name() }}@if ($d->specialty)<small>{{ $d->specialty }}</small>@endif
                                @if ($leaves[$d->id] ?? null)<small class="text-danger fw-semibold">{{ __('Off') }}{{ $leaves[$d->id]->reason ? ': '.$leaves[$d->id]->reason : '' }}</small>@endif</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($slots as $slot)
                        @php $key = $slot->format('H:i'); $isNow = $nowSlot && $key <= $nowSlot && $slot->copy()->addMinutes($step)->format('H:i') > $nowSlot; @endphp
                        <tr class="{{ $isNow ? 'is-now' : '' }} {{ $slot->minute === 0 ? 'is-hour' : '' }}">
                            <th class="appt-time" scope="row" dir="ltr">{{ $key }}</th>
                            @foreach ($shown as $d)
                                @php $here = $cells[$d->id][$key] ?? []; $isOff = isset($off[$d->id][$key]); @endphp
                                <td class="{{ $isOff ? 'slot-off' : '' }}">
                                    @foreach ($here as $a)
                                        <a class="appt-block {{ $d->colourClass() }} is-{{ $a->status }}" href="{{ route('patients.show', ['id' => $a->patient_id]) }}"
                                            title="{{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }} {{ $a->patient?->name }} · {{ $a->statusLabel() }}">
                                            <span class="appt-block-time" dir="ltr">{{ $a->starts_at->format('H:i') }}@if ($a->token) · #{{ $a->token }}@endif</span>
                                            <span class="appt-block-name">{{ $a->patient?->name }}</span>
                                            <span class="appt-block-meta">{{ $a->source === 'walk_in' ? __('Walk-in') : ($a->type?->label() ?? '') }} · {{ $a->statusLabel() }}</span>
                                        </a>
                                    @endforeach
                                    @if ($canManage && ! $here && ! $isOff && ($day->toDateString() > today_iso() || ($isToday && $key >= now()->subMinutes($step)->format('H:i'))))
                                        <a class="appt-free" href="{{ route('appointments.create', ['doctor' => $d->id, 'date' => $day->toDateString(), 'time' => $key]) }}" aria-label="{{ __('Book :doctor at :time', ['doctor' => $d->name(), 'time' => $key]) }}"><x-icon name="plus" /></a>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- the same day as a list, with the buttons of every appointment --}}
    @php $list = collect($cells)->flatten(2)->sortBy('starts_at'); @endphp
    @if ($list->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('The day as a list') }}</h2></div>
            <div class="list-group list-group-flush">
                @foreach ($list as $a)
                    @include('appointments.row', ['a' => $a])
                @endforeach
            </div>
        </div>
    @endif
@endif
@endsection
