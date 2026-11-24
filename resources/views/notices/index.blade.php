@extends('layouts.app', ['title' => __('Notifications')])

@section('content')
<x-page-header :title="__('Notifications')" icon="bell">
    <form method="POST" action="{{ route('notices.read') }}">@csrf<button type="submit" class="btn btn-outline-soft"><x-icon name="check" class="me-1" /> {{ __('Mark all as read') }}</button></form>
</x-page-header>

<div class="card">
    <div class="card-body p-0">
        @forelse ($rows as $row)
            <a href="{{ route('notices.open', ['id' => $row->id]) }}" class="d-flex gap-3 align-items-start p-3 border-bottom text-decoration-none text-reset">
                <span class="avatar avatar-sm {{ $row->read_at ? 'avatar-soft-secondary' : 'avatar-soft-primary' }}"><x-icon name="bell" /></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-block {{ $row->read_at ? '' : 'fw-semibold' }}">{{ $row->title }}</span>
                    @if ($row->body)<span class="d-block small text-muted">{{ $row->body }}</span>@endif
                </span>
                <span class="small text-muted text-nowrap">{{ fmt_date($row->created_at) }} {{ $row->created_at?->format('H:i') }}</span>
                @unless ($row->read_at)<span class="badge bg-primary rounded-pill">{{ __('New') }}</span>@endunless
            </a>
        @empty
            <div class="text-center text-muted py-5">{{ __('No notifications yet') }}</div>
        @endforelse
    </div>
    @if ($rows->hasPages())<div class="card-footer">{{ $rows->links('partials.pagination') }}</div>@endif
</div>
@endsection
