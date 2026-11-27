@extends('book.layout', ['title' => __('Your appointment')])

@section('content')
@php $live = in_array($a->status, ['booked', 'confirmed'], true); @endphp
<div class="card mb-3"><div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-2"><span class="badge {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span></div>
    <dl class="row mb-0">
        <dt class="col-5 col-sm-3 fw-normal text-muted">{{ __('Doctor') }}</dt><dd class="col-7 col-sm-9">{{ $booking->doctor->name() }}</dd>
        <dt class="col-5 col-sm-3 fw-normal text-muted">{{ __('Day') }}</dt><dd class="col-7 col-sm-9">{{ __(\App\Services\Availability::WEEKDAYS[$a->starts_at->dayOfWeek]) }} {{ fmt_date($a->starts_at) }}</dd>
        <dt class="col-5 col-sm-3 fw-normal text-muted">{{ __('Time') }}</dt><dd class="col-7 col-sm-9" dir="ltr">{{ $a->starts_at->format('H:i') }}</dd>
        <dt class="col-5 col-sm-3 fw-normal text-muted">{{ __('Patient') }}</dt><dd class="col-7 col-sm-9">{{ $booking->name }}</dd>
    </dl>
</div></div>
@if ($live)
    <p class="small text-muted">{{ __('Keep this page: it is the address of your appointment. Please come 10 minutes early.') }}</p>
    <form method="POST" action="{{ route('book.cancel', ['token' => $booking->token]) }}" data-confirm="{{ __('Cancel your appointment?') }}" data-confirm-ok="{{ __('Cancel it') }}">@csrf
        <button class="btn btn-outline-danger" type="submit">{{ __('Cancel my appointment') }}</button></form>
@endif
@endsection
