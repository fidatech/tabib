@extends('layouts.auth', ['title' => __('app.auth.title')])

@section('content')
@php
    // the clinic being signed in to: its own logo and name, so the staff know they are at the right door
    $clinicName = (text_dir() === 'rtl' ? setting('company_name_local') : null) ?: (setting('company_name') ?: tenant()?->name);
    $clinicLogo = setting('company_logo') ? route('settings.logo').'?v='.md5(setting('company_logo')) : null;
@endphp
<div class="auth-card">
    @if ($clinicLogo)
        <div class="ac-clinic"><img src="{{ $clinicLogo }}" alt="{{ $clinicName }}" width="64" height="64"></div>
    @else
        <div class="ac-icon"><x-icon name="user-doctor" /></div>
    @endif
    <h2>{{ __('app.auth.welcome') }}</h2>
    <p class="ac-sub">@if ($clinicName){{ __('Sign in to :clinic', ['clinic' => $clinicName]) }}@else{{ __('app.auth.subtitle') }}@endif</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <div class="auth-field"><x-icon name="envelope" set="regular" /><input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus></div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label small" for="remember">{{ __('app.auth.remember') }}</label>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('app.auth.submit') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <form method="POST" action="{{ route('preferences.locale') }}" class="auth-languages">
        @csrf
        @foreach (config('app.locales') as $code => $language)
            <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $language }}</button>
        @endforeach
    </form>
</div>
@endsection
