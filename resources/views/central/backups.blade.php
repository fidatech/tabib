@extends('layouts.central', ['title' => __('Backups')])

@section('content')
<x-page-header :title="__('Backups')" icon="database" :subtitle="__('A copy of every clinic is saved each night. Here they are downloaded, made by hand, and put back.')" />

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ route('admin.backups.store') }}" class="card h-100 mb-0">
            @csrf
            <div class="card-body row g-2 align-items-end">
                <div class="col"><label class="form-label" for="b-tenant">{{ __('Back up now') }}</label><select class="form-select" id="b-tenant" name="tenant_id" required data-plain><option value="">{{ __('Choose…') }}</option>@foreach ($tenants as $tenant)<option value="{{ $tenant->id }}" @selected($chosen?->id === $tenant->id)>{{ $tenant->name }}</option>@endforeach</select></div>
                <div class="col-auto"><button class="btn btn-primary" type="submit"><x-icon name="database" class="me-1" /> {{ __('Back up') }}</button></div>
            </div>
        </form>
    </div>
    <div class="col-6 col-lg-2"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="database" /></span><div><div class="kpi-value">{{ $backups->total() }}</div><div class="kpi-label">{{ __('Backups') }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="kpi kpi-violet"><span class="kpi-icon"><x-icon name="download" /></span><div><div class="kpi-value" dir="ltr">{{ human_size($total) }}</div><div class="kpi-label">{{ __('Space used') }}</div></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h2 class="h5 card-title mb-0">{{ $chosen ? $chosen->name : __('All backups') }}</h2>
        <form method="GET"><select class="form-select form-select-sm" name="tenant" data-plain onchange="this.form.submit()" aria-label="{{ __('Clinic') }}"><option value="">{{ __('Every clinic') }}</option>@foreach ($tenants as $tenant)<option value="{{ $tenant->id }}" @selected($chosen?->id === $tenant->id)>{{ $tenant->name }}</option>@endforeach</select></form>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Clinic') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('File') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td dir="ltr">{{ $backup->created_at?->format('Y-m-d H:i') }}</td><td>{{ $backup->tenant?->name }}</td>
                        <td><span class="badge {{ $backup->kind === 'auto' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }}">{{ $backup->kind === 'auto' ? __('Nightly') : __('By hand') }}</span></td>
                        <td class="text-end" dir="ltr">{{ human_size($backup->size) }}</td><td dir="ltr" class="text-muted small">{{ basename($backup->file) }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('admin.backups.download', ['id' => $backup->id]) }}"><x-icon name="download" class="me-1" /> {{ __('Download') }}</a>
                            <button type="button" class="btn btn-sm btn-light-danger" data-bs-toggle="modal" data-bs-target="#restore{{ $backup->id }}"><x-icon name="rotate-left" class="me-1" /> {{ __('Put back') }}</button>
                            <form method="POST" action="{{ route('admin.backups.destroy', ['id' => $backup->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">@csrf @method('DELETE')<x-act kind="delete" /></form>
                            <div class="modal fade text-start" id="restore{{ $backup->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.backups.restore', ['id' => $backup->id]) }}" class="modal-content">
                                @csrf
                                <div class="modal-header"><h2 class="h5 modal-title">{{ __('Put this backup back?') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                                <div class="modal-body text-wrap">
                                    <div class="service-strip is-danger"><x-icon name="triangle-exclamation" /><div><strong>{{ $backup->tenant?->name }} · <span dir="ltr">{{ $backup->created_at?->format('Y-m-d H:i') }}</span></strong>{{ __('Everything the clinic recorded after this moment will be replaced. What it has now is saved as a new backup first.') }}</div></div>
                                    <label class="form-label" for="r-{{ $backup->id }}">{{ __('Type the short name of the clinic to go on') }}: <code>{{ $backup->tenant?->slug }}</code></label>
                                    <input type="text" class="form-control" id="r-{{ $backup->id }}" name="confirm" dir="ltr" autocomplete="off" required>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Put back') }}</button></div>
                            </form></div></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $backups->links('partials.pagination') }}
    </div>
</div>
@endsection
