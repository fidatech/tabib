@extends('layouts.central', ['title' => $tenant->name])

@section('content')
@php $me = auth('admin')->user(); @endphp
<x-page-header :title="$tenant->name" icon="house-medical" :subtitle="$tenant->domain">
    <a class="btn btn-outline-soft" href="{{ route('admin.tenants.index') }}"><x-icon name="arrow-left" class="me-1" /> {{ __('Back') }}</a>
    @if ($me->allows('clients.manage'))<a class="btn btn-outline-soft" href="{{ route('admin.tenants.edit', ['id' => $tenant->id]) }}"><x-icon name="pen" class="me-1" /> {{ __('Edit') }}</a>@endif
    @if ($me->allows('billing.manage'))<a class="btn btn-primary" href="{{ route('admin.invoices.index', ['tenant' => $tenant->id]) }}"><x-icon name="receipt" class="me-1" /> {{ __('New invoice') }}</a>@endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="code-branch" /></span><div><div class="kpi-value">{{ $inside ? count($inside['branches']) : '—' }}</div><div class="kpi-label">{{ __('Branches') }}</div><div class="kpi-note">{{ $inside['users'] ?? '—' }} {{ __('users') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-violet"><span class="kpi-icon"><x-icon name="list-check" /></span><div><div class="kpi-value">{{ $inside ? number_format($inside['activity']) : '—' }}</div><div class="kpi-label">{{ __('Activity in 30 days') }}</div><div class="kpi-note">{{ __('entries in the audit log') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ ['ok' => 'kpi-green', 'soon' => 'kpi-amber'][$licence['state']] ?? 'kpi-red' }}"><span class="kpi-icon"><x-icon name="calendar-days" /></span><div><div class="kpi-value" dir="ltr">{{ $tenant->licence_expires_on?->format('Y-m-d') ?? '—' }}</div><div class="kpi-label">{{ __('Licence until') }}</div><div class="kpi-note">@if ($licence['days'] === null){{ __('No date') }}@elseif ($licence['days'] >= 0){{ __(':days days left', ['days' => $licence['days']]) }}@else{{ __('Ended :days days ago', ['days' => abs($licence['days'])]) }}@endif</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ \App\Support\Num::isPositive($balance['due']) ? 'kpi-amber' : 'kpi-green' }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($balance['due']) }}</div><div class="kpi-label">{{ __('Still to be paid') }}</div><div class="kpi-note" dir="ltr">${{ money($balance['paid']) }} / ${{ money($balance['invoiced']) }}</div></div></div></div>
</div>

@unless ($inside)
    <div class="service-strip is-danger"><x-icon name="triangle-exclamation" /><div><strong>{{ __('The database of this clinic could not be opened.') }}</strong>{{ __('See System health for the reason.') }}</div></div>
