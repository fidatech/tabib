@extends('book.layout', ['title' => __('Book an appointment')])

@section('content')
@if (! $open)
    <div class="alert alert-info">{{ __('Booking online is not open now. Please call the clinic.') }}</div>
@elseif ($doctors->isEmpty())
    <div class="alert alert-info">{{ __('No doctor can be booked online now. Please call the clinic.') }}</div>
@else
    {{-- 1. the doctor --}}
    <h2 class="h6 fw-semibold">{{ __('1. Choose the doctor') }}</h2>
    <div class="row g-2 mb-4">
        @foreach ($doctors as $d)
            <div class="col-12 col-sm-6">
                <a class="book-choice {{ $doctor?->id === $d->id ? 'active' : '' }}" href="{{ route('book.index', ['doctor' => $d->id]) }}">
                    <span class="appt-dot {{ $d->colourClass() }}"></span><b>{{ $d->name() }}</b>@if ($d->specialty)<span class="d-block small text-muted">{{ $d->specialty }}</span>@endif
                </a>
            </div>
        @endforeach
    </div>

    @if ($doctor)
        {{-- 2. the day --}}
        <h2 class="h6 fw-semibold">{{ __('2. Choose the day') }}</h2>
        <div class="book-days mb-4">
            @foreach ($days as $d)
                <a class="book-day {{ $day && $day->isSameDay($d) ? 'active' : '' }}" href="{{ route('book.index', ['doctor' => $doctor->id, 'date' => $d->toDateString()]) }}">
                    <span class="small">{{ __(\App\Services\Availability::WEEKDAYS[$d->dayOfWeek]) }}</span><b>{{ fmt_date($d) }}</b>
                </a>
            @endforeach
        </div>

        @if ($day)
            {{-- 3. the time --}}
            <h2 class="h6 fw-semibold">{{ __('3. Choose the time') }}</h2>
            @if (! $slots)
                <p class="text-muted">{{ __('No free time on this day. Please choose another day.') }}</p>
            @else
                <form method="POST" action="{{ route('book.ask') }}" class="mb-2">
                    @csrf
                    <input type="hidden" name="doctor_id" value="{{ $doctor->id }}"><input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <div class="book-times mb-4" role="radiogroup" aria-label="{{ __('3. Choose the time') }}">
                        @foreach ($slots as $s)
                            <input type="radio" class="btn-check" name="time" id="t-{{ $s->format('Hi') }}" value="{{ $s->format('H:i') }}" required @checked(old('time', $time) === $s->format('H:i'))>
                            <label class="btn btn-outline-primary" for="t-{{ $s->format('Hi') }}" dir="ltr">{{ $s->format('H:i') }}</label>
                        @endforeach
                    </div>

                    {{-- 4. who comes --}}
                    <h2 class="h6 fw-semibold">{{ __('4. Who comes') }}</h2>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><label class="form-label small" for="b-name">{{ __('Name of the patient') }}</label>
                            <input type="text" class="form-control" id="b-name" name="name" value="{{ old('name') }}" required minlength="2" maxlength="150" autocomplete="name"></div>
                        <div class="col-12 col-md-6"><label class="form-label small" for="b-phone">{{ __('Mobile number (the code is sent to it)') }}</label>
                            <input type="tel" class="form-control" id="b-phone" name="phone" value="{{ old('phone') }}" required maxlength="30" dir="ltr" autocomplete="tel" placeholder="0700 123 456"></div>
                        <div class="col-6 col-md-3"><label class="form-label small" for="b-gender">{{ __('Sex') }}</label>
                            <select class="form-select" id="b-gender" name="gender" required><option value=""></option><option value="male" @selected(old('gender') === 'male')>{{ __('Male') }}</option><option value="female" @selected(old('gender') === 'female')>{{ __('Female') }}</option></select></div>
                        <div class="col-6 col-md-3"><label class="form-label small" for="b-age">{{ __('Age') }}</label>
                            <input type="number" class="form-control" id="b-age" name="age" value="{{ old('age') }}" min="0" max="120" dir="ltr"></div>
                        <div class="col-12 col-md-6"><label class="form-label small" for="b-reason">{{ __('Why you come (optional)') }}</label>
                            <input type="text" class="form-control" id="b-reason" name="reason" value="{{ old('reason') }}" maxlength="255"></div>
                        <div class="book-hp" aria-hidden="true"><label for="b-website">Website</label><input type="text" id="b-website" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="col-12"><button class="btn btn-primary btn-lg w-100" type="submit"><x-icon name="comment-sms" class="me-1" /> {{ __('Send me the code') }}</button></div>
                    </div>
                </form>
            @endif
        @endif
    @endif
@endif
@endsection
