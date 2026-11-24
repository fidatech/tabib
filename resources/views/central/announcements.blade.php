@extends('layouts.central', ['title' => __('Announcements')])

@section('content')
<x-page-header :title="__('Announcements')" icon="bullhorn" :subtitle="__('A message on top of the screens of every clinic, or of one')" />

<form method="POST" action="{{ route('admin.announcements.store') }}" class="card mb-3">
    @csrf
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('New announcement') }}</h2></div>
    <div class="card-body row g-3">
        <div class="col-12 col-md-5"><label class="form-label" for="a-title">{{ __('Title') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('title') is-invalid @enderror" id="a-title" name="title" value="{{ old('title') }}" required maxlength="150"></div>
        <div class="col-6 col-md-4"><label class="form-label" for="a-tenant">{{ __('Shown to') }}</label><select class="form-select" id="a-tenant" name="tenant_id" data-plain><option value="">{{ __('Every clinic') }}</option>@foreach ($tenants as $tenant)<option value="{{ $tenant->id }}" @selected((int) old('tenant_id') === $tenant->id)>{{ $tenant->name }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label" for="a-level">{{ __('Kind') }}</label><select class="form-select" id="a-level" name="level" data-plain>@foreach (\App\Models\Central\Announcement::LEVELS as $key => $label)<option value="{{ $key }}" @selected(old('level') === $key)>{{ __($label) }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label" for="a-body">{{ __('Message') }} <span class="text-danger">*</span></label><textarea class="form-control @error('body') is-invalid @enderror" id="a-body" name="body" rows="3" required maxlength="2000">{{ old('body') }}</textarea></div>
        <div class="col-6 col-md-3"><label class="form-label" for="a-from">{{ __('Show from') }}</label><input type="date" class="form-control" id="a-from" name="starts_on" value="{{ old('starts_on') }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="a-to">{{ __('Show until') }}</label><input type="date" class="form-control @error('ends_on') is-invalid @enderror" id="a-to" name="ends_on" value="{{ old('ends_on') }}"></div>
        <div class="col-12 col-md-6 d-flex align-items-end justify-content-end"><button class="btn btn-primary" type="submit"><x-icon name="bullhorn" class="me-1" /> {{ __('Publish') }}</button></div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Shown to') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Show from') }}</th><th>{{ __('Show until') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($announcements as $announcement)
                    <tr>
                        <td><strong>{{ $announcement->title }}</strong><div class="small text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</div></td>
                        <td>{{ $announcement->tenant?->name ?? __('Every clinic') }}</td>
                        <td><span class="badge {{ ['info' => 'bg-primary-subtle text-primary', 'warning' => 'bg-warning-subtle text-warning', 'danger' => 'bg-danger-subtle text-danger'][$announcement->level] }}">{{ __(\App\Models\Central\Announcement::LEVELS[$announcement->level]) }}</span></td>
                        <td dir="ltr">{{ $announcement->starts_on?->format('Y-m-d') }}</td><td dir="ltr">{{ $announcement->ends_on?->format('Y-m-d') }}</td>
                        <td><span class="dot {{ $announcement->is_active ? 'dot-ok' : '' }}"></span>{{ $announcement->is_active ? __('Showing') : __('Stopped') }}</td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.announcements.update', ['id' => $announcement->id]) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-light-secondary" type="submit">{{ $announcement->is_active ? __('Stop') : __('Show again') }}</button></form>
                            <form method="POST" action="{{ route('admin.announcements.destroy', ['id' => $announcement->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">@csrf @method('DELETE')<x-act kind="delete" /></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $announcements->links('partials.pagination') }}
    </div>
</div>
@endsection
