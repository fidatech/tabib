@extends('layouts.central', ['title' => $ticket->number])

@section('content')
<x-page-header :title="$ticket->subject" icon="headset" :subtitle="$ticket->number.' · '.$ticket->tenant->name">
    <a class="btn btn-outline-soft" href="{{ route('admin.tickets.index') }}"><x-icon name="arrow-left" class="me-1" /> {{ __('Back') }}</a>
    <a class="btn btn-outline-soft" href="{{ route('admin.tenants.show', ['id' => $ticket->tenant_id]) }}"><x-icon name="house-medical" class="me-1" /> {{ __('Open the page of this clinic') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card mb-3"><div class="card-body">
            <div class="talk">
                @foreach ($ticket->messages as $message)
                    <div class="talk-msg {{ $message->side === 'support' ? 'is-me' : '' }}">
                        <div class="talk-head"><strong>{{ $message->author }}@if ($message->side === 'support') · Fida Technologies @endif</strong><span dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</span></div>
                        <div class="talk-body">{{ $message->body }}</div>
                    </div>
                @endforeach
            </div>
        </div></div>
        <form method="POST" action="{{ route('admin.tickets.update', ['id' => $ticket->id]) }}" class="card mb-0">
            @csrf
            @method('PUT')
            <div class="card-body"><label class="form-label" for="t-body">{{ __('Your answer') }}</label><textarea class="form-control @error('body') is-invalid @enderror" id="t-body" name="body" rows="4" maxlength="5000"></textarea></div>
            <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                @if ($ticket->status === 'closed')
                    <button class="btn btn-light-secondary" type="submit" name="status" value="open">{{ __('Open it again') }}</button>
                @else
                    <button class="btn btn-light-secondary" type="submit" name="status" value="closed">{{ __('Answer and close') }}</button>
                @endif
                <button class="btn btn-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send the answer') }}</button>
            </div>
        </form>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('About this ticket') }}</h2></div><div class="card-body">
            <dl class="facts">
                <div><dt>{{ __('Status') }}</dt><dd><span class="badge {{ ['open' => 'bg-danger-subtle text-danger', 'answered' => 'bg-success-subtle text-success'][$ticket->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __(\App\Models\Central\Ticket::STATUSES[$ticket->status]) }}</span></dd></div>
                <div><dt>{{ __('Priority') }}</dt><dd>{{ __(\App\Models\Central\Ticket::PRIORITIES[$ticket->priority] ?? $ticket->priority) }}</dd></div>
                <div><dt>{{ __('Clinic') }}</dt><dd>{{ $ticket->tenant->name }}</dd></div>
                <div><dt>{{ __('Opened by') }}</dt><dd>{{ $ticket->opened_by }}</dd></div>
                <div><dt>{{ __('Email') }}</dt><dd dir="ltr">{{ $ticket->opened_email }}</dd></div>
                <div><dt>{{ __('Opened on') }}</dt><dd dir="ltr">{{ $ticket->created_at?->format('Y-m-d H:i') }}</dd></div>
                <div><dt>{{ __('Messages') }}</dt><dd>{{ $ticket->messages->count() }}</dd></div>
            </dl>
        </div></div>
    </div>
</div>
@endsection