@endunless

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('About the clinic') }}</h2></div>
            <div class="card-body">
                <dl class="facts">
                    <div><dt>{{ __('Status') }}</dt><dd>@if ($tenant->status === 'active')<span class="badge bg-success-subtle text-success">{{ __('Active') }}</span>@elseif ($tenant->status === 'pending')<span class="badge bg-warning-subtle text-warning">{{ __('Waiting for approval') }}</span>@else<span class="badge bg-danger-subtle text-danger">{{ __(\App\Models\Central\Tenant::STATUSES[$tenant->status]) }}@if ($tenant->locked_reason === 'licence') · {{ __('licence ended') }}@endif</span>@endif</dd></div>
                    <div><dt>{{ __('Name in Dari or Pashto') }}</dt><dd>{{ $tenant->name_local ?: '—' }}</dd></div>
                    <div><dt>{{ __('Health ministry licence number') }}</dt><dd dir="ltr">{{ $tenant->licence_no ?: '—' }}</dd></div>
                    <div><dt>{{ __('Address') }}</dt><dd dir="ltr"><a href="//{{ $tenant->domain }}" target="_blank" rel="noopener">{{ $tenant->domain }}</a></dd></div>
                    <div><dt>{{ __('Short name') }}</dt><dd dir="ltr">{{ $tenant->slug }}</dd></div>
                    <div><dt>{{ __('Client since') }}</dt><dd dir="ltr">{{ $tenant->created_at?->format('Y-m-d') }}</dd></div>
                    <div><dt>{{ __('Last activity') }}</dt><dd dir="ltr">{{ $inside && $inside['last'] ? \Illuminate\Support\Carbon::parse($inside['last'])->diffForHumans() : '—' }}</dd></div>
                    <div><dt>{{ __('Size of the database') }}</dt><dd dir="ltr">{{ $inside ? human_size($inside['size']) : '—' }}</dd></div>
                    @if ($inside)
                        @php $u = $inside['usage']; $of = fn ($n, $max) => $max === null ? number_format($n) : number_format($n).' / '.number_format($max); @endphp
                        <div><dt>{{ __('Patients') }}</dt><dd dir="ltr">{{ number_format($u['patients']) }}</dd></div>
                        <div><dt>{{ __('Visits this month') }}</dt><dd dir="ltr">{{ number_format($u['visits']) }}</dd></div>
                        <div><dt>{{ __('Doctors') }}</dt><dd dir="ltr" class="{{ $tenant->max_doctors !== null && $u['doctors'] >= $tenant->max_doctors ? 'text-danger fw-semibold' : '' }}">{{ $of($u['doctors'], $tenant->max_doctors) }}</dd></div>
                        <div><dt>{{ __('Text messages this month') }}</dt><dd dir="ltr" class="{{ $tenant->max_sms_month !== null && $u['sms'] >= $tenant->max_sms_month ? 'text-danger fw-semibold' : '' }}">{{ $of($u['sms'], $tenant->max_sms_month) }}</dd></div>
                        <div><dt>{{ __('Files') }}</dt><dd dir="ltr">{{ human_size($u['files']) }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <form method="POST" action="{{ route('admin.tenants.note', ['id' => $tenant->id]) }}" class="card h-100 mb-0">
            @csrf
            @method('PUT')
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Owner and notes') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label" for="o-name">{{ __('Full name of the owner') }}</label><input type="text" class="form-control" id="o-name" name="owner_name" value="{{ old('owner_name', $tenant->owner_name) }}"></div>
                <div class="col-6"><label class="form-label" for="o-phone">{{ __('Phone') }}</label><input type="text" class="form-control" id="o-phone" name="owner_phone" value="{{ old('owner_phone', $tenant->owner_phone) }}" dir="ltr"></div>
                <div class="col-6"><label class="form-label" for="o-email">{{ __('Email') }}</label><input type="email" class="form-control" id="o-email" name="owner_email" value="{{ old('owner_email', $tenant->owner_email) }}" dir="ltr"></div>
                <div class="col-12"><label class="form-label" for="o-notes">{{ __('Notes') }}</label><textarea class="form-control" id="o-notes" name="notes" rows="4">{{ old('notes', $tenant->notes) }}</textarea></div>
            </div>
            @if ($me->allows('clients.manage'))<div class="card-footer text-end"><button class="btn btn-light-secondary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>@endif
        </form>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Actions') }}</h2></div>
            <div class="card-body d-grid gap-3">
                @if ($me->allows('enter') && $tenant->status === 'active')
                    <form method="POST" action="{{ route('admin.tenants.enter', ['id' => $tenant->id]) }}" target="_blank">
                        @csrf
                        <label class="form-label" for="e-reason">{{ __('Enter its system as support') }} <x-help :text="__('You work as the account “Fida Support”. Your name and this reason are written in the audit log of the clinic, where its owner can read them.')" /></label>
                        <div class="input-group"><input type="text" class="form-control @error('reason') is-invalid @enderror" id="e-reason" name="reason" placeholder="{{ __('Reason, for example the ticket number') }}" required minlength="5" maxlength="200"><button class="btn btn-primary" type="submit" title="{{ __('Enter') }}" aria-label="{{ __('Enter') }}"><x-icon name="right-to-bracket" /></button></div>
                    </form>
                @endif
                @if ($me->allows('backups'))
                    <form method="POST" action="{{ route('admin.backups.store') }}">@csrf<input type="hidden" name="tenant_id" value="{{ $tenant->id }}"><button class="btn btn-outline-soft w-100" type="submit"><x-icon name="database" class="me-1" /> {{ __('Back up now') }}</button></form>
                @endif
                @if ($me->allows('billing.manage'))<a class="btn btn-outline-soft" href="{{ route('admin.payments.index', ['tenant' => $tenant->id]) }}"><x-icon name="money-bill-wave" class="me-1" /> {{ __('Record a payment') }}</a>@endif
                @if ($me->allows('billing.manage') && ($allSellers = \App\Models\Central\Seller::where('is_active', true)->orWhere('id', $tenant->seller_id)->orderBy('name')->get())->isNotEmpty())
                    {{-- who sold this clinic: his commission is worked out on its payments --}}
                    <form method="POST" action="{{ route('admin.sellers.assign') }}">@csrf<input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <label class="form-label" for="c-seller">{{ __('Reseller') }}</label>
                        <div class="input-group"><select class="form-select" id="c-seller" name="seller_id" data-plain><option value="">{{ __('No reseller (direct sale)') }}</option>@foreach ($allSellers as $s)<option value="{{ $s->id }}" @selected($tenant->seller_id === $s->id)>{{ $s->name }} · {{ $s->commissionLabel() }}</option>@endforeach</select><button class="btn btn-light-secondary" type="submit"><x-icon name="check" /></button></div>
                    </form>
                @endif
                @if ($me->allows('clients.manage') && ! empty($inside['logins']))
                    @if ($errors->login->any())<div class="alert alert-danger py-2 mb-0">{{ $errors->login->first() }}</div>@endif
                    <button type="button" class="btn btn-outline-soft w-100" data-bs-toggle="modal" data-bs-target="#loginModal"><x-icon name="key" class="me-1" /> {{ __('Change email or password') }}</button>
                @endif
                @if ($me->allows('clients.manage'))
                    <form method="POST" action="{{ route('admin.tenants.status', ['id' => $tenant->id]) }}" data-confirm="{{ __('Change the status of this clinic?') }}" data-confirm-ok="{{ __('app.actions.yes') }}">
                        @csrf
                        @if ($tenant->status === 'active')<button type="submit" name="status" value="suspended" class="btn btn-light-danger w-100"><x-icon name="lock" class="me-1" /> {{ __('Suspend') }}</button>
                        @else<button type="submit" name="status" value="active" class="btn btn-success w-100"><x-icon name="lock-open" class="me-1" /> {{ __('Activate') }}</button>@endif
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Branches') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Place') }}</th><th class="text-end">{{ __('Users') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($inside['branches'] ?? [] as $branch)
                            <tr><td class="fw-semibold">{{ $branch['name'] }}</td><td>{{ __($branch['type']) }}</td><td>{{ $branch['place'] }}</td><td class="text-end">{{ $branch['users'] }}</td><td><span class="dot {{ $branch['active'] ? 'dot-ok' : '' }}"></span>{{ $branch['active'] ? __('Active') : __('Not active') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Invoices') }}</h2><a class="small" href="{{ route('admin.payments.index', ['tenant' => $tenant->id]) }}">{{ __('Payments') }}</a></div>
            <div class="card-body">
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr><td>{{ $invoice->number }}</td><td dir="ltr">{{ $invoice->from_date->format('Y-m-d') }}</td><td dir="ltr">{{ $invoice->to_date->format('Y-m-d') }}</td><td class="text-end" dir="ltr">${{ money($invoice->total) }}</td>
                                <td><span class="badge {{ ['paid' => 'bg-success-subtle text-success', 'unpaid' => 'bg-warning-subtle text-warning'][$invoice->status] ?? 'bg-secondary-subtle text-secondary' }}">{{ __(\App\Models\Central\Invoice::STATUSES[$invoice->status]) }}</span></td>
                                <td class="text-end no-print"><div class="act-group"><x-act kind="receipt" :href="route('admin.invoices.show', ['id' => $invoice->id])" :label="__('Print')" blank /></div></td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Support tickets') }}</h2></div><div class="card-body">
            @forelse ($tickets as $ticket)
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><a href="{{ route('admin.tickets.show', ['id' => $ticket->id]) }}">{{ $ticket->subject }}</a><span class="badge {{ ['open' => 'bg-danger-subtle text-danger', 'answered' => 'bg-success-subtle text-success'][$ticket->status] ?? 'bg-secondary-subtle text-secondary' }} align-self-start">{{ __(\App\Models\Central\Ticket::STATUSES[$ticket->status]) }}</span></div>
            @empty
                <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
            @endforelse
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Backups') }}</h2></div><div class="card-body">
            @forelse ($backups as $backup)
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><span dir="ltr">{{ $backup->created_at?->format('Y-m-d H:i') }}</span><span class="text-muted" dir="ltr">{{ human_size($backup->size) }}</span></div>
            @empty
                <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
            @endforelse
        </div></div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0"><div class="card-header"><h2 class="h5 card-title mb-0">{{ __('What we did for this clinic') }}</h2></div><div class="card-body">
            @forelse ($logs as $log)
                <div class="py-2 border-bottom"><div class="d-flex justify-content-between gap-3"><strong>{{ __(ucfirst($log->action)) }}</strong><span class="small text-muted" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</span></div><div class="small text-muted">{{ $log->admin?->name }} · {{ $log->description }}</div></div>
            @empty
                <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
            @endforelse
        </div></div>
    </div>
</div>
@if ($me->allows('clients.manage') && ! empty($inside['logins']))
    {{-- the email or the password one person of this clinic signs in with --}}
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.tenants.login', ['id' => $tenant->id]) }}" autocomplete="off">@csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('Change email or password') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="l-user">{{ __('User') }} <span class="req">*</span></label>
                    <select class="form-select" id="l-user" name="user_id" required data-plain onchange="var o = this.options[this.selectedIndex]; document.getElementById('l-email').value = o.getAttribute('data-email')">
                        @foreach ($inside['logins'] as $login)
                            <option value="{{ $login['id'] }}" data-email="{{ $login['email'] }}" @selected((string) old('user_id', $inside['logins'][0]['id']) === (string) $login['id'])>{{ $login['name'] }}{{ $login['role'] ? ' · '.__($login['role']) : '' }}{{ $login['active'] ? '' : ' · '.__('Inactive') }}</option>
                        @endforeach
                    </select></div>
                @php $chosen = collect($inside['logins'])->firstWhere('id', (int) old('user_id', $inside['logins'][0]['id'])) ?? $inside['logins'][0]; @endphp
                <div class="mb-3"><label class="form-label" for="l-email">{{ __('Email') }} <span class="req">*</span></label><input type="email" class="form-control" id="l-email" name="email" value="{{ old('email', $chosen['email']) }}" required maxlength="255" dir="ltr" autocomplete="off"></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="l-pass">{{ __('New password') }}</label><input type="password" class="form-control" id="l-pass" name="password" minlength="8" dir="ltr" autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label" for="l-pass2">{{ __('Repeat the password') }}</label><input type="password" class="form-control" id="l-pass2" name="password_confirmation" minlength="8" dir="ltr" autocomplete="new-password"></div>
                </div>
                <div class="form-text">{{ __('Leave the password empty to keep the one he has. The change is written in the log of this client.') }}</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
        </form></div>
    </div>
@endif
@endsection
