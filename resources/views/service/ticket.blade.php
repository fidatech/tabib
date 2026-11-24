@extends('layouts.app', ['title' => $ticket->number])

@section('content')
<x-page-header :title="$ticket->subject" icon="headset" :subtitle="$ticket->number.' · '.__(\App\Models\Central\Ticket::STATUSES[$ticket->status])">
    <a class="btn btn-outline-soft" href="{{ route('service.tickets') }}"><x-icon name="arrow-left" class="me-1" /> {{ __('Back') }}</a>
</x-page-header>

<div class="card mb-3"><div class="card-body">
    <div class="talk">
        @foreach ($ticket->messages as $message)
            <div class="talk-msg {{ $message->side === 'client' ? 'is-me' : '' }}">
                <div class="talk-head"><strong>{{ $message->side === 'support' ? 'Fida Technologies · '.$message->author : $message->author }}</strong><span dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</span></div>
                <div class="talk-body">{{ $message->body }}</div>
            </div>
        @endforeach
    </div>
</div></div>

<form method="POST" action="{{ route('service.ticket.reply', ['id' => $ticket->id]) }}" class="card">
    @csrf
    <div class="card-body"><label class="form-label" for="r-body">{{ $ticket->status === 'closed' ? __('Write again to open it') : __('Your message') }}</label><textarea class="form-control @error('body') is-invalid @enderror" id="r-body" name="body" rows="4" maxlength="5000"></textarea></div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        @if ($ticket->status !== 'closed')<button class="btn btn-light-secondary" type="submit" name="close" value="1">{{ __('It is solved, close it') }}</button>@endif
        <button class="btn btn-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button>
    </div>
</form>
@endsection
