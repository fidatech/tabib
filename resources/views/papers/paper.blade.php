{{-- A lab or imaging request ($what = order), or a letter or certificate ($what = letter). --}}
@php
    $lang = $what === 'letter' ? $letter->lang : (in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en');
    $t = fn (string $en, string $fa, string $ps) => ['en' => $en, 'fa' => $fa, 'ps' => $ps][$lang];
    $back = $what === 'order' ? route('orders.show', ['id' => $order->id]) : route('patients.show', ['id' => $patient->id, 'tab' => 'papers']);
    $size = 'A5';
@endphp
@extends('papers.layout')

@section('paper')
    @if ($what === 'order')
        <h2>{{ $order->kind === 'lab' ? $t('Laboratory request', 'درخواست لابراتوار', 'د لابراتوار غوښتنه') : $t('Imaging request', 'درخواست رادیولوژی', 'د راډیولوژۍ غوښتنه') }}
            @if ($order->urgency === 'urgent') <span style="color:#b91c1c">· {{ $t('URGENT', 'عاجل', 'بیړنی') }}</span>@endif</h2>
    @else
        <h2>{{ $t(...match ($letter->kind) { 'referral' => ['Referral letter', 'معرفی‌نامه', 'د لېږلو لیک'], 'sick_leave' => ['Medical certificate', 'تصدیق صحی (استراحت)', 'روغتیايي تصدیق (استراحت)'],
            'fitness' => ['Certificate of fitness', 'تصدیق صحتمندی', 'د روغتیا تصدیق'], default => ['Letter', 'نامه', 'لیک'] }) }}</h2>
    @endif

    <div class="who">
        <span><b>{{ $t('Patient', 'مریض', 'ناروغ') }}:</b> {{ $patient->name }}{{ $patient->father_name ? ' ('.$patient->father_name.')' : '' }}</span>
        <span><b>{{ $t('File', 'شماره پرونده', 'د دوسیې شمېره') }}:</b> <span class="n">{{ $patient->number }}</span></span>
        @if ($patient->age() !== null)<span><b>{{ $t('Age', 'سن', 'عمر') }}:</b> {{ $patient->age() }}</span>@endif
        <span><b>{{ $t('No.', 'شماره', 'شمېره') }}:</b> <span class="n">{{ $what === 'order' ? $order->number : $letter->number }}</span></span>
        <span><b>{{ $t('Date', 'تاریخ', 'نېټه') }}:</b> {{ fmt_date(($what === 'order' ? $order : $letter)->created_at) }}</span>
    </div>

    @if (($what === 'order' ? $order : $letter)->status === 'cancelled')<div class="cancel">{{ $t('CANCELLED', 'لغو شده', 'لغوه شوې') }}</div>@endif

    @if ($what === 'order')
        <h3>{{ $t('Please do', 'لطفاً انجام دهید', 'مهرباني وکړئ ترسره یې کړئ') }}</h3>
        <ol style="font-size:14px;line-height:1.8;margin:4px 0" dir="ltr">@foreach ($order->tests as $test)<li>{{ $test }}</li>@endforeach</ol>
        @if ($order->clinical_note)<h3>{{ $t('Clinical information', 'معلومات کلینیکی', 'کلینیکي معلومات') }}</h3><p>{{ $order->clinical_note }}</p>@endif
        <p class="small">{{ $t('Please give the result to the patient or send it to the clinic.', 'لطفاً نتیجه را به مریض بدهید یا به کلینیک بفرستید.', 'مهرباني وکړئ پایله ناروغ ته ورکړئ یا کلینیک ته یې ولېږئ.') }}</p>
    @else
        @if ($letter->addressed_to)<p><b>{{ $t('To', 'به', 'ته') }}:</b> {{ $letter->addressed_to }}</p>@endif
        <div class="body">{{ $letter->body }}</div>
    @endif

    <div class="foot">
        <span class="small">@if ($what === 'letter' && ! $letter->signatureHolds()) <b style="color:#b91c1c">{{ $t('Changed after it was given', 'بعد از صدور تغییر کرده', 'له ورکولو وروسته بدل شوی') }}</b> @endif</span>
        <div class="sign"><div class="line">{{ $doctor?->name() }}</div><div class="small">{{ $t('Signature and stamp', 'امضا و مهر', 'لاسلیک او مهر') }}</div></div>
    </div>
@endsection
