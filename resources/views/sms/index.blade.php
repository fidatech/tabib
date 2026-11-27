@extends('layouts.app', ['title' => __('Text messages')])

@php
    $S = \App\Services\Sms::class;
    $val = fn (string $k, ?string $d = null) => old($k, setting($k, $d));
    $on = fn (string $k) => (bool) old($k, setting($k) === '1');
    $lang = $val('sms_language', 'fa');
@endphp

@section('content')
<x-page-header :title="__('Text messages')" icon="comment-sms" :subtitle="$S::enabled() ? __('Sending is on') : __('Sending is off')" />

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <form method="POST" action="{{ route('sms.update') }}" class="card mb-3">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Gateway') }}</h2></div>
            <fieldset class="card-body row g-3" @disabled(! $canManage)>
                <div class="col-12 form-check form-switch ms-2">
                    <input type="hidden" name="sms_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="sms-enabled" name="sms_enabled" value="1" @checked($on('sms_enabled'))>
                    <label class="form-check-label fw-semibold" for="sms-enabled">{{ __('Send text messages to patients') }}</label>
                </div>
                <div class="col-12 col-md-6"><label class="form-label small" for="sms-gateway">{{ __('Gateway') }}</label>
                    <select class="form-select" id="sms-gateway" name="sms_gateway" data-sms-gateway>@foreach ($S::GATEWAYS as $k => $g)<option value="{{ $k }}" @selected($val('sms_gateway', 'log') === $k)>{{ __($g) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="sms-country">{{ __('Country code') }}</label>
                    <input type="text" class="form-control" id="sms-country" name="sms_country_code" value="{{ $val('sms_country_code', '93') }}" required maxlength="5" dir="ltr"></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="sms-sender">{{ __('Sender') }}</label>
                    <input type="text" class="form-control" id="sms-sender" name="sms_sender" value="{{ $val('sms_sender') }}" maxlength="30" dir="ltr"></div>

                <div class="col-12 row g-3 m-0 p-0" data-sms-for="twilio">
                    <div class="col-12 col-md-6"><label class="form-label small" for="sms-sid">Twilio Account SID</label>
                        <input type="text" class="form-control" id="sms-sid" name="sms_twilio_sid" value="{{ $val('sms_twilio_sid') }}" maxlength="64" dir="ltr" autocomplete="off"></div>
                    <div class="col-12 col-md-6"><label class="form-label small" for="sms-token">Twilio Auth Token</label>
                        <input type="password" class="form-control" id="sms-token" name="sms_twilio_token" maxlength="128" dir="ltr" autocomplete="new-password" placeholder="{{ $hasToken ? __('Saved. Empty: keep it') : '' }}"></div>
                </div>
                <div class="col-12 row g-3 m-0 p-0" data-sms-for="http">
                    <div class="col-12"><label class="form-label small" for="sms-url">{{ __('Address of the gateway') }}</label>
                        <input type="text" inputmode="url" class="form-control" id="sms-url" name="sms_http_url" value="{{ $val('sms_http_url') }}" maxlength="500" dir="ltr" placeholder="https://sms.example.af/send?to={to}&amp;text={message}&amp;from={sender}">
                        <div class="form-text">{{ __('{to}, {message} and {sender} are filled in.') }}</div></div>
                    <div class="col-6 col-md-4"><label class="form-label small" for="sms-method">{{ __('Method') }}</label>
                        <select class="form-select" id="sms-method" name="sms_http_method"><option value="get" @selected($val('sms_http_method', 'get') === 'get')>GET</option><option value="post" @selected($val('sms_http_method') === 'post')>POST (JSON)</option></select></div>
                    <div class="col-6 col-md-8"><label class="form-label small" for="sms-htoken">{{ __('Token (Bearer)') }}</label>
                        <input type="password" class="form-control" id="sms-htoken" name="sms_http_token" maxlength="255" dir="ltr" autocomplete="new-password" placeholder="{{ $hasHttpToken ? __('Saved. Empty: keep it') : '' }}"></div>
                    <div class="col-12"><label class="form-label small" for="sms-body">{{ __('Body for POST') }}</label>
                        <input type="text" class="form-control" id="sms-body" name="sms_http_body" value="{{ $val('sms_http_body') }}" maxlength="1000" dir="ltr" placeholder='{"to":"{to}","message":"{message}"}'></div>
                </div>
            </fieldset>

            <div class="card-header border-top"><h2 class="h5 card-title mb-0">{{ __('Reminders and words') }}</h2></div>
            <fieldset class="card-body row g-3" @disabled(! $canManage)>
                <div class="col-12 col-md-7 form-check form-switch ms-2">
                    <input type="hidden" name="sms_reminders" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="sms-reminders" name="sms_reminders" value="1" @checked($on('sms_reminders'))>
                    <label class="form-check-label" for="sms-reminders">{{ __('Remind patients the day before their appointment') }}</label>
                </div>
                <div class="col-12 col-md-4"><label class="form-label small" for="sms-time">{{ __('Send the reminders from') }}</label>
                    <input type="time" class="form-control" id="sms-time" name="sms_reminder_time" value="{{ $val('sms_reminder_time', '16:00') }}" required dir="ltr"></div>
                <div class="col-12 form-check form-switch ms-2">
                    <input type="hidden" name="sms_on_booking" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="sms-booking" name="sms_on_booking" value="1" @checked($on('sms_on_booking'))>
                    <label class="form-check-label" for="sms-booking">{{ __('Send a message when an appointment is booked') }}</label>
                </div>
                <div class="col-12 col-md-7 form-check form-switch ms-2">
                    <input type="hidden" name="sms_followups" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="sms-followups" name="sms_followups" value="1" @checked($on('sms_followups'))>
                    <label class="form-check-label" for="sms-followups">{{ __('Remind patients of the check-up the doctor asked for ("come back on")') }}</label>
                </div>
                <div class="col-12 col-md-4"><label class="form-label small" for="sms-fdays">{{ __('Days before the check-up') }}</label>
                    <input type="number" class="form-control" id="sms-fdays" name="sms_followup_days" value="{{ $val('sms_followup_days', '2') }}" min="0" max="14" dir="ltr"></div>
                <div class="col-12 col-md-6"><label class="form-label small" for="sms-lang">{{ __('Language of the messages') }}</label>
                    <select class="form-select" id="sms-lang" name="sms_language">@foreach (['fa' => 'دری', 'ps' => 'پښتو', 'en' => 'English'] as $k => $l)<option value="{{ $k }}" @selected($lang === $k)>{{ $l }}</option>@endforeach</select></div>
                @foreach (['reminder' => 'Words of the reminder', 'booking' => 'Words of the booking message', 'followup' => 'Words of the check-up reminder'] as $kind => $label)
                    <div class="col-12"><label class="form-label small" for="sms-text-{{ $kind }}">{{ __($label) }}</label>
                        <textarea class="form-control" id="sms-text-{{ $kind }}" name="sms_text_{{ $kind }}" rows="2" maxlength="480" placeholder="{{ $S::DEFAULT_TEXTS[$lang][$kind] }}">{{ $val('sms_text_'.$kind) }}</textarea></div>
                @endforeach
                <div class="col-12 form-text mt-0">{{ __('Empty: the words shown. {patient}, {doctor}, {clinic}, {date}, {time} and {phone} are filled in. Patients marked "no text messages" get none.') }}</div>
            </fieldset>

            <div class="card-header border-top"><h2 class="h5 card-title mb-0">{{ __('Online booking') }}</h2></div>
            <fieldset class="card-body row g-3" @disabled(! $canManage)>
                <div class="col-12 form-check form-switch ms-2">
                    <input type="hidden" name="online_booking" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="online-booking" name="online_booking" value="1" @checked($on('online_booking'))>
                    <label class="form-check-label" for="online-booking">{{ __('Patients may book on the page of the clinic, with a code sent to their phone') }}</label>
                </div>
                <div class="col-6 col-md-4"><label class="form-label small" for="online-days">{{ __('Days ahead they can book') }}</label>
                    <input type="number" class="form-control" id="online-days" name="online_days_ahead" value="{{ $val('online_days_ahead', '14') }}" min="1" max="60" dir="ltr"></div>
                <div class="col-6 col-md-4"><label class="form-label small" for="online-hours">{{ __('Hours of notice at least') }}</label>
                    <input type="number" class="form-control" id="online-hours" name="online_min_hours" value="{{ $val('online_min_hours', '2') }}" min="0" max="72" dir="ltr"></div>
                <div class="col-12 small">
                    @if (\App\Services\OnlineBooking::open())
                        <x-icon name="circle-check" class="text-success me-1" /> {{ __('Open at') }} <a href="{{ route('book.index') }}" target="_blank" rel="noopener" dir="ltr">{{ route('book.index') }}</a>
                    @else
                        <span class="text-muted">{{ __('Closed. It opens when it is switched on and text messages are sent through a gateway. A doctor can be closed to it on his page.') }}</span>
                    @endif
                </div>
                @if ($canManage)<div class="col-12 text-end"><button class="btn btn-primary" type="submit">{{ __('Save') }}</button></div>@endif
            </fieldset>
        </form>

        @if ($canManage)
            <form method="POST" action="{{ route('sms.test') }}" class="card mb-3">@csrf
                <div class="card-body row g-2 align-items-end">
                    <div class="col"><label class="form-label small" for="sms-test">{{ __('Send a test message to') }}</label>
                        <input type="tel" class="form-control" id="sms-test" name="to" required maxlength="30" dir="ltr" placeholder="0700 123 456"></div>
                    <div class="col-auto"><button class="btn btn-outline-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button></div>
                </div>
            </form>
        @endif
    </div>

    <div class="col-12 col-xl-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2"><h2 class="h5 card-title mb-0 flex-grow-1">{{ __('Messages') }}</h2>
                <form method="GET"><label class="visually-hidden" for="sms-filter">{{ __('Status') }}</label>
                    <select class="form-select form-select-sm" id="sms-filter" name="status" onchange="this.form.submit()"><option value="">{{ __('All') }}</option>
                        @foreach (\App\Models\SmsMessage::STATUSES as $k => $s)<option value="{{ $k }}" @selected($status === $k)>{{ __($s[0]) }}</option>@endforeach</select></form>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($messages as $m)
                    <div class="list-group-item">
                        <div class="d-flex gap-2 align-items-center flex-wrap small">
                            <b dir="ltr">{{ $m->to }}</b>
                            @if ($m->patient)<a href="{{ route('patients.show', ['id' => $m->patient_id]) }}">{{ $m->patient->name }}</a>@endif
                            <span class="text-muted">{{ __(\App\Models\SmsMessage::KINDS[$m->kind] ?? $m->kind) }} · {{ fmt_date($m->created_at) }} {{ $m->created_at->format('H:i') }}</span>
                            <span class="badge {{ \App\Models\SmsMessage::STATUSES[$m->status][1] ?? 'bg-secondary' }} ms-auto">{{ __(\App\Models\SmsMessage::STATUSES[$m->status][0] ?? $m->status) }}</span>
                        </div>
                        <div class="small mt-1">{{ $m->body }}</div>
                        @if ($m->error)<div class="small text-danger">{{ $m->error }}</div>@endif
                    </div>
                @empty
                    <div class="list-group-item text-muted">{{ __('No message yet.') }}</div>
                @endforelse
            </div>
            @if ($messages->hasPages())<div class="card-body">{{ $messages->links('partials.pagination') }}</div>@endif
        </div>
    </div>
</div>

<script>
(function () {
    var pick = document.querySelector('[data-sms-gateway]'); if (!pick) return;
    function show() { document.querySelectorAll('[data-sms-for]').forEach(function (el) { el.hidden = el.getAttribute('data-sms-for') !== pick.value; }); }
    pick.addEventListener('change', show); show();
})();
</script>
@endsection
