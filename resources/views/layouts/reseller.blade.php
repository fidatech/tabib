@php
    $admin = auth('seller')->user();
    // what waits for an answer of the administrator: his requests to open a clinic and the money he reported
    $pendingActivations = \App\Models\Central\Signup::where('seller_id', $admin->id)->where('status', 'pending')->count();
    $pendingClaims = \App\Models\Central\SellerClaim::where('seller_id', $admin->id)->where('status', 'pending')->count();
    // heading, then [label, icon, route, count]
    $menu = [
        'Overview' => [['Dashboard', 'gauge-high', 'reseller.home']],
        'Sales' => [
            ['My clinics', 'house-medical', 'reseller.clients'],
            ['Activation', 'user-plus', 'reseller.activations', $pendingActivations],
        ],
        'Money' => [
            ['Payments', 'money-bill-wave', 'reseller.payments', $pendingClaims],
            ['Commissions', 'percent', 'reseller.commissions'],
            ['Finances', 'wallet', 'reseller.finances'],
        ],
        'My account' => [['Profile and password', 'id-badge', 'reseller.account']],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="tabib" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('reseller.home') }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app-wrapper">
    <aside class="app-sidebar" id="appSidebar" aria-label="{{ __('app.nav.menu') }}">
        <div class="sidebar-brand">
            <a href="{{ route('reseller.home') }}" class="brand-link"><x-brand-logo /><span class="brand-text">{{ config('app.name') }}</span></a>
        </div>
        {{-- on a phone the menu opens as a drawer: who is signed in comes first --}}
        <div class="drawer-profile"><span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span><div class="min-w-0"><strong>{{ $admin->name }}</strong><small dir="ltr">{{ $admin->email }}</small></div></div>
        <div class="sidebar-scroll" id="sidebarScroll">
            <ul class="sidebar-menu" id="sidebarMenu">
                @foreach ($menu as $heading => $items)
                    <li class="menu-heading"><span>{{ __($heading) }}</span></li>
                    @foreach ($items as $item)
                        <li class="menu-item {{ request()->routeIs($item[2]) ? 'active' : '' }}">
                            <a class="menu-link" href="{{ route($item[2]) }}"><span class="menu-icon"><x-icon :name="$item[1]" /></span><span class="menu-text">{{ __($item[0]) }}</span>
                                @if (! empty($item[3]))<span class="badge bg-warning text-dark ms-auto">{{ $item[3] }}</span>@endif</a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
        <header class="app-topbar" id="appTopbar">
            <button class="btn-icon btn-ghost topbar-toggle" id="sidebarToggle" type="button" aria-label="{{ __('app.nav.toggle_sidebar') }}"><x-icon name="bars-staggered" /></button>
            <div class="appbar-title" id="appbarTitle">{{ $title ?? config('app.name') }}</div>
            <div class="topbar-brand" aria-hidden="true">{{ __('Reseller panel') }}</div>
            <div class="topbar-right">
                <div class="dropdown">
                    <button class="topbar-icon topbar-lang" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ __('Language') }}" aria-label="{{ strtoupper(app()->getLocale()) }} · {{ __('Language') }}"><x-icon name="globe" /><span>{{ strtoupper(app()->getLocale()) }}</span></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">{{ __('Language') }}</li>
                        @foreach (config('app.locales') as $code => $language)
                            <li><button class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" type="submit" form="localeForm" name="locale" value="{{ $code }}" lang="{{ $code }}">{{ $language }}</button></li>
                        @endforeach
                    </ul>
                </div>
                <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>
                <div class="dropdown">
                    <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ $admin->name }}">
                        <span class="avatar avatar-sm avatar-soft-primary">{{ $admin->initials() }}</span><span class="visually-hidden">{{ $admin->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end user-menu">
                        <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span><div><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('reseller.account') }}"><x-icon name="id-badge" /> {{ __('Profile and password') }}</a></li>
                        <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="app-content">
            <div class="content-container">
                @yield('content')
            </div>
        </main>
        <footer class="app-footer">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <nav><span class="text-muted">{{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span></nav>
        </footer>
    </div>
</div>

@include('partials.tabbar', ['tabs' => [
    [__('app.nav.home'), 'house', route('reseller.home'), request()->routeIs('reseller.home')],
    [__('My clinics'), 'house-medical', route('reseller.clients'), request()->routeIs('reseller.clients')],
    [__('Payments'), 'money-bill-wave', route('reseller.payments'), request()->routeIs('reseller.payments'), $pendingClaims],
    [__('Finances'), 'wallet', route('reseller.finances'), request()->routeIs('reseller.finances')],
]])

<form id="logoutForm" method="POST" action="{{ route('reseller.logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>
<form id="localeForm" method="POST" action="{{ route('preferences.locale') }}" hidden>@csrf</form>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
