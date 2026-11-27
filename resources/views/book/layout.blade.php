{{-- The public pages of a clinic: its name and logo, the languages, nothing of the staff side. --}}
@php
    $company = (text_dir() === 'rtl' ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $logo = setting('company_logo') ? route('settings.logo').'?v='.md5(setting('company_logo')) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-primary="tabib" data-icons="{{ asset_v('icons.svg') }}">
<head>
    @include('partials.head', ['title' => $title.' · '.$company])
</head>
<body class="book-body">
<header class="book-head">
    <div class="container book-wrap d-flex align-items-center gap-2">
        @if ($logo)<img src="{{ $logo }}" alt="" class="book-logo">@else<x-brand-logo :size="32" />@endif
        <span class="fw-bold flex-grow-1">{{ $company }}</span>
        <form method="POST" action="{{ route('preferences.locale') }}" class="auth-languages mb-0">
            @csrf
            @foreach (config('app.locales') as $code => $language)
                <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $language }}</button>
            @endforeach
        </form>
    </div>
</header>
<main class="container book-wrap py-4">
    <h1 class="h4 mb-3">{{ $title }}</h1>
    @yield('content')
    @if (setting('company_phone') || setting('company_address'))
        <p class="small text-muted mt-4 mb-0">{{ implode(' · ', array_filter([setting('company_address'), setting('company_phone')])) }}</p>
    @endif
</main>
@include('partials.flash')
@include('partials.scripts')
</body>
</html>
