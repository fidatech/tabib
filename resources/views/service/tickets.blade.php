@extends('layouts.app', ['title' => __('Support')])

@section('content')
<x-page-header :title="__('Support')" icon="headset" :subtitle="__('Ask Fida Technologies for help. The answer comes here.')">
    <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newTicket" aria-controls="newTicket" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}"><x-icon name="plus" class="me-1" /> {{ __('New question') }}</button>
</x-page-header>

<form method="POST" action="{{ route('service.tickets.open') }}" class="card mb-3 collapse {{ $errors->any() ? 'show' : '' }}" id="newTicket">
    @csrf
    <div class="card-body row g-3">
        <div class="col-12 col-md-9"><label class="form-label" for="t-subject">{{ __('Subject') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('subject') is-invalid @enderror" id="t-subject" name="subject" value="{{ old('subject') }}" required maxlength="200"></div>
        <div class="col-12 col-md-3"><label class="form-label" for="t-priority">{{ __('Priority') }}</label><select class="form-select" id="t-priority" name="priority" data-plain>@foreach (\App\Models\Central\Ticket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority') === $key)>{{ __($label) }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label" for="t-body">{{ __('What do you need?') }} <span class="text-danger">*</span></label><textarea class="form-control @error('body') is-invalid @enderror" id="t-body" name="body" rows="5" required maxlength="5000" placeholder="{{ __('Say on which page it happens and what you expected.') }}">{{ old('body') }}</textarea></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button></div>
</form>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Opened by') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last message') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr><td>{{ $ticket->number }}</td><td><a class="fw-semibold" href="{{ route('service.ticket', ['id' => $ticket->id]) }}">{{ $ticket->subject }}</a></td><td>{{ $ticket->opened_by }}</td>
                        <td><span class="badge {{ ['answered' => 'bg-success-subtle text-success', 'open' => 'bg-warning-subtle text-warning'][$ticket->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __(\App\Models\Central\Ticket::STATUSES[$ticket->status]) }}</span></td>
                        <td>{{ $ticket->last_message_at?->diffForHumans() }}</td>
                        <td class="text-end no-print"><div class="act-group"><x-act kind="view" :href="route('service.ticket', ['id' => $ticket->id])" /></div></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('You have not asked anything yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $tickets->links('partials.pagination') }}
    </div>
</div>
@endsection
