@extends('layouts.central', ['title' => __('Registration requests')])

@section('content')
<x-page-header :title="__('Registration requests')" icon="user-plus" :subtitle="__('Clinics that asked for the system on the website')">
    <a class="btn btn-light-secondary" href="{{ url('/register') }}" target="_blank" rel="noopener"><x-icon name="arrow-up-right-from-square" class="me-1" /> {{ __('The form on the website') }}</a>
</x-page-header>

<div class="card">
    <div class="card-header">
        <div class="seg">
            @foreach (\App\Models\Central\Signup::STATUSES as $key => $label)
                <a href="{{ route('admin.signups.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ __($label) }}@if (($counts[$key] ?? 0) > 0) <span class="badge {{ $key === 'pending' ? 'bg-danger' : 'bg-secondary' }}">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </div>
    </div>
    <div class="card-body">
        @if ($signups->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="user-plus" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            @php $canDelete = $status !== 'approved' && auth('admin')->user()->allows('clients.manage'); @endphp
            @if ($canDelete)
                {{-- one form for the ticked rows and for the bin of each row: the buttons and boxes point to it --}}
                <form id="signupsDelete" method="POST" action="{{ route('admin.signups.destroy') }}" data-confirm="{{ __('Delete these requests?') }}" data-confirm-text="{{ __('They are removed for good.') }}" data-confirm-ok="{{ __('Delete') }}">@csrf</form>
                <div class="d-flex justify-content-end mb-2 no-print"><button type="submit" form="signupsDelete" class="btn btn-sm btn-light-danger"><x-icon name="trash" class="me-1" /> {{ __('Delete the ticked') }}</button></div>
            @endif
            <table class="table table-hover w-100 align-middle">
                <thead><tr>
                    @if ($canDelete)<th class="no-print" style="width:1%"><input class="form-check-input" type="checkbox" aria-label="{{ __('Tick all') }}" onclick="document.querySelectorAll('input[form=signupsDelete]').forEach(b => b.checked = this.checked)"></th>@endif
                    <th>{{ __('Clinic') }}</th><th>{{ __('Address') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Sent on') }}</th>
                    @if ($status !== 'pending')<th>{{ __('Decided') }}</th>@endif
                    <th class="text-end no-print">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($signups as $signup)
                        <tr>
                            @if ($canDelete)<td class="no-print"><input class="form-check-input" type="checkbox" form="signupsDelete" name="ids[]" value="{{ $signup->id }}" aria-label="{{ $signup->name }}"></td>@endif
                            <td><span class="fw-semibold">{{ $signup->name }}</span>@if ($signup->seller) <span class="badge bg-primary-subtle text-primary"><x-icon name="handshake" /> {{ $signup->seller->name }}</span>@endif @if ($signup->name_local)<div class="small text-muted" dir="rtl">{{ $signup->name_local }}</div>@endif
                                <div class="small text-muted">{{ implode(' · ', array_filter([$signup->clinic_type ? __(\App\Models\Central\Signup::TYPES[$signup->clinic_type] ?? $signup->clinic_type) : null, $signup->doctors ? trans_choice(':count doctor|:count doctors', $signup->doctors) : null, $signup->licence_no, $signup->city, $signup->province])) }}</div></td>
                            <td dir="ltr">@if ($signup->tenant)<a href="//{{ $signup->tenant->domain }}" target="_blank" rel="noopener">{{ $signup->tenant->domain }}</a>@else{{ $signup->domain() }}@endif</td>
                            <td>{{ $signup->owner_name }}<div class="small text-muted" dir="ltr">{{ $signup->owner_phone }} · {{ $signup->owner_email }}</div>@if ($signup->message)<div class="small">{{ $signup->message }}</div>@endif</td>
                            <td>{{ $signup->branches }}</td>
                            <td dir="ltr">{{ $signup->created_at->format('Y-m-d H:i') }}</td>
                            @if ($status !== 'pending')<td>{{ $signup->decider?->name }}<div class="small text-muted" dir="ltr">{{ $signup->decided_at?->format('Y-m-d H:i') }}</div>@if ($signup->reason)<div class="small">{{ $signup->reason }}</div>@endif</td>@endif
                            <td class="text-end no-print">
                                @if ($signup->isPending() && auth('admin')->user()->allows('clients.manage'))
                                    <div class="act-group">
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approve{{ $signup->id }}"><x-icon name="check" class="me-1" /> {{ __('Approve') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject{{ $signup->id }}"><x-icon name="xmark" class="me-1" /> {{ __('Reject') }}</button>
                                        <button type="submit" form="signupsDelete" name="only" value="{{ $signup->id }}" class="btn btn-sm btn-light-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button>
                                    </div>
                                @elseif ($canDelete)
                                    <div class="act-group"><button type="submit" form="signupsDelete" name="only" value="{{ $signup->id }}" class="btn btn-sm btn-light-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></div>
                                @elseif ($signup->tenant)
                                    <x-act kind="view" :href="route('admin.tenants.show', ['id' => $signup->tenant_id])" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $signups->links() }}
        @endif
    </div>
</div>

@foreach ($signups as $signup)
    @continue(! $signup->isPending())
    <div class="modal fade" id="approve{{ $signup->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.signups.approve', ['id' => $signup->id]) }}">@csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('Approve and open :name', ['name' => $signup->name]) }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <p class="text-muted">{{ $signup->tenant ? __('The clinic is made already and its owner can sign in, but only his subscription page opens. Approving opens the whole system.') : __('The clinic is opened now: its database, its main branch and its owner, who signs in with the password chosen on the website.') }}</p>
                <div class="mb-3"><label class="form-label" for="slug{{ $signup->id }}">{{ __('Address') }} <span class="req">*</span></label>
                    <div class="input-group" dir="ltr"><input type="text" class="form-control" id="slug{{ $signup->id }}" name="slug" value="{{ $signup->slug }}" required maxlength="40" autocomplete="off" @readonly($signup->tenant)><span class="input-group-text">.{{ config('tenancy.base_domain') }}</span></div></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="until{{ $signup->id }}">{{ __('Licence until') }}</label><input type="date" class="form-control" id="until{{ $signup->id }}" name="licence_expires_on" value="{{ now()->addYear()->toDateString() }}"></div>
                    <div class="col-md-6"><label class="form-label" for="max{{ $signup->id }}">{{ __('Dependent branches allowed') }}</label><input type="text" inputmode="numeric" dir="ltr" class="form-control" id="max{{ $signup->id }}" name="max_branches" value="{{ $signup->branches }}" autocomplete="off"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-success"><x-icon name="check" class="me-1" /> {{ __('Approve') }}</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="reject{{ $signup->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.signups.reject', ['id' => $signup->id]) }}">@csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('Reject the request of :name', ['name' => $signup->name]) }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <label class="form-label" for="reason{{ $signup->id }}">{{ __('Reason') }} <span class="req">*</span></label>
                <textarea class="form-control" id="reason{{ $signup->id }}" name="reason" rows="3" maxlength="500" required></textarea>
                <div class="form-text">{{ __('The applicant reads this on the page of the request.') }}</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Reject') }}</button></div>
        </form></div>
    </div>
@endforeach
@endsection
