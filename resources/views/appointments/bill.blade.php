{{-- The bill of an appointment: a link to it with whether it is paid, or the button that makes it. $a: the appointment. --}}
@php
    $bill = auth()->user()->can('billing.view') ? $a->invoice : null;
    $canBill = ! $bill && auth()->user()->can('billing.manage') && in_array($a->status, ['arrived', 'with_doctor', 'completed'], true);
@endphp
@if ($bill)
    <a class="btn btn-sm {{ $bill->status === 'paid' ? 'btn-outline-success' : 'btn-outline-danger' }} no-print" href="{{ route('invoices.show', ['id' => $bill->id]) }}"><x-icon name="file-invoice-dollar" class="me-1" /> {{ $bill->statusLabel() }}</a>
@elseif ($canBill)
    <form method="POST" action="{{ route('invoices.start') }}" class="no-print d-inline">@csrf<input type="hidden" name="appointment_id" value="{{ $a->id }}">
        <button type="submit" class="btn btn-sm btn-outline-primary"><x-icon name="file-invoice-dollar" class="me-1" /> {{ __('Bill') }}</button></form>
@endif
