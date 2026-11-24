{{-- The paper of an invoice (A5), or of one receipt ($payment: narrow, for a receipt printer too). --}}
@php
    
    $rtl = text_dir() === 'rtl';
    $cur = setting('currency');
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $logo = setting('company_logo') ? route('settings.logo').'?v='.md5(setting('company_logo')) : null;
    $patient = $invoice->patient;
    $receipt = $payment !== null;
    $title = $receipt ? ($payment->isRefund() ? __('Refund') : __('Receipt')).' '.$payment->number : __('Invoice').' '.$invoice->number;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}">
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
@page{size:{{ $receipt ? '80mm auto' : 'A5' }};margin:{{ $receipt ? '4mm' : '10mm' }}}
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef3f3;color:#111827;font:13px/1.45 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{max-width:{{ $receipt ? '80mm' : '148mm' }};margin:0 auto 10px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;font-weight:600;padding:8px 14px;border:1px solid #0a5f61;border-radius:8px;background:#0a5f61;color:#fff;text-decoration:none;cursor:pointer}
.bar a{background:#fff;color:#0a5f61}
.paper{max-width:{{ $receipt ? '80mm' : '148mm' }};margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:{{ $receipt ? '5mm' : '9mm' }}}
.head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding-bottom:8px;border-bottom:3px solid #0a5f61}
.head img{max-height:48px;max-width:120px;object-fit:contain}
.head h1{margin:0;font-size:16px;color:#0a5f61}
.small{font-size:12px;color:#4b5563}
.kind{text-align:end;font-weight:800;color:#0a5f61;font-size:15px}
.who{display:flex;flex-wrap:wrap;gap:4px 16px;margin:10px 0;padding:8px 10px;background:#f1f7f7;border-radius:8px}
.who b{color:#0a5f61}
table{width:100%;border-collapse:collapse}
th,td{padding:5px 4px;border-bottom:1px solid #e5e7eb;text-align:start;vertical-align:top}
th{font-size:12px;color:#4b5563}
.n{text-align:end;white-space:nowrap;direction:ltr;unicode-bidi:embed}
tfoot th,tfoot td{border-bottom:0}
.big{font-size:16px;font-weight:800}
.owed{color:#b91c1c}
.void{margin:6px 0;padding:8px;border:2px solid #b91c1c;color:#b91c1c;font-weight:800;text-align:center;font-size:15px}
.foot{margin-top:14px;display:flex;justify-content:space-between;align-items:flex-end;gap:12px;font-size:12px;color:#4b5563}
.sign{min-width:40mm;text-align:center}
.sign .line{border-top:1px solid #111827;margin-top:26px;padding-top:3px;color:#111827}
@media print{body{padding:0;background:#fff}.bar{display:none}.paper{box-shadow:none;border-radius:0;max-width:none;padding:0}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ __('Print') }}</button><a href="{{ route('invoices.show', ['id' => $invoice->id]) }}">{{ __('Back') }}</a></div>
<main class="paper">
    <header class="head">
        <div>
            @if ($logo)<img src="{{ $logo }}" alt="{{ $company }}">@endif
            <h1>{{ $company }}</h1>
            <div class="small">{{ implode(' · ', array_filter([$invoice->branch?->name, setting('company_address'), setting('company_phone')])) }}</div>
        </div>
        <div class="kind">{{ $title }}</div>
    </header>

    <div class="who">
        <span><b>{{ __('Patient') }}:</b> {{ $patient->name }} <span dir="ltr">({{ $patient->number }})</span></span>
        <span><b>{{ __('Invoice') }}:</b> <span dir="ltr">{{ $invoice->number }}</span></span>
        <span><b>{{ __('Date') }}:</b> {{ $receipt ? fmt_date($payment->received_at).' '.$payment->received_at->format('H:i') : fmt_date($invoice->issued_on) }}</span>
        @if ($invoice->doctor)<span><b>{{ __('Doctor') }}:</b> {{ $invoice->doctor->name() }}</span>@endif
    </div>

    @if ($invoice->isVoid())<div class="void">{{ __('VOID') }} — {{ $invoice->void_reason }}</div>@endif

    @if ($receipt)
        <table>
            <tbody>
                <tr><th>{{ $payment->isRefund() ? __('Given back') : __('Received') }}</th><td class="n big">{{ money(\App\Support\Num::abs($payment->amount), $cur) }}</td></tr>
                <tr><th>{{ __('Paid by') }}</th><td class="n">{{ $payment->methodLabel() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td></tr>
                @if ($payment->note)<tr><th>{{ __('Why') }}</th><td>{{ $payment->note }}</td></tr>@endif
                <tr><th>{{ __('Total of the invoice') }}</th><td class="n">{{ money($invoice->total, $cur) }}</td></tr>
                <tr><th>{{ __('Still owed') }}</th><td class="n {{ \App\Support\Num::cmp($invoice->balance(), '0') > 0 ? 'owed' : '' }}">{{ money($invoice->balance(), $cur) }}</td></tr>
            </tbody>
        </table>
        <div class="foot"><span>{{ __('Received by') }}: {{ $payment->receiver?->name }}</span></div>
    @else
        <table>
            <thead><tr><th>{{ __('Description') }}</th><th class="n">{{ __('Quantity') }}</th><th class="n">{{ __('Price') }}</th><th class="n">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr><td>{{ $item->description }}</td><td class="n">{{ \App\Support\Num::format($item->quantity, 2, true) }}</td><td class="n">{{ money($item->unit_price) }}</td><td class="n">{{ money($item->amount) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="3" class="n">{{ __('Subtotal') }}</th><td class="n">{{ money($invoice->subtotal) }}</td></tr>
                @if (! \App\Support\Num::isZero($invoice->discount))<tr><th colspan="3" class="n">{{ __('Discount') }}</th><td class="n">−{{ money($invoice->discount) }}</td></tr>@endif
                <tr><th colspan="3" class="n">{{ __('Total') }}</th><td class="n big">{{ money($invoice->total, $cur) }}</td></tr>
                <tr><th colspan="3" class="n">{{ __('Paid') }}</th><td class="n">{{ money($invoice->paid, $cur) }}</td></tr>
                <tr><th colspan="3" class="n">{{ __('Owed') }}</th><td class="n big {{ \App\Support\Num::cmp($invoice->balance(), '0') > 0 ? 'owed' : '' }}">{{ money($invoice->balance(), $cur) }}</td></tr>
            </tfoot>
        </table>
        @if ($invoice->note)<p class="small">{{ $invoice->note }}</p>@endif
        <div class="foot"><span>{{ __('Made by') }}: {{ $invoice->creator?->name }}</span><div class="sign"><div class="line">{{ __('Signature and stamp') }}</div></div></div>
    @endif
</main>
</body>
</html>
