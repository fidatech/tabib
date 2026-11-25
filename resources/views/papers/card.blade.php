{{-- The card a patient takes home: his medicines, when in the day, and a box to tick each day. --}}
@php
    $P = \App\Services\Prescribing::class;
    $t = fn (string $en, string $fa, string $ps) => ['en' => $en, 'fa' => $fa, 'ps' => $ps][$lang];
    $title = $t('Medicine card', 'کارت دوا', 'د درملو کارت').' · '.$rx->number;
    $size = 'A5';
    $doctor = $rx->doctor;
    $back = route('prescriptions.show', ['id' => $rx->id]);
    $slots = [$t('Morning', 'صبح', 'سهار'), $t('Noon', 'چاشت', 'غرمه'), $t('Evening', 'شام', 'ماښام'), $t('Bedtime', 'وقت خواب', 'د خوب وخت')];
    $days = min(14, max(1, (int) $rx->items->max('days')));
@endphp
@extends('papers.layout')

@section('style')
.grid th,.grid td{text-align:center;border:1px solid #d1d5db}
.grid td.med{text-align:start}
.grid .on{background:#e6f4f4;font-weight:800;color:#0a5f61}
.tick{display:inline-block;width:13px;height:13px;border:1px solid #6b7280;border-radius:3px;margin:1px}
@endsection

@section('paper')
    <h2>{{ $t('When to take your medicines', 'وقت خوردن دواهای شما', 'ستاسو د درملو د خوړلو وخت') }}</h2>
    <div class="who"><span><b>{{ $t('Patient', 'مریض', 'ناروغ') }}:</b> {{ $patient->name }}</span><span><b>{{ $t('Prescription', 'نسخه', 'نسخه') }}:</b> <span class="n">{{ $rx->number }}</span></span>
        <span><b>{{ $t('From', 'از', 'له') }}:</b> {{ fmt_date($rx->signed_at) }}</span></div>
    <table class="grid">
        <thead><tr><th>{{ $t('Medicine', 'دوا', 'درمل') }}</th>@foreach ($slots as $s)<th>{{ $s }}</th>@endforeach<th>{{ $t('Days (tick each day)', 'روزها (هر روز علامت بزنید)', 'ورځې (هره ورځ نښه کړئ)') }}</th></tr></thead>
        <tbody>
            @foreach ($rx->items as $item)
                @php $on = $P::SLOTS[$item->frequency] ?? null; $dose = rtrim(rtrim(number_format((float) $item->dose_amount, 2, '.', ''), '0'), '.') ?: '•'; @endphp
                <tr>
                    <td class="med"><b dir="ltr">{{ trim($item->name.' '.$item->strength) }}</b><div class="small">{{ $item->directions($lang) }}</div></td>
                    @foreach (range(0, 3) as $i)<td class="{{ $on && $on[$i] ? 'on' : '' }}">{{ $on && $on[$i] ? $P::digits($dose, $lang) : '' }}</td>@endforeach
                    <td>@for ($d = 1; $d <= min($days, (int) ($item->days ?: $days)); $d++)<span class="tick" title="{{ $d }}"></span>@endfor</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="small">{{ $t('Bring this card and your medicines to your next visit.', 'این کارت و دواهای خود را در مراجعه بعدی همراه بیاورید.', 'دا کارت او خپل درمل په راتلونکې کتنه کې له ځان سره راوړئ.') }}</p>
@endsection
