{{-- The paper of a prescription: A5, the letterhead of the clinic, the doctor and his licence, the medicines with their directions in the language asked for, and a QR code a pharmacist opens to check it. --}}
@php
    $rtl = in_array($lang, ['fa', 'ps'], true);
    $P = \App\Services\Prescribing::class;
    $t = fn (string $en, string $fa, string $ps) => ['en' => $en, 'fa' => $fa, 'ps' => $ps][$lang];
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $logo = setting('company_logo') ? route('settings.logo').'?v='.md5(setting('company_logo')) : null;
    $contact = array_filter([setting('company_address'), setting('company_phone')]);
    $branch = current_branch();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $rx->number }} | {{ $company }}</title>
<style>
@page{size:A5;margin:10mm}
*{box-sizing:border-box}
body{margin:0;padding:16px;background:#eef3f3;color:#111827;font:13px/1.45 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{max-width:148mm;margin:0 auto 10px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;font-weight:600;padding:8px 14px;border:1px solid #0a5f61;border-radius:8px;background:#0a5f61;color:#fff;text-decoration:none;cursor:pointer}
.bar a{background:#fff;color:#0a5f61}
.rx{max-width:148mm;min-height:200mm;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:9mm 9mm 7mm;display:flex;flex-direction:column}
.head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding-bottom:8px;border-bottom:3px solid #0a5f61}
.head img{max-height:52px;max-width:140px;object-fit:contain}
.head h1{margin:0;font-size:17px;color:#0a5f61}
.small{font-size:11px;color:#4b5563}
.doc{text-align:end}
.who{display:flex;flex-wrap:wrap;gap:4px 18px;margin:10px 0;padding:8px 10px;background:#f1f7f7;border-radius:8px}
.who b{color:#0a5f61}
.sym{font:italic 700 28px/1 Georgia,serif;color:#0a5f61;margin:4px 0}
ol{margin:0;padding-inline-start:20px}
li{margin:0 0 9px;break-inside:avoid}
li .name{font-weight:700}
li .qty{color:#4b5563;font-size:11.5px}
.allergy{margin:6px 0;padding:6px 10px;border:1px solid #b91c1c;border-radius:6px;color:#b91c1c;font-weight:600}
.note{margin-top:8px;font-size:12px}
.foot{margin-top:auto;display:flex;justify-content:space-between;align-items:flex-end;gap:12px;padding-top:10px;border-top:1px solid #d1d5db}
.sign{min-width:45mm;text-align:center}
.sign .line{border-top:1px solid #111827;margin-top:28px;padding-top:3px}
.qr{text-align:center}
.qr svg{display:block;width:26mm;height:26mm}
.cancel{margin:6px 0;padding:8px;border:2px solid #b91c1c;color:#b91c1c;font-weight:800;text-align:center;font-size:16px}
[dir=ltr]{unicode-bidi:embed}
@media print{body{padding:0;background:#fff}.bar{display:none}.rx{box-shadow:none;border-radius:0;max-width:none;min-height:auto;padding:0}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ $t('Print', 'چاپ', 'چاپ') }}</button><a href="{{ route('prescriptions.show', ['id' => $rx->id]) }}">{{ $t('Back', 'برگشت', 'بېرته') }}</a></div>
<main class="rx">
    <header class="head">
        <div>
            @if ($logo)<img src="{{ $logo }}" alt="{{ $company }}">@endif
            <h1>{{ $company }}</h1>
            <div class="small">{{ implode(' · ', array_filter([$branch?->name, ...$contact])) }}</div>
        </div>
        <div class="doc">
            <div><b>{{ $rx->doctor?->name() }}</b></div>
            @if ($rx->doctor?->specialty)<div class="small">{{ $rx->doctor->specialty }}</div>@endif
            @if ($rx->doctor?->licence_no)<div class="small">{{ $t('Licence', 'جواز', 'جواز') }}: <span dir="ltr">{{ $rx->doctor->licence_no }}</span></div>@endif
        </div>
    </header>

    <div class="who">
        <span><b>{{ $t('Patient', 'مریض', 'ناروغ') }}:</b> {{ $patient->name }}</span>
        @if ($patient->age() !== null)<span><b>{{ $t('Age', 'سن', 'عمر') }}:</b> {{ $P::digits((string) $patient->age(), $lang) }}</span>@endif
        @if ($patient->gender)<span><b>{{ $t('Sex', 'جنس', 'جنس') }}:</b> {{ $t(...match ($patient->gender) { 'male' => ['Male', 'مرد', 'نارینه'], 'female' => ['Female', 'زن', 'ښځینه'], default => ['Other', 'دیگر', 'نور'] }) }}</span>@endif
        @if ($rx->weight_kg)<span><b>{{ $t('Weight', 'وزن', 'وزن') }}:</b> {{ $P::digits((string) $rx->weight_kg, $lang) }} kg</span>@endif
        <span><b>{{ $t('No.', 'شماره', 'شمېره') }}:</b> <span dir="ltr">{{ $rx->number }}</span></span>
        <span><b>{{ $t('Date', 'تاریخ', 'نېټه') }}:</b> {{ fmt_date($rx->signed_at) }}</span>
    </div>

    @if ($rx->status === 'cancelled')<div class="cancel">{{ $t('CANCELLED — do not dispense', 'لغو شده — داده نشود', 'لغوه شوې — مه ورکوئ') }}</div>@endif

    @php $allergies = \App\Models\History\Allergy::where('patient_id', $patient->id)->where('status', 'active')->pluck('substance'); @endphp
    @if ($allergies->isNotEmpty())<div class="allergy">{{ $t('Allergies', 'حساسیت', 'حساسیت') }}: {{ $allergies->implode('، ') }}</div>@endif

    <div class="sym" aria-hidden="true">℞</div>
    <ol>
        @foreach ($rx->items as $item)
            <li>
                <div class="name" dir="ltr">{{ trim($item->name.' '.$item->strength.' '.$item->form) }}</div>
                <div>{{ $item->directions($lang) }}</div>
                @if ($item->quantity)<div class="qty">{{ $t('Quantity', 'مقدار', 'مقدار') }}: <span dir="ltr">{{ $item->quantity }}</span></div>@endif
            </li>
        @endforeach
    </ol>
    @if ($rx->notes)<div class="note">{{ $rx->notes }}</div>@endif

    <footer class="foot">
        <div class="qr">{!! $qr !!}<div class="small">{{ $t('Scan to check', 'برای تأیید اسکن کنید', 'د تایید لپاره یې سکن کړئ') }}</div></div>
        <div class="sign"><div class="line">{{ $rx->doctor?->name() }}</div><div class="small">{{ $t('Signed electronically', 'امضای الکترونیکی', 'برېښنايي لاسلیک') }} · {{ $rx->signed_at?->format('Y-m-d H:i') }}</div></div>
    </footer>
</main>
</body>
</html>
