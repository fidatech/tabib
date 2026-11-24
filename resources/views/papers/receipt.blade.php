@extends('layouts.print', ['title' => $number ?: $singular])

{{-- The receipt of one paper (an expense, a capital entry, a loan ...): what the list shows of it, with places to sign. --}}
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="h5 mb-0">{{ $singular }}</h2><div class="small text-muted">{{ $branch?->name }}</div></div>
    <div class="text-end">@if ($number)<strong dir="ltr">{{ $number }}</strong>@endif<div class="small text-muted">{{ __('Printed on') }} {{ fmt_date(today_iso()) }}</div></div>
</div>
@if ($cancelled)
    <div class="alert alert-danger py-2">{{ __('Cancelled') }}</div>
@endif
<table class="table table-bordered w-100 keep-table">
    <tbody>
        @foreach ($lines as $label => $value)
            @if ((string) $value !== '')
                <tr><th style="width:38%">{{ __($label) }}</th><td>{{ $value }}</td></tr>
            @endif
        @endforeach
    </tbody>
</table>
<div class="d-flex justify-content-between mt-5 small">
    <span>{{ __('Prepared by') }}: ____________</span>
    <span>{{ __('Received by') }}: ____________</span>
    <span>{{ __('Signature and stamp') }}: ____________</span>
</div>
@endsection
