@extends('layouts.central', ['title' => $seller->name])

{{-- One reseller: his account with us, his clients, and every line that moved the account. --}}
@section('content')
@php $me = auth('admin')->user(); $manage = $me->allows('billing.manage'); $N = \App\Support\Num::class; @endphp
<x-page-header :title="$seller->name" icon="handshake" :subtitle="__('Reseller').' · '.$seller->commissionLabel()">
    <a class="btn btn-light-secondary" href="{{ route('admin.sellers.index') }}"><x-icon name="arrow-left" class="me-1" /> {{ __('Resellers') }}</a>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi kpi-blue"><span class="kpi-icon"><x-icon name="money-bill-wave" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['sales']) }}</div><div class="kpi-label">{{ __('Sales') }}</div><div class="kpi-note">{{ $account['clients'] }} {{ __('Clinics') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-green"><span class="kpi-icon"><x-icon name="percent" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['commission']) }}</div><div class="kpi-label">{{ __('Commission earned') }}</div><div class="kpi-note" dir="ltr">${{ money($account['paid_out']) }} {{ __('paid to him') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi kpi-amber"><span class="kpi-icon"><x-icon name="hand-holding-dollar" /></span><div><div class="kpi-value" dir="ltr">${{ money($account['collected']) }}</div><div class="kpi-label">{{ __('Taken from clients') }}</div><div class="kpi-note" dir="ltr">${{ money($account['received']) }} {{ __('handed over') }}</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $N::isPositive($account['balance']) ? 'kpi-red' : ($N::isNegative($account['balance']) ? 'kpi-amber' : 'kpi-green') }}"><span class="kpi-icon"><x-icon name="wallet" /></span><div><div class="kpi-value" dir="ltr">${{ money($N::abs($account['balance'])) }}</div><div class="kpi-label">{{ $N::isPositive($account['balance']) ? __('He owes us') : ($N::isNegative($account['balance']) ? __('We owe him') : __('Settled')) }}</div></div></div></div>
</div>

@include('central.partials.seller-claims', ['claims' => $claims, 'withSeller' => false])

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Statement of account') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What happened') }}</th><th class="text-end">{{ __('He owes') }}</th><th class="text-end">{{ __('He is credited') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="no-print"></th></tr></thead>
                    <tbody>
                        @forelse ($statement as $line)
                            <tr>
                                <td dir="ltr" class="text-nowrap">{{ $line['date'] }}</td>
                                <td>{{ __($line['what']) }}<div class="small text-muted">{{ $line['detail'] }}</div></td>
                                <td class="text-end" dir="ltr">{{ $N::isZero($line['owes']) ? '' : '$'.money($line['owes']) }}</td>
                                <td class="text-end" dir="ltr">{{ $N::isZero($line['earns']) ? '' : '$'.money($line['earns']) }}</td>
                                <td class="text-end fw-semibold {{ $N::isPositive($line['balance']) ? 'text-danger' : ($N::isNegative($line['balance']) ? 'text-warning' : '') }}" dir="ltr">{{ $N::isNegative($line['balance']) ? '−' : '' }}${{ money($N::abs($line['balance'])) }}</td>
                                <td class="text-end no-print">
                                    @if ($line['settlement'] && $manage)
                                        <form method="POST" action="{{ route('admin.sellers.cancel', ['id' => $seller->id, 'settlement' => $line['settlement']->id]) }}" data-confirm="{{ __('Cancel this? It no longer counts in the account.') }}" data-confirm-ok="{{ __('Cancel it') }}">@csrf @method('DELETE')<x-act kind="cancel" /></form>
                                    @elseif ($line['payment'])
                                        <x-act kind="receipt" :href="route('admin.payments.receipt', ['id' => $line['payment']->id])" blank />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="small text-muted mb-0">{{ __('He owes what he takes from clients and what we pay him; he is credited his commission and what he hands over. A balance above zero is owed to us; with a minus we owe him.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        @if ($manage)
            <form method="POST" action="{{ route('admin.sellers.settle', ['id' => $seller->id]) }}" class="card mb-3">
                @csrf
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Money between us') }}</h2></div>
                <div class="card-body row g-3">
                    <div class="col-12"><label class="form-label" for="s-direction">{{ __('What happened') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="s-direction" name="direction" required data-plain>@foreach (\App\Models\Central\SellerSettlement::DIRECTIONS as $key => $label)<option value="{{ $key }}" @selected(old('direction', $N::isNegative($account['balance']) ? 'out' : 'in') === $key)>{{ __($label) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="s-amount">{{ __('Amount') }} <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">$</span><input type="text" inputmode="decimal" class="form-control @error('amount') is-invalid @enderror" id="s-amount" name="amount" value="{{ old('amount', $N::isZero($account['balance']) ? '' : $N::abs($account['balance'])) }}" required dir="ltr" autocomplete="off"></div>@error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="col-6"><label class="form-label" for="s-method">{{ __('Paid by') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="s-method" name="method" required data-plain>@foreach (\App\Models\Central\Payment::METHODS as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ __($label) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="s-date">{{ __('Date') }} <span class="text-danger">*</span></label><input type="date" class="form-control @error('paid_on') is-invalid @enderror" id="s-date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required></div>
                    <div class="col-6"><label class="form-label" for="s-ref">{{ __('Slip or transaction number') }}</label><input type="text" class="form-control" id="s-ref" name="reference" value="{{ old('reference') }}" dir="ltr"></div>
                    <div class="col-12"><label class="form-label" for="s-note">{{ __('Note') }}</label><input type="text" class="form-control" id="s-note" name="note" value="{{ old('note') }}" maxlength="500"></div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
            </form>
            {{-- his sign-in to the panel of the resellers --}}
            <form method="POST" action="{{ route('admin.sellers.login', ['id' => $seller->id]) }}" class="card mb-3" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 card-title mb-0">{{ __('Sign-in to his panel') }}</h2>
                    @if ($seller->canSignIn())<span class="badge bg-success-subtle text-success">{{ __('Can sign in') }}</span>@else<span class="badge bg-secondary-subtle text-secondary">{{ __('No sign-in yet') }}</span>@endif</div>
                <div class="card-body row g-3">
                    @if ($errors->login->any())<div class="col-12"><div class="alert alert-danger py-2 mb-0">{{ $errors->login->first() }}</div></div>@endif
                    <div class="col-12"><label class="form-label" for="g-email">{{ __('Email') }} <span class="text-danger">*</span></label><input type="email" class="form-control" id="g-email" name="email" value="{{ old('email', $seller->email) }}" required maxlength="190" dir="ltr" autocomplete="off"></div>
                    <div class="col-6"><label class="form-label" for="g-pass">{{ __('New password') }}</label><input type="password" class="form-control" id="g-pass" name="password" minlength="8" dir="ltr" autocomplete="new-password" @required(! $seller->password)></div>
                    <div class="col-6"><label class="form-label" for="g-pass2">{{ __('Repeat the password') }}</label><input type="password" class="form-control" id="g-pass2" name="password_confirmation" minlength="8" dir="ltr" autocomplete="new-password" @required(! $seller->password)></div>
                    <div class="col-12 small text-muted">{{ __('He signs in at') }} <a href="{{ route('reseller.login') }}" dir="ltr" target="_blank" rel="noopener">{{ route('reseller.login') }}</a>@if ($seller->last_login_at) · {{ __('last sign-in') }} <span dir="ltr">{{ $seller->last_login_at->format('Y-m-d H:i') }}</span>@endif</div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-light-secondary" type="submit"><x-icon name="key" class="me-1" /> {{ __('Save') }}</button></div>
            </form>
            <form method="POST" action="{{ route('admin.sellers.update', ['id' => $seller->id]) }}" class="card mb-0">
                @csrf @method('PUT')
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Details and commission') }}</h2></div>
                <div class="card-body">@include('central.partials.seller-fields', ['seller' => $seller])
                    <p class="small text-muted mt-3 mb-0">{{ __('A new commission counts for the payments recorded from now on; earlier ones keep theirs.') }}</p></div>
                <div class="card-footer text-end"><button class="btn btn-light-secondary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
            </form>
        @else
            <div class="card mb-0"><div class="card-body"><dl class="facts mb-0">
                <div><dt>{{ __('Phone') }}</dt><dd dir="ltr">{{ $seller->phone ?: '—' }}</dd></div>
                <div><dt>{{ __('Email') }}</dt><dd dir="ltr">{{ $seller->email ?: '—' }}</dd></div>
                <div><dt>{{ __('Commission') }}</dt><dd>{{ $seller->commissionLabel() }}</dd></div>
            </dl></div></div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 card-title mb-0">{{ __('His clinics') }}</h2>
        @if ($manage && $free->isNotEmpty())
            <form method="POST" action="{{ route('admin.sellers.assign') }}" class="d-flex gap-2 no-print">@csrf<input type="hidden" name="seller_id" value="{{ $seller->id }}">
                <select class="form-select form-select-sm" name="tenant_id" required data-plain aria-label="{{ __('Clinic') }}"><option value="">{{ __('Add a clinic…') }}</option>@foreach ($free as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach</select>
                <button class="btn btn-sm btn-primary" type="submit"><x-icon name="plus" /></button></form>
        @endif
    </div>
    <div class="card-body">
        <table class="table table-hover w-100 align-middle">
            <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Status') }}</th><th>{{ __('Licence until') }}</th><th class="text-end">{{ __('Paid through him') }}</th><th class="text-end">{{ __('Commission') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($clients as $row)
                    <tr>
                        <td><a class="fw-semibold" href="{{ route('admin.tenants.show', ['id' => $row['tenant']->id]) }}">{{ $row['tenant']->name }}</a><div class="small text-muted" dir="ltr">{{ $row['tenant']->domain }}</div></td>
                        <td>{{ __(\App\Models\Central\Tenant::STATUSES[$row['tenant']->status] ?? $row['tenant']->status) }}</td>
                        <td dir="ltr">{{ $row['tenant']->licence_expires_on?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end" dir="ltr">${{ money($row['paid']) }}</td>
                        <td class="text-end" dir="ltr">${{ money($row['commission']) }}</td>
                        <td class="text-end no-print"><div class="act-group">
                            @if ($manage)<a class="btn btn-sm btn-light-secondary" href="{{ route('admin.payments.index', ['tenant' => $row['tenant']->id]) }}">{{ __('Record a payment') }}</a>
                                <form method="POST" action="{{ route('admin.sellers.assign') }}" data-confirm="{{ __('Take this clinic away from him?') }}" data-confirm-ok="{{ __('app.actions.yes') }}">@csrf<input type="hidden" name="tenant_id" value="{{ $row['tenant']->id }}"><x-act kind="cancel" /></form>@endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
