@extends('layouts.app', ['title' => __('Settings')])

@php
    $canManage = auth()->user()->can('settings.manage');
    $text = fn (string $key, ?string $default = null) => old($key, setting($key, $default));
@endphp

@section('content')
<x-page-header :title="__('Settings')" icon="gear" />

<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('The clinic') }}</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="s-company_name">{{ __('Name of the clinic') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="s-company_name" name="company_name" value="{{ $text('company_name') }}" required>
                    @error('company_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="s-company_name_local">{{ __('Name in Dari or Pashto') }}</label>
                    <input type="text" class="form-control" id="s-company_name_local" name="company_name_local" value="{{ $text('company_name_local') }}" dir="rtl">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="s-licence_no">{{ __('Health ministry licence number') }}</label>
                    <input type="text" class="form-control" id="s-licence_no" name="licence_no" value="{{ $text('licence_no') }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="s-company_phone">{{ __('Phone') }}</label>
                    <input type="text" class="form-control" id="s-company_phone" name="company_phone" value="{{ $text('company_phone') }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="s-company_email">{{ __('Email address') }}</label>
                    <input type="email" class="form-control @error('company_email') is-invalid @enderror" id="s-company_email" name="company_email" value="{{ $text('company_email') }}">
                    @error('company_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-12">
                    <label class="form-label" for="s-company_address">{{ __('Address') }}</label>
                    <input type="text" class="form-control" id="s-company_address" name="company_address" value="{{ $text('company_address') }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="s-calendar">{{ __('Dates') }} <x-help :text="__('For every user of the clinic. Only what is shown changes.')" /></label>
                    <select class="form-select" id="s-calendar" name="calendar">
                        <option value="shamsi" @selected(calendar() === 'shamsi')>{{ __('Solar Hijri (1405/08/05)') }}</option>
                        <option value="gregorian" @selected(calendar() === 'gregorian')>{{ __('Gregorian (27/10/2026)') }}</option>
                    </select>
                </div>
                @foreach (['font_fa' => 'Font for Dari', 'font_ps' => 'Font for Pashto'] as $name => $label)
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="s-{{ $name }}">{{ __($label) }}</label>
                        <select class="form-select" id="s-{{ $name }}" name="{{ $name }}">
                            @foreach ($fonts as $key => $fontName)
                                <option value="{{ $key }}" @selected(($text($name) ?: \App\Support\Fonts::DEFAULT) === $key)>{{ $fontName }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                {{-- the two files side by side --}}
                <div class="col-12 col-md-6">
                    <label class="form-label" for="s-logo">{{ __('Company logo') }} <x-help :text="__('PNG, JPG or WebP, up to 1 MB. Shown in the menu and on printed papers.')" /></label>
                    <input type="file" class="form-control @error('logo') is-invalid @enderror" id="s-logo" name="logo" accept="image/png,image/jpeg,image/webp">
                    @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="s-clinic_licence">{{ __('Clinic licence') }} <x-help :text="__('The licence of the Ministry of Public Health: PDF or picture, up to 5 MB. Kept encrypted.')" /></label>
                    <input type="file" class="form-control @error('clinic_licence') is-invalid @enderror" id="s-clinic_licence" name="clinic_licence" accept="application/pdf,image/png,image/jpeg,image/webp">
                    @error('clinic_licence')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                {{-- what is on file now --}}
                @if (setting('company_logo') || setting('licence_file'))
                    <div class="col-12 col-md-6">
                        @if (setting('company_logo'))
                            <div class="d-flex align-items-center gap-3 border rounded p-3 h-100">
                                <img src="{{ route('settings.logo') }}?v={{ md5(setting('company_logo')) }}" alt="{{ __('Company logo') }}" style="max-height:64px;max-width:180px;object-fit:contain">
                                <div class="form-check ms-auto"><input class="form-check-input" type="checkbox" id="s-remove_logo" name="remove_logo" value="1"><label class="form-check-label" for="s-remove_logo">{{ __('Remove the logo') }}</label></div>
                            </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-6">
                        @if (setting('licence_file'))
                            <div class="d-flex flex-wrap align-items-center gap-3 border rounded p-3 h-100">
                                <a class="btn btn-outline-soft" href="{{ route('settings.licence') }}" target="_blank" rel="noopener"><x-icon name="download" class="me-1" /> {{ __('Download the clinic licence') }}</a>
                                <span class="small text-muted text-truncate" style="max-width:220px">{{ setting('licence_name') }}</span>
                                <div class="form-check ms-auto"><input class="form-check-input" type="checkbox" id="s-remove_clinic_licence" name="remove_clinic_licence" value="1"><label class="form-check-label" for="s-remove_clinic_licence">{{ __('Remove the licence file') }}</label></div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Working hours and appointments') }}</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label fw-semibold" for="s-opening_time">{{ __('Opens at') }} <span class="text-danger">*</span></label>
                    <input type="time" dir="ltr" class="form-control @error('opening_time') is-invalid @enderror" id="s-opening_time" name="opening_time" value="{{ $text('opening_time', '08:00') }}" required>
                    @error('opening_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label fw-semibold" for="s-closing_time">{{ __('Closes at') }} <span class="text-danger">*</span></label>
                    <input type="time" dir="ltr" class="form-control @error('closing_time') is-invalid @enderror" id="s-closing_time" name="closing_time" value="{{ $text('closing_time', '17:00') }}" required>
                    @error('closing_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label fw-semibold" for="s-slot_minutes">{{ __('Usual appointment') }} <span class="text-danger">*</span> <x-help :text="__('How long an appointment takes unless its type says otherwise.')" /></label>
                    <select class="form-select @error('slot_minutes') is-invalid @enderror" id="s-slot_minutes" name="slot_minutes">
                        @foreach ([5, 10, 15, 20, 30, 45, 60] as $minutes)
                            <option value="{{ $minutes }}" @selected((int) $text('slot_minutes', '15') === $minutes)>{{ __(':count minutes', ['count' => $minutes]) }}</option>
                        @endforeach
                    </select>
                    @error('slot_minutes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label fw-semibold" for="s-currency">{{ __('Currency') }} <span class="text-danger">*</span></label>
                    <input type="text" dir="ltr" maxlength="5" class="form-control @error('currency') is-invalid @enderror" id="s-currency" name="currency" value="{{ $text('currency', 'AFN') }}" required>
                    @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        @if ($canManage)
            <div class="card-footer d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </div>
        @endif
    </div>
</form>
@endsection
