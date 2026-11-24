@extends('layouts.central', ['title' => __('Staff and roles')])

@section('content')
<x-page-header :title="__('Staff and roles')" icon="users-gear" :subtitle="__('Who of Fida Technologies may sign in to this panel, and what each may do')" />

<div class="row g-3 mb-3">
    @foreach (\App\Models\Central\Admin::ROLES as $key => $label)
        @php $abilities = \App\Models\Central\Admin::ABILITIES[$key]; @endphp
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 mb-0"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">{{ __($label) }}</h2><span class="badge bg-primary-subtle text-primary">{{ $staff->where('role', $key)->count() }}</span></div>
                <div class="small text-muted">
                    @if (in_array('*', $abilities, true)){{ __('Everything, and the only one who manages staff, settings and the log.') }}
                    @else{{ implode(' · ', array_map(fn ($a) => __(['dashboard' => 'Dashboard', 'clients.view' => 'See the clinics', 'clients.manage' => 'Add and change clinics', 'billing.view' => 'See invoices and payments', 'billing.manage' => 'Make invoices, record payments', 'reports' => 'Income reports', 'announcements' => 'Announcements', 'tickets' => 'Support tickets', 'enter' => 'Enter a clinic as support', 'backups' => 'Backups', 'health' => 'System health'][$a] ?? $a), $abilities)) }}@endif
                </div>
            </div></div>
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('admin.staff.store') }}" class="card mb-3">
    @csrf
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('New member of staff') }}</h2></div>
    <div class="card-body row g-3 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label" for="s-name">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('name') is-invalid @enderror" id="s-name" name="name" value="{{ old('name') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12 col-md-3"><label class="form-label" for="s-email">{{ __('Email') }} <span class="text-danger">*</span></label><input type="email" class="form-control @error('email') is-invalid @enderror" id="s-email" name="email" value="{{ old('email') }}" dir="ltr" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-6 col-md-2"><label class="form-label" for="s-role">{{ __('Role') }} <span class="text-danger">*</span></label><select class="form-select" id="s-role" name="role" data-plain>@foreach (\App\Models\Central\Admin::ROLES as $key => $label)<option value="{{ $key }}" @selected(old('role', 'support') === $key)>{{ __($label) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="s-password">{{ __('Password') }} <span class="text-danger">*</span></label><input type="password" class="form-control @error('password') is-invalid @enderror" id="s-password" name="password" autocomplete="new-password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button></div>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @foreach ($staff as $member)
                    <tr>
                        <td class="fw-semibold">{{ $member->name }}@if ($member->id === auth('admin')->id()) <span class="badge bg-primary-subtle text-primary">{{ __('You') }}</span>@endif</td>
                        <td dir="ltr">{{ $member->email }}</td>
                        <td>{{ __(\App\Models\Central\Admin::ROLES[$member->role] ?? $member->role) }}</td>
                        <td><span class="dot {{ $member->is_active ? 'dot-ok' : '' }}"></span>{{ $member->is_active ? __('Active') : __('Not active') }}</td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('admin.staff.update', ['id' => $member->id]) }}" class="d-inline-flex gap-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <select class="form-select form-select-sm" name="role" style="width:130px" data-plain aria-label="{{ __('Role') }}">@foreach (\App\Models\Central\Admin::ROLES as $key => $label)<option value="{{ $key }}" @selected($member->role === $key)>{{ __($label) }}</option>@endforeach</select>
                                <input type="password" class="form-control form-control-sm" name="password" placeholder="{{ __('New password') }}" autocomplete="new-password" style="width:140px" aria-label="{{ __('New password') }}">
                                <button class="btn btn-sm btn-light-secondary" type="submit">{{ __('Save') }}</button>
                                <button class="btn btn-sm {{ $member->is_active ? 'btn-light-danger' : 'btn-success' }}" type="submit" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">{{ $member->is_active ? __('Switch off') : __('Switch on') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
