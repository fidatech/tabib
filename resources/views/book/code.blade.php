@extends('book.layout', ['title' => __('Write the code')])

@section('content')
<div class="card"><div class="card-body">
    <p>{{ __('A code was sent by SMS to :phone. Write it here to book :doctor on :date at :time.', ['phone' => $booking->phone, 'doctor' => $booking->doctor->name(),
        'date' => fmt_date($booking->starts_at), 'time' => $booking->starts_at->format('H:i')]) }}</p>
    <form method="POST" action="{{ route('book.confirm', ['token' => $booking->token]) }}" class="row g-2">
        @csrf
        <div class="col-12 col-sm-6"><label class="form-label" for="b-code">{{ __('Code') }}</label>
            <input type="text" class="form-control form-control-lg text-center book-code" id="b-code" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="12" dir="ltr" autofocus></div>
        <div class="col-12 col-sm-6 d-flex align-items-end"><button class="btn btn-primary btn-lg w-100" type="submit">{{ __('Book') }}</button></div>
    </form>
    <p class="small text-muted mt-3 mb-0">{{ __('The time is kept for you for :minutes minutes.', ['minutes' => \App\Services\OnlineBooking::CODE_MINUTES]) }} <a href="{{ route('book.index', ['doctor' => $booking->doctor_id]) }}">{{ __('Choose another time') }}</a></p>
</div></div>
@endsection
