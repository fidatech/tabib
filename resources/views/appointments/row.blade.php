{{--
    One appointment as a row: time, patient (or doctor), what it is, its status and the buttons
    that move it on. $a: the appointment; $showDate: show the date (on the page of a patient);
    $showDoctor: show the doctor instead of the patient.
--}}
@php
    $canAct = auth()->user()->can('appointments.manage');
    $showDate = $showDate ?? false;
    // the doctor who is signed in opens the visit of his patient straight from the row
    // (asked once in a request, kept on the request)
    if (! request()->attributes->has('my_doctor')) {
        request()->attributes->set('my_doctor', auth()->user()->can('clinical.manage') ? \App\Models\Doctor::where('user_id', auth()->id())->where('is_active', true)->value('id') : null);
    }
    $myDoctor = request()->attributes->get('my_doctor');
    $canVisit = $myDoctor && in_array($a->status, ['arrived', 'with_doctor', 'completed'], true) && ($a->doctor_id === $myDoctor || $a->status !== 'completed');
    // the front desk bills a patient who came; a bill already made shows whether it is paid
    $canBill = auth()->user()->can('billing.manage') && in_array($a->status, ['arrived', 'with_doctor', 'completed'], true);
    $bill = auth()->user()->can('billing.view') ? $a->invoice : null;
    $buttons = ['confirm' => 'btn-outline-soft', 'arrive' => 'btn-warning', 'start' => 'btn-primary', 'complete' => 'btn-success', 'no_show' => 'btn-outline-soft', 'cancel' => 'btn-outline-danger'];
@endphp
<div class="list-group-item appt-row d-flex flex-wrap gap-2 align-items-center">
    <div class="appt-row-time" dir="ltr">
        @if ($showDate)<span class="d-block small text-muted">{{ fmt_date($a->starts_at) }}</span>@endif
        <strong>{{ $a->starts_at->format('H:i') }}</strong>
        @if ($a->token)<span class="badge rounded-pill bg-dark ms-1" title="{{ __('Token') }}">#{{ $a->token }}</span>@endif
    </div>
    <div class="flex-grow-1 min-w-0">
        @if ($showDate)
            <span class="fw-semibold">{{ $a->doctor?->name() }}</span>
        @else
            <a class="fw-semibold" href="{{ route('patients.show', ['id' => $a->patient_id]) }}">{{ $a->patient?->name }}</a>
            <span class="small text-muted">{{ $a->patient?->number }}</span>
        @endif
        <span class="d-block small text-muted text-truncate">
            {{ implode(' · ', array_filter([$a->type?->label(), $a->source === 'walk_in' ? __('Walk-in') : null, $a->reason, $a->status === 'cancelled' && $a->cancel_reason ? $a->cancel_reason : null])) }}
        </span>
    </div>
    <span class="badge {{ $a->statusClass() }}">{{ $a->statusLabel() }}</span>
    @if ($bill)
        <a class="btn btn-sm {{ $bill->status === 'paid' ? 'btn-outline-success' : 'btn-outline-danger' }} no-print" href="{{ route('invoices.show', ['id' => $bill->id]) }}"><x-icon name="file-invoice-dollar" class="me-1" /> {{ $bill->statusLabel() }}</a>
    @elseif ($canBill)
        <form method="POST" action="{{ route('invoices.start') }}" class="no-print">@csrf<input type="hidden" name="appointment_id" value="{{ $a->id }}">
            <button type="submit" class="btn btn-sm btn-outline-primary"><x-icon name="file-invoice-dollar" class="me-1" /> {{ __('Bill') }}</button></form>
    @endif
    @if ($canVisit)
        <form method="POST" action="{{ route('encounters.start') }}" class="no-print">@csrf<input type="hidden" name="appointment_id" value="{{ $a->id }}">
            <button type="submit" class="btn btn-sm btn-gradient"><x-icon name="stethoscope" class="me-1" /> {{ $a->status === 'completed' ? __('The visit') : __('Open the visit') }}</button></form>
    @endif
    @if ($canAct && ($a->actions() || $a->isOpen()))
        <div class="d-flex flex-wrap gap-1 no-print">
            @foreach ($a->actions() as $action)
                <form method="POST" action="{{ route('appointments.act', ['id' => $a->id, 'action' => $action]) }}"
                    @if ($action === 'cancel') data-confirm="{{ __('Cancel this appointment?') }}" data-confirm-ok="{{ __('Cancel') }}" @endif>
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $buttons[$action] }}">{{ __(\App\Models\Appointment::ACTIONS[$action][2]) }}</button>
                </form>
            @endforeach
            @if ($a->isOpen())
                <a class="btn btn-sm btn-light" href="{{ route('appointments.edit', ['id' => $a->id]) }}" title="{{ __('Move') }}" aria-label="{{ __('Move') }}"><x-icon name="pen" /></a>
            @endif
        </div>
    @endif
</div>
