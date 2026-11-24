@php
    $cols = ['all' => 'All', 'completed' => 'Seen', 'no_show' => 'Did not come', 'cancelled' => 'Cancelled', 'walk_in' => 'Walk-in'];
    $rate = fn (array $r) => ($r['all'] - $r['cancelled']) > 0 ? round(100 * $r['no_show'] / ($r['all'] - $r['cancelled'])).'%' : '—';
@endphp
<div class="row g-3 mb-3">
    @foreach ([['Appointments', $totals['all']], ['Seen', $totals['completed']], ['Did not come', $totals['no_show']], ['Did not come (share)', $rate($totals)], ['New patients', $newPatients]] as [$label, $value])
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __($label) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
    @endforeach
</div>
<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By doctor') }}</h2></div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>{{ __('Doctor') }}</th>@foreach ($cols as $label)<th class="text-end">{{ __($label) }}</th>@endforeach<th class="text-end">{{ __('Did not come (share)') }}</th></tr></thead>
        <tbody>
            @forelse ($byDoctor as $r)
                <tr><td>{{ $r['name'] }}</td>@foreach ($cols as $k => $label)<td class="text-end">{{ $r[$k] }}</td>@endforeach<td class="text-end">{{ $rate($r) }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-muted">{{ __('Nothing in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
<p class="small text-muted mt-2">{{ __('"Did not come (share)" counts the appointments that were not cancelled.') }}</p>
