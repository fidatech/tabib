@extends('layouts.reseller', ['title' => __('Profile and password')])

@section('content')
<x-page-header :title="__('Profile and password')" icon="id-badge" />

<form method="POST" action="{{ route('reseller.account') }}" class="row g-3">
    @csrf
    @method('PUT')
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Profile') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label" for="a-name">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('name') is-invalid @enderror" id="a-name" name="name" value="{{ old('name', $me->name) }}" required maxlength="150">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-6"><label class="form-label" for="a-phone">{{ __('Phone') }}</label><input type="text" class="form-control" id="a-phone" name="phone" value="{{ old('phone', $me->phone) }}" dir="ltr"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="a-email">{{ __('Email') }}</label><input type="text" class="form-control" id="a-email" value="{{ $me->email }}" dir="ltr" readonly></div>
                <div class="col-12"><label class="form-label" for="a-commission">{{ __('Commission') }}</label><input type="text" class="form-control" id="a-commission" value="{{ $me->commissionLabel() }} · {{ __(\App\Models\Central\Seller::COMMISSIONS[$me->commission_type]) }}" readonly></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Change Password') }}</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label" for="a-current">{{ __('Current password') }}</label><input type="password" class="form-control @error('current_password') is-invalid @enderror" id="a-current" name="current_password" autocomplete="current-password" dir="ltr">@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-6"><label class="form-label" for="a-new">{{ __('New password') }}</label><input type="password" class="form-control @error('password') is-invalid @enderror" id="a-new" name="password" autocomplete="new-password" dir="ltr">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-6"><label class="form-label" for="a-again">{{ __('New password again') }}</label><input type="password" class="form-control" id="a-again" name="password_confirmation" autocomplete="new-password" dir="ltr"></div>
                <div class="col-12 form-text">{{ __('Leave the three boxes empty to keep your password.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
</form>
@endsection
