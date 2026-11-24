<p class="text-muted">{{ __(':count signed visits in this period.', ['count' => $visits]) }}</p>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>#</th><th>ICD-10</th><th>{{ __('Diagnosis') }}</th><th class="text-end">{{ __('Times') }}</th><th class="text-end">{{ __('As the main diagnosis') }}</th><th class="text-end">{{ __('Patients') }}</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr><td>{{ $loop->iteration }}</td><td dir="ltr">{{ $r->code ?: '—' }}</td><td>{{ $r->description }}</td><td class="text-end">{{ $r->n }}</td><td class="text-end">{{ $r->main }}</td><td class="text-end">{{ $r->patients }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('Nothing in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
