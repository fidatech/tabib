{{-- The public website of the system: what it is, what it costs, and the request form. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-primary="tabib" data-icons="{{ asset_v('icons.svg') }}">
<head>
    @include('partials.head', ['indexable' => true])
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? __('app.meta_description', ['name' => config('app.name')]) }}">
    <meta property="og:type" content="website">
</head>
<body class="site-body">
<a class="visually-hidden-focusable" href="#main">{{ __('Skip to the content') }}</a>
<header class="site-nav">
    <div class="site-wrap">
        <a href="{{ url('/') }}" class="brand-link"><x-brand-logo :size="34" /><span class="brand-text">{{ config('app.name') }}</span></a>
        <nav class="site-links" aria-label="{{ __('Main menu') }}">
            <a href="{{ url('/') }}#features">{{ __('Features') }}</a>
            <a href="{{ url('/') }}#pricing">{{ __('Prices') }}</a>
            <a href="{{ url('/') }}#signin">{{ __('Sign in') }}</a>
        </nav>
        <div class="site-tools">
            <form method="POST" action="{{ route('preferences.locale') }}" class="site-languages">
                @csrf
                @foreach (config('app.locales') as $code => $language)
                    <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $language }}</button>
                @endforeach
            </form>
            <a class="btn btn-primary" href="{{ route('site.register') }}">{{ __('Register your clinic') }}</a>
        </div>
    </div>
</header>
<main id="main">
    @if (session('danger'))
        <div class="site-wrap"><div class="alert alert-danger mt-3 mb-0" role="alert">{{ session('danger') }}</div></div>
    @endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="site-wrap">
        <div>
            <strong>{{ config('app.name') }}</strong>
            <p>{{ __('app.auth.hero_text') }}</p>
        </div>
        <div>
            <strong>{{ $company['company_name'] }}</strong>
            @if ($company['company_address'])<p>{{ $company['company_address'] }}</p>@endif
            @if ($company['company_phone'])<p dir="ltr"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['company_phone']) }}">{{ $company['company_phone'] }}</a></p>@endif
            @if ($company['company_email'])<p><a href="mailto:{{ $company['company_email'] }}">{{ $company['company_email'] }}</a></p>@endif
        </div>
        <div>
            <strong>{{ __('Links') }}</strong>
            <p><a href="{{ route('site.register') }}">{{ __('Register your clinic') }}</a></p>
            <p><a href="{{ route('admin.login') }}">{{ __('System administration') }}</a></p>
        </div>
    </div>
    <div class="site-wrap site-copy">© {{ date('Y') }} {{ config('app.name') }} · <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></div>
</footer>
</body>
</html>
