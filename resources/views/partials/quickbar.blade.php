{{-- The daily work of the clinic, one row of buttons under the navbar on every page; each opens its page (config/quickbar.php). --}}
@php $bar = \App\Support\Menu::bar(auth()->user()); @endphp
@if ($bar)
<nav class="quickbar no-print" aria-label="{{ __('Daily work') }}">
    <ul class="quickbar-list">
        @foreach ($bar as $entry)
            @php $here = $entry['current'] || $entry['open']; @endphp
            <li><a class="quickbar-item {{ $here ? 'active' : '' }}" href="{{ \App\Support\Menu::url($entry) }}" @if ($here) aria-current="page" @endif><span class="icon-soft {{ $entry['tone'] }}"><x-icon :name="$entry['icon']" /></span><span>{{ __($entry['label']) }}</span></a></li>
        @endforeach
    </ul>
</nav>
@endif
