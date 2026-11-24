@extends('layouts.central', ['title' => $tenant ? __('Edit :name', ['name' => __('Clinic')]) : __('Add :name', ['name' => __('Clinic')])])

@php
    $val = fn (string $key, $default = null) => old($key, $tenant?->{$key} ?? $default);
    $field = function (string $name, string $label, array $o = []) use ($val) {
        return ['name' => $name, 'label' => $label, 'type' => $o['type'] ?? 'text', 'required' => $o['required'] ?? false, 'help' => $o['help'] ?? null, 'col' => $o['col'] ?? 6, 'value' => $o['value'] ?? $val($name), 'dir' => $o['dir'] ?? null];
    };
    $about = [
        $field('name', 'Name of the clinic', ['required' => true]),
        $field('name_local', 'Name in Dari or Pashto', ['dir' => 'rtl']),
        $field('licence_no', 'Health ministry licence number'),
        $field('domain', 'Own domain', ['help' => 'Leave empty to use the short name under :domain', 'value' => $tenant ? $val('domain') : old('domain')]),
    ];
    $licence = [
        $field('max_branches', 'Dependent branches allowed', ['col' => 3, 'type' => 'number', 'help' => 'Empty means no limit']),
        $field('licence_expires_on', 'Licence until', ['col' => 3, 'type' => 'date', 'value' => old('licence_expires_on', $tenant?->licence_expires_on?->format('Y-m-d'))]),
    ];
    $owner = [
        $field('slug', 'Short name', ['required' => true, 'help' => 'Small letters, digits and "-". It becomes the address and the database name and cannot be changed later.', 'value' => old('slug')]),
        $field('owner_name', 'Full name of the owner', ['required' => true, 'value' => old('owner_name')]),
        $field('owner_email', 'Email address of the owner', ['required' => true, 'type' => 'email', 'value' => old('owner_email')]),
        $field('owner_password', 'Password of the owner', ['required' => true, 'type' => 'password', 'help' => 'At least 8 characters', 'value' => '']),
    ];
    $groups = array_filter([
        $tenant ? null : ['First sign-in', $owner],
        ['The clinic', $about],
        ['Software licence', $licence],
    ]);
@endphp

@section('content')
<x-page-header :title="$tenant ? __('Edit :name', ['name' => __('Clinic')]) : __('Add :name', ['name' => __('Clinic')])" icon="house-medical" :crumbs="[[__('Clinics'), route('admin.tenants.index')], [$tenant ? __('Edit') : __('Add')]]" />

<form method="POST" action="{{ $tenant ? route('admin.tenants.update', ['id' => $tenant->id]) : route('admin.tenants.store') }}">
    @csrf
    @if ($tenant)
        @method('PUT')
    @endif
    @foreach ($groups as [$heading, $fields])
        <div class="card {{ $loop->first ? '' : 'mt-3' }}">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __($heading) }}</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ($fields as $f)
                        <div class="col-12 col-md-{{ $f['col'] }}">
                            <label class="form-label" for="t-{{ $f['name'] }}">{{ __($f['label']) }} @if ($f['required'])<span class="text-danger">*</span>@endif</label>
                            <input type="{{ $f['type'] }}" class="form-control @error($f['name']) is-invalid @enderror" id="t-{{ $f['name'] }}" name="{{ $f['name'] }}" value="{{ $f['value'] }}" @if ($f['dir']) dir="{{ $f['dir'] }}" @endif @if ($f['type'] === 'password') autocomplete="new-password" @endif @required($f['required'])>
                            @if ($f['help'])
                                <div class="form-text">{{ __($f['help'], ['domain' => config('tenancy.base_domain')]) }}</div>
                            @endif
                            @error($f['name'])
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <div class="card mt-3">
        <div class="card-body">
            <label class="form-label" for="t-notes">{{ __('Notes') }}</label>
            <textarea class="form-control" id="t-notes" name="notes" rows="3">{{ $val('notes') }}</textarea>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('admin.tenants.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    </div>
</form>
@endsection
