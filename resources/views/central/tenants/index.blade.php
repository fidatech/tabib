@extends('layouts.central', ['title' => __('Clinics')])

@section('content')
<x-page-header :title="__('Clinics')" icon="house-medical">
    @if (auth('admin')->user()->allows('clients.manage'))<a class="btn btn-primary" href="{{ route('admin.tenants.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Clinic')]) }}</a>@endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit" title="{{ __('Search') }}" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($tenants->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="house-medical" /></div>
                <h2 class="h3">{{ $term !== '' ? __('Nothing found') : __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>{{ __('Clinic') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Dependent branches allowed') }}</th>
                        <th>{{ __('Licence until') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $tenant->id]) }}">{{ $tenant->name }}</a>@if ($tenant->name_local)<div class="text-muted small" dir="rtl">{{ $tenant->name_local }}</div>@endif</td>
                            <td><a href="//{{ $tenant->domain }}" target="_blank" rel="noopener">{{ $tenant->domain }}</a></td>
                            <td>{{ $tenant->max_branches ?? __('No limit') }}</td>
                            <td dir="ltr">{{ $tenant->licence_expires_on?->format('Y-m-d') }}@php $state = \App\Services\Licence::state($tenant); @endphp
                                @if ($state['state'] === 'soon')<div><span class="badge bg-warning-subtle text-warning">{{ __(':days days left', ['days' => $state['days']]) }}</span></div>
                                @elseif (in_array($state['state'], ['grace', 'ended'], true))<div><span class="badge bg-danger-subtle text-danger">{{ __('Ended :days days ago', ['days' => abs($state['days'])]) }}</span></div>@endif</td>
                            <td>
                                @if ($tenant->status === \App\Models\Central\Tenant::ACTIVE)
                                    <span class="badge bg-success-subtle text-success">{{ __('Active') }}</span>
                                @elseif ($tenant->status === \App\Models\Central\Tenant::SUSPENDED)
                                    <span class="badge bg-warning-subtle text-warning">{{ __('Suspended') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('Archived') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.tenants.show', ['id' => $tenant->id]) }}">{{ __('Open') }}</a>
                                @if (auth('admin')->user()->allows('clients.manage'))
                                <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.tenants.edit', ['id' => $tenant->id]) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.tenants.status', ['id' => $tenant->id]) }}" class="d-inline" data-confirm="{{ __('Change the status of this clinic?') }}" data-confirm-ok="{{ __('app.actions.yes') }}">
                                    @csrf
                                    @if ($tenant->status === \App\Models\Central\Tenant::ACTIVE)
                                        <button type="submit" name="status" value="suspended" class="btn btn-sm btn-light-danger">{{ __('Suspend') }}</button>
                                    @else
                                        <button type="submit" name="status" value="active" class="btn btn-sm btn-light-secondary">{{ __('Activate') }}</button>
                                    @endif
                                </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $tenants->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
