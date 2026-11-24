{{-- The branches of one clinic with what each of them paid: used by the administrator and by the clinic itself. --}}
@php $S = \App\Services\Subscriptions::class; @endphp
<table class="table table-sm w-100 align-middle mb-0">
    <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Status') }}</th><th>{{ __('Paid until') }}</th><th>{{ __('Invoice') }}</th><th class="text-end">{{ __('Price') }}</th></tr></thead>
    <tbody>
        @foreach ($rows as $r)
            <tr class="{{ $r['counts'] ? '' : 'text-muted' }}">
                <td><span class="fw-semibold">{{ $r['name'] }}</span> <span class="badge bg-secondary-subtle text-secondary">{{ $r['main'] ? __('Main branch') : __('Dependent branch') }}</span>@if ($r['place'])<div class="small text-muted">{{ $r['place'] }}</div>@endif</td>
                <td>@if (! $r['counts'])<span class="badge bg-secondary-subtle text-secondary">{{ __('Closed') }}</span>@else<span class="badge bg-{{ $S::TONES[$r['state']] }}-subtle text-{{ $S::TONES[$r['state']] }}">{{ __($S::STATES[$r['state']]) }}</span>@endif</td>
                <td dir="ltr">{{ $r['until'] ?? '—' }}</td>
                <td>@if ($r['invoice'])@if ($invoiceRoute ?? null)<a href="{{ route($invoiceRoute, ['id' => $r['invoice']->id]) }}" @if ($invoiceBlank ?? false) target="_blank" rel="noopener" @endif>{{ $r['invoice']->number }}</a>@else{{ $r['invoice']->number }}@endif @else — @endif</td>
                <td class="text-end" dir="ltr">{{ $r['price'] !== null && $r['counts'] ? '$'.money($r['price']) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
