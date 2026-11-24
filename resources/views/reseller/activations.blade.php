@extends('layouts.reseller', ['title' => __('Activation')])

{{-- Ask for a new clinic to be opened, and follow the requests: waiting, opened, or rejected. --}}
@section('content')
<x-page-header :title="__('Activation')" icon="user-plus" :subtitle="__('Ask for a new clinic to be opened; it becomes your client when the administrator approves it')" />

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <form method="POST" action="{{ route('reseller.activate') }}" class="card h-100 mb-0" autocomplete="off">
            @csrf
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Open a new clinic') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="n-name">{{ __('Name of the clinic') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('name') is-invalid @enderror" id="n-name" name="name" value="{{ old('name') }}" required maxlength="150">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-6"><label class="form-label" for="n-local">{{ __('Name in Dari or Pashto') }}</label><input type="text" class="form-control" id="n-local" name="name_local" value="{{ old('name_local') }}" maxlength="150"></div>
                <div class="col-12"><label class="form-label" for="n-slug">{{ __('Web address of your clinic (subdomain)') }} <span class="text-danger">*</span></label>
                    <div class="input-group" dir="ltr"><input type="text" class="form-control @error('slug') is-invalid @enderror" id="n-slug" name="slug" value="{{ old('slug') }}" required maxlength="40" pattern="[a-z][a-z0-9\-]{2,39}" autocapitalize="none" spellcheck="false" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9-]+/g, '')"><span class="input-group-text">.{{ config('tenancy.base_domain') }}</span></div>
                    @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-6 col-md-4"><label class="form-label" for="n-licence">{{ __('Licence number') }}</label><input type="text" class="form-control" id="n-licence" name="licence_no" value="{{ old('licence_no') }}" maxlength="60" dir="ltr"></div>
                <div class="col-6 col-md-4"><label class="form-label" for="n-province">{{ __('Province') }}</label><input type="text" class="form-control" id="n-province" name="province" value="{{ old('province') }}" maxlength="100"></div>
                <div class="col-12 col-md-4"><label class="form-label" for="n-branches">{{ __('Dependent branches') }}</label><input type="text" inputmode="numeric" class="form-control @error('branches') is-invalid @enderror" id="n-branches" name="branches" value="{{ old('branches', 0) }}" dir="ltr"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="n-owner">{{ __('Name of the owner') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="n-owner" name="owner_name" value="{{ old('owner_name') }}" required maxlength="150"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="n-phone">{{ __('Phone') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('owner_phone') is-invalid @enderror" id="n-phone" name="owner_phone" value="{{ old('owner_phone') }}" required maxlength="40" dir="ltr">@error('owner_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="n-email">{{ __('Email of the owner (he signs in with it)') }} <span class="text-danger">*</span></label><input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="n-email" name="owner_email" value="{{ old('owner_email') }}" required maxlength="190" dir="ltr" autocomplete="off">@error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-6"><label class="form-label" for="n-pass">{{ __('Password of the owner') }} <span class="text-danger">*</span></label><input type="password" class="form-control @error('password') is-invalid @enderror" id="n-pass" name="password" required minlength="8" dir="ltr" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-6"><label class="form-label" for="n-pass2">{{ __('Repeat the password') }} <span class="text-danger">*</span></label><input type="password" class="form-control" id="n-pass2" name="password_confirmation" required minlength="8" dir="ltr" autocomplete="new-password"></div>
                <div class="col-12"><label class="form-label" for="n-message">{{ __('Note') }}</label><input type="text" class="form-control" id="n-message" name="message" value="{{ old('message') }}" maxlength="1000"></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary" type="submit"><x-icon name="paper-plane" class="me-1" /> {{ __('Send the request') }}</button></div>
        </form>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('My requests') }}</h2></div>
            <div class="card-body">
                <table class="table table-hover w-100 align-middle">
                    <thead><tr><th>{{ __('Clinic') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Sent on') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($requests as $r)
                            <tr>
                                <td><span class="fw-semibold">{{ $r->name }}</span><div class="small text-muted" dir="ltr">{{ $r->domain() }}</div></td>
                                <td>{{ $r->owner_name }}<div class="small text-muted" dir="ltr">{{ $r->owner_phone }}</div></td>
                                <td dir="ltr" class="text-nowrap">{{ $r->created_at->format('Y-m-d') }}</td>
                                <td>
                                    @if ($r->status === 'approved')<span class="badge bg-success-subtle text-success">{{ __('Opened') }}</span>@if ($r->tenant)<div class="small"><a href="//{{ $r->tenant->domain }}/login" target="_blank" rel="noopener" dir="ltr">{{ $r->tenant->domain }}</a></div>@endif
                                    @elseif ($r->status === 'rejected')<span class="badge bg-danger-subtle text-danger">{{ __('Rejected') }}</span><div class="small text-muted">{{ $r->reason }}</div>
                                    @else<span class="badge bg-warning-subtle text-warning">{{ __('Waiting for approval') }}</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
