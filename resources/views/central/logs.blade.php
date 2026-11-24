@extends('layouts.central', ['title' => __('Activity log')])

@section('content')
<x-page-header :title="__('Activity log')" icon="clock-rotate-left" :subtitle="__('Who did what in this panel')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-6 col-md-2"><label class="form-label small mb-1">{{ __('Staff') }}</label><select class="form-select" name="admin" data-plain><option value="">{{ __('All') }}</option>@foreach ($admins as $admin)<option value="{{ $admin->id }}" @selected((int) request('admin') === $admin->id)>{{ $admin->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-1">{{ __('Clinic') }}</label><select class="form-select" name="tenant" data-plain><option value="">{{ __('All') }}</option>@foreach ($tenants as $id => $name)<option value="{{ $id }}" @selected((int) request('tenant') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1">{{ __('From') }}</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1">{{ __('To') }}</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div>
            <div class="col-12 col-md"><label class="form-label small mb-1">{{ __('Search') }}</label><input type="search" class="form-control" name="q" value="{{ $term }}"></div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button> <a class="btn btn-light-secondary" href="{{ route('admin.logs.index') }}">{{ __('Clear') }}</a></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Action') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Details') }}</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr><td dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</td><td>{{ $log->admin?->name ?? __('System') }}</td><td><span class="badge bg-primary-subtle text-primary">{{ __(ucfirst($log->action)) }}</span></td><td>{{ $tenants[$log->tenant_id] ?? '' }}</td><td>{{ $log->description }}</td><td dir="ltr" class="text-muted">{{ $log->ip }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links('partials.pagination') }}
    </div>
</div>
@endsection
