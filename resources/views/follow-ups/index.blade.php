@extends('layouts.app', ['title' => __('Check-ups')])

@section('content')
<x-page-header :title="__('Check-ups')" icon="calendar-check" :subtitle="__('Patients the doctors asked to come back, two weeks back and ahead')" />

<form method="GET" class="mb-3 d-flex gap-2 align-items-end">
    <div><label class="form-label small mb-1" for="fu-show">{{ __('Show') }}</label>
        <select class="form-select" id="fu-show" name="show" onchange="this.form.submit()">
            <option value="open" @selected($show === 'open')>{{ __('Without an appointment') }}</option>
            <option value="all" @selected($show === 'all')>{{ __('All') }}</option>
        </select></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Come back on') }}</th><th>{{ __('Patient') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Doctor') }}</th><th>{{ __('Visit') }}</th><th>{{ __('Status') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    @php $v = $r['visit']; $late = $v->follow_up_on->lt($today); @endphp
                    <tr>
                        <td class="text-nowrap {{ $late && ! $r['booked'] ? 'text-danger fw-semibold' : '' }}">{{ fmt_date($v->follow_up_on) }}</td>
                        <td><a href="{{ route('patients.show', ['id' => $v->patient_id]) }}">{{ $v->patient?->name }}</a> <span class="small text-muted" dir="ltr">{{ $v->patient?->number }}</span></td>
                        <td dir="ltr">{{ $v->patient?->phone }}</td>
                        <td>{{ $v->doctor?->name() }}</td>
                        <td><a href="{{ route('encounters.show', ['id' => $v->id]) }}">{{ fmt_date($v->started_at) }}</a></td>
                        <td class="small">
                            @if ($r['booked'])<span class="badge bg-success">{{ __('Has an appointment') }}</span>
                            @elseif ($late)<span class="badge bg-danger">{{ __('Did not come back') }}</span>
                            @else<span class="badge bg-warning text-dark">{{ __('To book') }}</span>@endif
                            @if ($v->follow_up_reminded_at)<span class="d-block text-muted">{{ __('Reminded on :date', ['date' => fmt_date($v->follow_up_reminded_at)]) }}</span>@endif
                        </td>
                        <td class="text-end">@if ($canBook && ! $r['booked'])<a class="btn btn-sm btn-outline-primary" href="{{ route('appointments.create', ['patient' => $v->patient_id, 'doctor' => $v->doctor_id, 'date' => max($v->follow_up_on->toDateString(), $today->toDateString())]) }}">{{ __('Book') }}</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">{{ __('No check-up to book.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
