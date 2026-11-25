{{-- The paper the clinic hands out: its letterhead, the doctor, and what is written. $title, $lang (en, fa, ps), $size (A4 or A5), $doctor, $back. --}}
@php
    $lang ??= in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en';
    $rtl = in_array($lang, ['fa', 'ps'], true);
    $size ??= 'A5';
    $doctor ??= null;
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $logo = setting('company_logo') ? route('settings.logo').'?v='.md5(setting('company_logo')) : null;
    $width = $size === 'A4' ? '210mm' : '148mm';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@unless (config('app.indexable'))
<meta name="robots" content="noindex, nofollow">
@endunless
<meta name="description" content="{{ $title }} · {{ $company }}">
<link rel="icon" href="{{ $logo ?? asset_v('img/favicon.svg') }}"@unless ($logo) type="image/svg+xml"@endunless>
<title>{{ $title }} | {{ $company }}</title>
<style>
@page{size:{{ $size }};margin:10mm}
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef3f3;color:#111827;font:13px/1.5 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{max-width:{{ $width }};margin:0 auto 10px;display:flex;gap:8px;flex-wrap:wrap}
.bar button,.bar a{font:inherit;font-weight:600;padding:8px 14px;border:1px solid #0a5f61;border-radius:8px;background:#fff;color:#0a5f61;text-decoration:none;cursor:pointer}
.bar button{background:#0a5f61;color:#fff}
.paper{max-width:{{ $width }};margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:9mm}
.head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding-bottom:8px;border-bottom:3px solid #0a5f61;margin-bottom:10px}
.head img{max-height:52px;max-width:140px;object-fit:contain}
.head h1{margin:0;font-size:17px;color:#0a5f61}
.small{font-size:12px;color:#4b5563}
.end{text-align:end}
h2{font-size:16px;margin:10px 0 6px;color:#0a5f61}
h3{font-size:13.5px;margin:12px 0 4px;color:#0a5f61;border-bottom:1px solid #d1d5db;padding-bottom:2px}
.who{display:flex;flex-wrap:wrap;gap:4px 16px;margin:8px 0;padding:8px 10px;background:#f1f7f7;border-radius:8px}
.who b{color:#0a5f61}
table{width:100%;border-collapse:collapse}
th,td{padding:4px;border-bottom:1px solid #e5e7eb;text-align:start;vertical-align:top}
th{font-size:12px;color:#4b5563}
.n{direction:ltr;unicode-bidi:embed;white-space:nowrap}
.body{white-space:pre-line;font-size:14px;line-height:1.7}
.cancel{margin:6px 0;padding:8px;border:2px solid #b91c1c;color:#b91c1c;font-weight:800;text-align:center}
.allergy{padding:6px 10px;border:1px solid #b91c1c;border-radius:6px;color:#b91c1c;font-weight:600;margin:6px 0}
.foot{margin-top:24px;display:flex;justify-content:space-between;align-items:flex-end;gap:12px}
.sign{min-width:50mm;text-align:center}
.sign .line{border-top:1px solid #111827;margin-top:34px;padding-top:3px}
@media print{body{padding:0;background:#fff}.bar{display:none}.paper{box-shadow:none;border-radius:0;max-width:none;padding:0}}
@yield('style')
</style>
</head>
<body>
<div class="bar">@yield('bar')<button type="button" onclick="window.print()">{{ $rtl ? 'چاپ' : __('Print') }}</button>@isset($back)<a href="{{ $back }}">{{ $rtl ? 'برگشت' : __('Back') }}</a>@endisset</div>
<main class="paper">
    <header class="head">
        <div>
            @if ($logo)<img src="{{ $logo }}" alt="{{ $company }}">@endif
            <h1>{{ $company }}</h1>
            <div class="small">{{ implode(' · ', array_filter([current_branch()?->name, setting('company_address'), setting('company_phone')])) }}</div>
        </div>
        @if ($doctor)
            <div class="end">
                <div><b>{{ $doctor->name() }}</b></div>
                @if ($doctor->specialty)<div class="small">{{ $doctor->specialty }}</div>@endif
                @if ($doctor->licence_no)<div class="small">{{ $rtl ? 'جواز' : __('Licence') }}: <span class="n">{{ $doctor->licence_no }}</span></div>@endif
            </div>
        @endif
    </header>
    @yield('paper')
</main>
</body>
</html>
