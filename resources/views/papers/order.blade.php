@extends('layouts.app', ['title' => $order->number.' · '.$patient->name])

@section('content')
<x-page-header :title="__(\App\Models\Order::KINDS[$order->kind]).' · '.$order->number" :icon="$order->kind === 'lab' ? 'flask' : 'x-ray'"
    :crumbs="[[__('Patients'), route('patients.index')], [$patient->name, route('patients.show', ['id' => $patient->id, 'tab' => 'papers'])], [$order->number]]"
    :subtitle="$order->doctor?->name().' · '.fmt_date($order->created_at).($order->urgency === 'urgent' ? ' · '.__('Urgent') : '')">
    <span class="badge {{ $order->statusClass() }} fs-6 align-self-center">{{ $order->statusLabel() }}</span>
    <a class="btn btn-outline-soft" href="{{ route('orders.print', ['id' => $order->id]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print the request') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Asked for') }}</h2></div>
            <ul class="list-group list-group-flush">@foreach ($order->tests as $test)<li class="list-group-item" dir="ltr">{{ $test }}</li>@endforeach</ul>
            @if ($order->clinical_note)<div class="card-body small border-top">{{ $order->clinical_note }}</div>@endif
        </div>
        @if ($order->encounter)<p class="small"><a href="{{ route('encounters.show', ['id' => $order->encounter_id]) }}">{{ __('Visit of :date', ['date' => fmt_date($order->encounter->started_at)]) }}</a></p>@endif
    </div>
    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Result') }}</h2></div>
            <div class="card-body">
                @if ($order->status === 'resulted')
                    @if ($order->result)<div class="mb-2" style="white-space:pre-line">{{ $order->result }}</div>@endif
                    @if ($order->document)<p class="mb-2"><a href="{{ route('history.document', ['patient' => $patient->id, 'id' => $order->document->id]) }}" target="_blank" rel="noopener"><x-icon name="file-lines" class="me-1" /> {{ $order->document->original_name }}</a></p>@endif
                    <p class="small text-muted mb-3">{{ __('Written by :name on :date.', ['name' => $order->resulter?->name, 'date' => fmt_date($order->resulted_at)]) }}</p>
                @elseif ($order->status === 'cancelled')
                    <p class="text-muted mb-0">{{ __('Cancelled') }}: {{ $order->cancel_reason }}</p>
                @endif
                @if ($order->status !== 'cancelled')
                    @can('clinical.manage')
                        <form method="POST" action="{{ route('orders.result', ['id' => $order->id]) }}" enctype="multipart/form-data" class="row g-2">@csrf
                            <div class="col-12"><label class="form-label small" for="o-result">{{ $order->status === 'resulted' ? __('Add to the result') : __('Write the result') }}</label>
                                <textarea class="form-control" id="o-result" name="result" rows="4" maxlength="5000">{{ $order->result }}</textarea></div>
                            <div class="col-12"><label class="form-label small" for="o-file">{{ __('Or add the paper of the result (photo or PDF)') }}</label>
                                <input type="file" class="form-control" id="o-file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                            <div class="col-12 text-end"><button class="btn btn-primary" type="submit">{{ __('Save the result') }}</button></div>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
        @if ($order->status === 'ordered')
            @can('clinical.manage')
                <form method="POST" action="{{ route('orders.cancel', ['id' => $order->id]) }}" class="card card-body row g-2 flex-row align-items-end mx-0">@csrf
                    <div class="col"><label class="form-label small" for="o-cancel">{{ __('Cancel this request') }}</label><input type="text" class="form-control" id="o-cancel" name="reason" required maxlength="255" placeholder="{{ __('Why') }}"></div>
                    <div class="col-auto"><button class="btn btn-outline-danger" type="submit">{{ __('Cancel') }}</button></div>
                </form>
            @endcan
        @endif
    </div>
</div>
@endsection
