{{-- An invoice or a receipt of Fida Technologies as a paper: for the printer, or "Save as PDF". Used by the staff and by the clinic itself. --}}
@php
    $company = \App\Http\Controllers\Central\SettingsController::company();
    $hasLogo = (bool) \App\Models\Central\CentralSetting::read('logo_path');
    $logoData = null;
    if ($hasLogo) {
        $path = \App\Models\Central\CentralSetting::read('logo_path');
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $logoData = $disk->exists($path) ? 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode($disk->get($path)) : null;
    }
    $isInvoice = $kind === 'invoice';
    $number = $isInvoice ? $invoice->number : $payment->number;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>{{ $number }}</title>
<style>
@foreach (config('fonts')[\App\Support\Fonts::DEFAULT]['faces'] ?? [] as [$file, $format, $weight])
@font-face{font-family:"PaperFont";src:url("{{ asset_v('fonts/'.$file) }}") format("{{ $format }}");font-weight:{{ $weight }};font-display:swap;unicode-range:U+0600-06FF,U+0750-077F,U+FB50-FDFF,U+FE70-FEFF,U+200C-200F}
@endforeach
</style>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#eef0f4;color:#111827;font-family:PaperFont,Inter,system-ui,sans-serif;font-size:13.5px;line-height:1.55}
.bar{max-width:820px;margin:0 auto 14px;display:flex;gap:8px;justify-content:flex-end}.bar button,.bar a{border:1px solid #1f3a8a;background:#1f3a8a;color:#fff;border-radius:10px;padding:9px 18px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}.bar a{background:#fff;color:#1f3a8a}
.sheet{max-width:820px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,.08);overflow:hidden}
.top{display:flex;justify-content:space-between;gap:24px;padding:30px 36px;border-bottom:4px solid #1f3a8a}
.brand img{max-height:64px;max-width:220px;object-fit:contain;display:block;margin-bottom:8px}.brand h1{margin:0;font-size:21px;font-weight:800}.muted{color:#6b7280;font-size:12px}
.doc{text-align:end}.doc h2{margin:0;font-size:26px;font-weight:800;letter-spacing:.5px;color:#1f3a8a;text-transform:uppercase}.doc .no{font-size:15px;font-weight:700;margin-top:2px}
.body{padding:26px 36px 32px}.two{display:flex;justify-content:space-between;gap:24px;margin-bottom:22px}.box h3{margin:0 0 4px;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#6b7280}.box strong{font-size:15px}
table{width:100%;border-collapse:collapse;margin-bottom:18px}th{background:#1f3a8a;color:#fff;font-size:11px;letter-spacing:.5px;text-transform:uppercase;padding:9px 12px;text-align:start}td{padding:10px 12px;border-bottom:1px solid #e5e7eb}
.end{text-align:end}.num{font-variant-numeric:tabular-nums;direction:ltr;unicode-bidi:isolate}
.sum{margin-inline-start:auto;width:300px}.sum div{display:flex;justify-content:space-between;padding:7px 12px}.sum .total{background:#1f3a8a;color:#fff;border-radius:10px;font-weight:800;font-size:16px;margin-top:6px}
.stamp{display:inline-block;border:2px solid;border-radius:10px;padding:4px 14px;font-weight:800;letter-spacing:1px;text-transform:uppercase;font-size:13px}.paid{color:#15803d}.unpaid{color:#b45309}.void{color:#b91c1c}
.note{margin-top:20px;padding:12px 14px;background:#f3f6fb;border-radius:10px;font-size:12.5px;color:#374151;white-space:pre-wrap}
.sign{display:flex;justify-content:space-between;gap:30px;margin-top:54px}.sign div{flex:1;max-width:220px;border-top:1px solid #111827;padding-top:5px;text-align:center;font-size:12px}
.foot{margin-top:24px;padding-top:10px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:11px;color:#6b7280}
@page{size:A4;margin:12mm}@media print{body{background:#fff;padding:0}.bar{display:none}.sheet{box-shadow:none;border-radius:0;max-width:none}th,.sum .total{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ __('Print') }}</button><a href="javascript:history.back()">{{ __('Back') }}</a></div>
<div class="sheet">
    <div class="top">
        <div class="brand">
            @if ($logoData)<img src="{{ $logoData }}" alt="">@endif
            <h1>{{ $company['company_name'] }}</h1>
            <div class="muted">{{ $company['company_address'] }}</div>
            <div class="muted num">{{ implode(' · ', array_filter([$company['company_phone'], $company['company_email'], $company['company_website']])) }}</div>
        </div>
        <div class="doc">
            <h2>{{ $isInvoice ? __('Invoice') : __('Receipt') }}</h2>
            <div class="no num">{{ $number }}</div>
            <div class="muted">{{ __('Date') }}: <span class="num">{{ ($isInvoice ? $invoice->created_at : $payment->paid_on)?->format('Y-m-d') }}</span></div>
            @if ($isInvoice)
                <div style="margin-top:10px"><span class="stamp {{ ['paid' => 'paid', 'unpaid' => 'unpaid'][$invoice->status] ?? 'void' }}">{{ __(\App\Models\Central\Invoice::STATUSES[$invoice->status]) }}</span></div>
            @elseif ($payment->isCancelled())
                <div style="margin-top:10px"><span class="stamp void">{{ __('Cancelled') }}</span></div>
            @endif
        </div>
    </div>
    <div class="body">
        <div class="two">
            <div class="box"><h3>{{ $isInvoice ? __('Invoice to') : __('Received from') }}</h3><strong>{{ $tenant->name }}</strong>@if ($tenant->name_local)<div dir="auto">{{ $tenant->name_local }}</div>@endif
                <div class="muted">{{ $tenant->owner_name }}</div><div class="muted num">{{ implode(' · ', array_filter([$tenant->owner_phone, $tenant->domain])) }}</div>
                @if ($tenant->licence_no)<div class="muted">{{ __('Health ministry licence number') }}: <span class="num">{{ $tenant->licence_no }}</span></div>@endif</div>
            <div class="box end">
                @if ($isInvoice)<h3>{{ __('Period of the licence') }}</h3><strong class="num">{{ $invoice->from_date->format('Y-m-d') }} — {{ $invoice->to_date->format('Y-m-d') }}</strong>
                @else<h3>{{ __('Paid by') }}</h3><strong>{{ __(\App\Models\Central\Payment::METHODS[$payment->method] ?? $payment->method) }}</strong>@if ($payment->reference)<div class="muted num">{{ $payment->reference }}</div>@endif @endif
            </div>
        </div>

        @if ($isInvoice)
            <table>
                <thead><tr><th>{{ __('Description') }}</th><th class="end">{{ __('Quantity') }}</th><th class="end">{{ __('Price') }}</th><th class="end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @forelse ($invoice->lines ?? [] as $line)
                        <tr><td>{{ __($line['label']) }}</td><td class="end num">{{ $line['quantity'] }}</td><td class="end num">${{ money($line['price']) }}</td><td class="end num">${{ money($line['amount']) }}</td></tr>
                    @empty
                        <tr><td>{{ __('Licence of the clinic system') }}</td><td class="end num">1</td><td class="end num">${{ money($invoice->total) }}</td><td class="end num">${{ money($invoice->total) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @php $paidOn = \App\Services\Billing::paidOn($invoice); @endphp
            <div class="sum">
                <div><span>{{ __('Total') }}</span><span class="num">${{ money($invoice->total) }}</span></div>
                <div><span>{{ __('Paid') }}</span><span class="num">${{ money($paidOn) }}</span></div>
                <div class="total"><span>{{ __('Still to be paid') }}</span><span class="num">${{ money(\App\Support\Num::sub($invoice->total, $paidOn)) }}</span></div>
            </div>
            @if ($invoice->note)<div class="note">{{ $invoice->note }}</div>@endif
            @if ($company['invoice_note'])<div class="note">{{ $company['invoice_note'] }}</div>@endif
        @else
            <table>
                <thead><tr><th>{{ __('Description') }}</th><th class="end">{{ __('Amount') }}</th></tr></thead>
                <tbody><tr><td>{{ $payment->invoice ? __('Payment of invoice :number', ['number' => $payment->invoice->number]) : __('Payment on account') }}@if ($payment->note)<div class="muted">{{ $payment->note }}</div>@endif</td><td class="end num">${{ money($payment->amount) }}</td></tr></tbody>
            </table>
            <div class="sum">
                <div class="total"><span>{{ __('Received') }}</span><span class="num">${{ money($payment->amount) }}</span></div>
                <div><span>{{ __('Still to be paid by the clinic') }}</span><span class="num">${{ money($balance['due']) }}</span></div>
            </div>
            <div class="sign"><div>{{ __('Received by') }}@if ($payment->admin): {{ $payment->admin->name }}@endif</div><div>{{ __('Signature and stamp') }}</div></div>
        @endif
        <div class="foot"><span>{{ $company['company_name'] }}</span><span class="num">{{ $company['company_website'] }}</span></div>
    </div>
</div>
</body>
</html>
