@props(['size' => 32, 'inverse' => false])
@php
    $gradient = 'lg'.\Illuminate\Support\Str::random(6);
@endphp
{{-- the mark of Tabib: a heartbeat line with a small cross, on a rounded teal square --}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
@if ($inverse)
<rect width="40" height="40" rx="11" fill="#fff"/><path d="M6.5 22.5h6.5l3-7 5.5 13 3.2-8.5 2.3 2.5h6.5" stroke="var(--primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M29 7.5v7M25.5 11h7" stroke="var(--primary)" stroke-width="2.6" stroke-linecap="round" opacity=".85"/>
@else
<rect width="40" height="40" rx="11" fill="url(#{{ $gradient }})"/><path d="M6.5 22.5h6.5l3-7 5.5 13 3.2-8.5 2.3 2.5h6.5" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M29 7.5v7M25.5 11h7" stroke="#fff" stroke-width="2.6" stroke-linecap="round" opacity=".85"/>
<defs><linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="40" y2="40"><stop style="stop-color:var(--brand-from,#0e7c7e)"/><stop offset="1" style="stop-color:var(--brand-to,#0a4f51)"/></linearGradient></defs>
@endif
</svg>
