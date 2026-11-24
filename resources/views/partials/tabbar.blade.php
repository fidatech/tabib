{{--
    The bottom bar of phones and tablets, as in a mobile app: the pages used most, and "Menu",
    which opens the whole menu as a drawer. Without it a panel has no way to its menu on a phone.
    $tabs: [label, icon, address, is it the page that is open, count to show in red]
--}}
<nav class="app-tabbar" aria-label="{{ __('app.nav.menu') }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab[2] }}" class="tab-item {{ $tab[3] ? 'active' : '' }}" @if ($tab[3]) aria-current="page" @endif>
            <x-icon :name="$tab[1]" />@if (! empty($tab[4]))<span class="tab-count">{{ $tab[4] > 99 ? '99+' : $tab[4] }}</span>@endif<em>{{ $tab[0] }}</em>
        </a>
    @endforeach
    <button type="button" class="tab-item" id="tabMenu" aria-controls="appSidebar"><x-icon name="grip" /><em>{{ __('app.nav.menu') }}</em></button>
</nav>
