<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0d7577">
<meta name="description" content="{{ $description ?? __('app.meta_description', ['name' => config('app.name')]) }}">
@unless (config('app.indexable') || ! empty($indexable))
<meta name="robots" content="noindex, nofollow">
@endunless
<title>{{ $title ?? '' }}{{ isset($title) ? ' | ' : '' }}{{ config('app.name') }}</title>
{{-- the icon of the browser tab: the logo of the clinic when it has one, the mark of the system otherwise --}}
@if (function_exists('tenant') && tenant() && setting('company_logo'))
<link rel="icon" href="{{ route('settings.logo') }}?v={{ md5(setting('company_logo')) }}">
<link rel="apple-touch-icon" href="{{ route('settings.logo') }}?v={{ md5(setting('company_logo')) }}">
@else
<link rel="icon" href="{{ asset_v('img/favicon.svg') }}" type="image/svg+xml">
@endif
<script>(function(){var s={};try{s=JSON.parse(localStorage.getItem('erp.ui')||'{}')}catch(e){}var h=document.documentElement;h.setAttribute('data-bs-theme',s.theme==='dark'?'dark':'light');h.setAttribute('data-layout',s.layout==='collapsed'?'collapsed':'vertical')})();</script>
<link rel="stylesheet" href="{{ asset_v(text_dir() === 'rtl' ? 'css/app.rtl.css' : 'css/app.css') }}">
{{ \App\Support\Fonts::style() }}
