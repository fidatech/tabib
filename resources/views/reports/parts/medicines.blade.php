<p class="text-muted">{{ __(':count signed prescriptions in this period.', ['count' => $prescriptions]) }}</p>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>#</th><th>{{ __('Medicine') }}</th><th>{{ __('Strength') }}</th><th>{{ __('Form') }}</th><th class="text-end">{{ __('Times') }}</th><th class="text-end">{{ __('Patients') }}</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr><td>{{ $loop->iteration }}</td><td dir="ltr">{{ $r->name }}</td><td dir="ltr">{{ $r->strength }}</td><td>{{ $r->form ? __($r->form) : '' }}</td><td class="text-end">{{ $r->n }}</td><td class="text-end">{{ $r->patients }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('Nothing in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
