@php
    $user = auth()->user();
@endphp
<aside class="app-sidebar" id="appSidebar" aria-label="{{ __('app.nav.menu') }}">
    @php
        $brandLocal = text_dir() === 'rtl' ? setting('company_name_local') : null;
        $brandName = $brandLocal ?: (setting('company_name') ?: config('app.name'));
    @endphp
    <div class="sidebar-brand is-stacked">
        <a href="{{ route('dashboard') }}" class="brand-link" title="{{ $brandName }}">
            <span class="brand-mark">
                @if (setting('company_logo'))
                    <img src="{{ route('settings.logo') }}?v={{ md5(setting('company_logo')) }}" alt="">
                @else
                    <x-brand-logo :size="44" />
                @endif
            </span>
            <span class="brand-text">{{ $brandName }}</span>
        </a>
    </div>
    <div class="sidebar-scroll" id="sidebarScroll">
        <div class="drawer-profile">
            <span class="avatar avatar-md avatar-soft-primary">{{ $user->initials() }}</span>
            <div class="min-w-0"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
        </div>
        <div class="drawer-quick">
            <button type="button" data-theme-toggle><x-icon name="moon" /><span>{{ __('app.nav.theme') }}</span></button>
            <button type="button" data-bs-toggle="modal" data-bs-target="#searchModal"><x-icon name="magnifying-glass" /><span>{{ __('app.nav.search') }}</span></button>
            <button type="submit" form="logoutForm"><x-icon name="right-from-bracket" /><span>{{ __('app.nav.sign_out') }}</span></button>
        </div>
        <ul class="sidebar-menu" id="sidebarMenu">
            @foreach (\App\Support\Menu::for($user) as $item)
                @if (isset($item['heading']))
                    <li class="menu-heading"><span>{{ __($item['heading']) }}</span></li>
                @elseif (isset($item['children']))
                    <li class="menu-item has-sub {{ $item['open'] ? 'open' : '' }}">
                        <a class="menu-link" href="#menu-{{ $loop->index }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $item['open'] ? 'true' : 'false' }}"><span class="menu-icon"><x-icon :name="$item['icon']" /></span><span class="menu-text">{{ __($item['label']) }}</span><x-icon name="chevron-right" class="menu-arrow" /></a>
                        <ul class="menu-sub collapse {{ $item['open'] ? 'show' : '' }}" id="menu-{{ $loop->index }}">
                            @foreach ($item['children'] as $child)
                                <li><a class="menu-sublink {{ ! empty($child['current']) ? 'active' : '' }}" href="{{ \App\Support\Menu::url($child) }}" @if (isset($child['url'])) target="_blank" rel="noopener noreferrer" @endif>{{ __($child['label']) }}@if (isset($child['url'])) <x-icon name="arrow-up-right-from-square" class="ms-1 small" />@endif</a></li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="menu-item {{ $item['current'] ? 'active' : '' }}">
                        <a class="menu-link" href="{{ \App\Support\Menu::url($item) }}" @if (isset($item['url'])) target="_blank" rel="noopener noreferrer" @endif><span class="menu-icon"><x-icon :name="$item['icon']" /></span><span class="menu-text">{{ __($item['label']) }}</span>@if (($waiting = \App\Support\Menu::badge($item)) > 0)<span class="ms-auto text-danger fw-bold text-nowrap" title="{{ __('Waiting for approval') }}"><x-icon name="triangle-exclamation" class="me-1" />{{ $waiting }}</span>@endif</a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
