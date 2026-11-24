@php
    $admin = auth('admin')->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="tabib" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('admin.home') }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app-wrapper">
    <aside class="app-sidebar" id="appSidebar" aria-label="{{ __('app.nav.menu') }}">
        <div class="sidebar-brand">
            <a href="{{ route('admin.home') }}" class="brand-link"><x-brand-logo /><span class="brand-text">{{ config('app.name') }}</span></a>
        </div>
        {{-- on a phone the menu opens as a drawer: who is signed in comes first --}}
        <div class="drawer-profile"><span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span><div class="min-w-0"><strong>{{ $admin->name }}</strong><small dir="ltr">{{ $admin->email }}</small></div></div>
        <div class="sidebar-scroll" id="sidebarScroll">
            <ul class="sidebar-menu" id="sidebarMenu">
                @php
                    // heading, then [label, icon, route, pattern that lights it, ability, count]
                    $openTickets = $admin->allows('tickets') ? \App\Models\Central\Ticket::where('status', 'open')->count() : 0;
                    $newSignups = $admin->allows('clients.view') ? \App\Models\Central\Signup::where('status', 'pending')->count() : 0;
                    $menu = [
                        'Overview' => [['Dashboard', 'gauge-high', 'admin.home', 'admin.home', 'dashboard']],
                        'Clients' => [
                            ['Clinics', 'house-medical', 'admin.tenants.index', 'admin.tenants.*', 'clients.view'],
                            ['Registration requests', 'user-plus', 'admin.signups.index', 'admin.signups.*', 'clients.view', $newSignups],
                            ['Support tickets', 'headset', 'admin.tickets.index', 'admin.tickets.*', 'tickets', $openTickets],
                            ['Announcements', 'bullhorn', 'admin.announcements.index', 'admin.announcements.*', 'announcements'],
                        ],
                        'Billing' => [
                            ['Invoices', 'receipt', 'admin.invoices.index', 'admin.invoices.*', 'billing.view'],
                            ['Subscriptions', 'list-check', 'admin.subscriptions.index', 'admin.subscriptions.*', 'billing.view'],
                            ['Recurring payments', 'rotate', 'admin.renewals.index', 'admin.renewals.*', 'billing.view'],
                            ['Payments', 'money-bill-wave', 'admin.payments.index', 'admin.payments.*', 'billing.view'],
                            ['Resellers', 'handshake', 'admin.sellers.index', 'admin.sellers.*', 'billing.view', $admin->allows('billing.view') ? \App\Models\Central\SellerClaim::where('status', 'pending')->count() : 0],
                            ['Income reports', 'chart-column', 'admin.income.index', 'admin.income.*', 'reports'],
                        ],
                        'System' => [
                            ['Backups', 'database', 'admin.backups.index', 'admin.backups.*', 'backups'],
                            ['System health', 'heart-pulse', 'admin.health.index', 'admin.health.*', 'health'],
                            ['Activity log', 'clock-rotate-left', 'admin.logs.index', 'admin.logs.*', 'logs'],
                            ['Staff and roles', 'users-gear', 'admin.staff.index', 'admin.staff.*', 'staff'],
                            ['Settings', 'gear', 'admin.settings.edit', 'admin.settings.*', 'settings'],
                        ],
                    ];
                @endphp
                @foreach ($menu as $heading => $items)
                    @php $items = array_filter($items, fn (array $item) => $admin->allows($item[4])); @endphp
                    @continue(! $items)
                    <li class="menu-heading"><span>{{ __($heading) }}</span></li>
                    @foreach ($items as $item)
                        <li class="menu-item {{ request()->routeIs($item[3]) ? 'active' : '' }}">
                            <a class="menu-link" href="{{ route($item[2]) }}"><span class="menu-icon"><x-icon :name="$item[1]" /></span><span class="menu-text">{{ __($item[0]) }}</span>
                                @if (! empty($item[5]))<span class="badge bg-danger ms-auto">{{ $item[5] }}</span>@endif</a>
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
            <div class="topbar-brand" aria-hidden="true">{{ __('System administration') }}</div>
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
                @php
                    // what waits for the staff: requests of the website, and tickets of the clinics
                    $bellSignups = $admin->allows('clients.view') ? \App\Models\Central\Signup::where('status', 'pending')->latest('id')->limit(5)->get() : collect();
                    $bellTickets = $admin->allows('tickets') ? \App\Models\Central\Ticket::with('tenant')->where('status', 'open')->latest('id')->limit(5)->get() : collect();
                    $bellCount = ($newSignups ?? 0) + ($openTickets ?? 0);
                @endphp
                <div class="dropdown">
                    <button class="topbar-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" type="button" aria-expanded="false" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                        <x-icon name="bell" />
                        @if ($bellCount)<span class="badge bg-danger rounded-pill position-absolute" style="top:2px;inset-inline-end:0;font-size:10px;padding:3px 5px">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>@endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px;max-width:92vw">
                        <div class="px-3 py-2 border-bottom"><strong>{{ __('Notifications') }}</strong></div>
                        <div style="max-height:360px;overflow-y:auto">
                            @foreach ($bellSignups as $item)
                                <a href="{{ route('admin.signups.index') }}" class="d-flex gap-2 align-items-start px-3 py-2 border-bottom text-decoration-none text-reset">
                                    <span class="text-primary mt-1"><x-icon name="user-plus" /></span>
                                    <span class="min-w-0"><span class="d-block small fw-semibold">{{ __('Registration request') }}: {{ $item->name }}</span>
                                        <span class="d-block small text-muted text-truncate">{{ $item->owner_name }} · {{ $item->domain() }}</span>
                                        <span class="d-block small text-muted">{{ $item->created_at?->diffForHumans() }}</span></span>
                                </a>
                            @endforeach
                            @foreach ($bellTickets as $item)
                                <a href="{{ route('admin.tickets.show', ['id' => $item->id]) }}" class="d-flex gap-2 align-items-start px-3 py-2 border-bottom text-decoration-none text-reset">
                                    <span class="text-warning mt-1"><x-icon name="headset" /></span>
                                    <span class="min-w-0"><span class="d-block small fw-semibold">{{ __('Support ticket') }}: {{ $item->subject }}</span>
                                        <span class="d-block small text-muted text-truncate">{{ $item->tenant?->name }}</span>
                                        <span class="d-block small text-muted">{{ $item->created_at?->diffForHumans() }}</span></span>
                                </a>
                            @endforeach
                            @if ($bellSignups->isEmpty() && $bellTickets->isEmpty())
                                <div class="text-center text-muted small py-4">{{ __('Nothing waits for you') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>
                <div class="dropdown">
                    <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ $admin->name }}">
                        <span class="avatar avatar-sm avatar-soft-primary">{{ $admin->initials() }}</span><span class="visually-hidden">{{ $admin->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end user-menu">
                        <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $admin->initials() }}</span><div><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></div></li>
                        <li><hr class="dropdown-divider"></li>
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

@include('partials.tabbar', ['tabs' => array_values(array_filter([
    [__('app.nav.home'), 'house', route('admin.home'), request()->routeIs('admin.home')],
    $admin->allows('clients.view') ? [__('Clinics'), 'house-medical', route('admin.tenants.index'), request()->routeIs('admin.tenants.*')] : null,
    $admin->allows('billing.view') ? [__('Payments'), 'money-bill-wave', route('admin.payments.index'), request()->routeIs('admin.payments.*')] : null,
    $admin->allows('tickets') ? [__('Tickets'), 'headset', route('admin.tickets.index'), request()->routeIs('admin.tickets.*'), $openTickets] : null,
]))])

<form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>
<form id="localeForm" method="POST" action="{{ route('preferences.locale') }}" hidden>@csrf</form>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
