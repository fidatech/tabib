<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $name }}</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f5f9;color:#3b4254;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}main{max-width:460px;margin:24px;padding:32px;background:#fff;border:1px solid #edf0f5;border-radius:12px;text-align:center}h1{margin:0 0 8px;font-size:22px;color:#232a3a}p{margin:0;line-height:1.6}</style>
</head>
<body>
<main>
    <h1>{{ $name }}</h1>
    @if (! empty($licence))
        <p>{{ __('The licence of this clinic has ended and its days of grace are over. Nothing is lost: the system opens again as soon as the licence is renewed.') }}</p>
    @elseif (! empty($rejected))
        <p>{{ __('The registration of this clinic was not approved. Please contact us if you think this is a mistake.') }}</p>
    @else
        <p>{{ __('This clinic is not active at the moment. Please contact Fida Technologies.') }}</p>
    @endif
    @if (! empty($company))
        <p style="font-size:14px;opacity:.85"><strong>{{ $company['company_name'] }}</strong><br><span dir="ltr">{{ implode(' · ', array_filter([$company['company_phone'], $company['company_email'], $company['company_website']])) }}</span></p>
    @endif
</main>
</body>
</html>
