<div class="row g-3 mb-3">
    @foreach ($buckets as $range => $amount)
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __(':range days', ['range' => $range]) }}</div><div class="fs-5 fw-bold" dir="ltr">{{ money($amount, $cur) }}</div></div></div></div>
    @endforeach
    <div class="col-12 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('All owed') }}</div><div class="fs-5 fw-bold text-danger" dir="ltr">{{ money($total, $cur) }}</div></div></div></div>
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>{{ __('Patient') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Invoices') }}</th><th>{{ __('Oldest') }}</th><th class="text-end">{{ __('Days') }}</th><th class="text-end">{{ __('Owed') }}</th></tr></thead>
        <tbody>
            @forelse ($patients as $id => $r)
                <tr><td><a href="{{ route('patients.show', ['id' => $id, 'tab' => 'billing']) }}">{{ $r['patient']?->name }}</a> <span class="small text-muted">{{ $r['patient']?->number }}</span></td>
                    <td dir="ltr">{{ $r['patient']?->phone }}</td><td class="text-end">{{ $r['invoices'] }}</td><td>{{ fmt_date($r['oldest']) }}</td><td class="text-end">{{ $r['days'] }}</td><td class="text-end fw-semibold" dir="ltr">{{ money($r['owed']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('Nobody owes money.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
