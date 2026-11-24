@extends('layouts.central', ['title' => __('Support tickets')])

@section('content')
<x-page-header :title="__('Support tickets')" icon="headset" :subtitle="__('What the clinics asked. The ones waiting for us come first.')" />

<div class="card">
    <div class="card-header">
        <div class="seg">
            <a href="{{ route('admin.tickets.index') }}" class="{{ $status ? '' : 'active' }}">{{ __('All') }}</a>
            @foreach (\App\Models\Central\Ticket::STATUSES as $key => $label)
                <a href="{{ route('admin.tickets.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ __($label) }}@if (($counts[$key] ?? 0) > 0)<span class="badge {{ $key === 'open' ? 'bg-danger' : 'bg-secondary' }}">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Opened by') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last message') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->number }}</td><td><a class="fw-semibold" href="{{ route('admin.tickets.show', ['id' => $ticket->id]) }}">{{ $ticket->subject }}</a></td>
                        <td>{{ $ticket->tenant->name }}</td><td>{{ $ticket->opened_by }}</td>
                        <td>@if ($ticket->priority === 'urgent')<span class="badge bg-danger">{{ __('Urgent') }}</span>@else<span class="text-muted">{{ __('Normal') }}</span>@endif</td>
                        <td><span class="badge {{ ['open' => 'bg-danger-subtle text-danger', 'answered' => 'bg-success-subtle text-success'][$ticket->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __(\App\Models\Central\Ticket::STATUSES[$ticket->status]) }}</span></td>
                        <td>{{ $ticket->last_message_at?->diffForHumans() }}</td>
                        <td class="text-end no-print"><div class="act-group"><x-act kind="view" :href="route('admin.tickets.show', ['id' => $ticket->id])" /></div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $tickets->links('partials.pagination') }}
    </div>
</div>
@endsection
