{{-- The fields of a reseller, for the "new" card and the "edit" card. --}}
@php $v = fn (string $key, $default = '') => old($key, $seller?->{$key} ?? $default); $id = $seller ? 'e' : 'n'; @endphp
<div class="row g-3">
    <div class="col-12"><label class="form-label" for="{{ $id }}-name">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" class="form-control @error('name') is-invalid @enderror" id="{{ $id }}-name" name="name" value="{{ $v('name') }}" required maxlength="150">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-6"><label class="form-label" for="{{ $id }}-phone">{{ __('Phone') }}</label><input type="text" class="form-control" id="{{ $id }}-phone" name="phone" value="{{ $v('phone') }}" maxlength="40" dir="ltr"></div>
    <div class="col-6"><label class="form-label" for="{{ $id }}-email">{{ __('Email') }}</label><input type="email" class="form-control @error('email') is-invalid @enderror" id="{{ $id }}-email" name="email" value="{{ $v('email') }}" maxlength="190" dir="ltr"></div>
    <div class="col-7"><label class="form-label" for="{{ $id }}-type">{{ __('Commission') }} <span class="text-danger">*</span></label>
        <select class="form-select" id="{{ $id }}-type" name="commission_type" required data-plain>@foreach (\App\Models\Central\Seller::COMMISSIONS as $key => $label)<option value="{{ $key }}" @selected($v('commission_type', 'percent') === $key)>{{ __($label) }}</option>@endforeach</select></div>
    <div class="col-5"><label class="form-label" for="{{ $id }}-value">{{ __('Percent or amount') }} <span class="text-danger">*</span></label><input type="text" inputmode="decimal" class="form-control @error('commission_value') is-invalid @enderror" id="{{ $id }}-value" name="commission_value" value="{{ $v('commission_value', '10') }}" required dir="ltr" autocomplete="off">@error('commission_value')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><label class="form-label" for="{{ $id }}-note">{{ __('Note') }}</label><input type="text" class="form-control" id="{{ $id }}-note" name="note" value="{{ $v('note') }}" maxlength="500"></div>
    @unless ($seller)
        {{-- his sign-in to the panel of the resellers: with an email and a password he can sign in at once --}}
        <div class="col-6"><label class="form-label" for="n-pass">{{ __('Password') }}</label><input type="password" class="form-control @error('password') is-invalid @enderror" id="n-pass" name="password" minlength="8" dir="ltr" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-6"><label class="form-label" for="n-pass2">{{ __('Repeat the password') }}</label><input type="password" class="form-control" id="n-pass2" name="password_confirmation" minlength="8" dir="ltr" autocomplete="new-password"></div>
        <div class="col-12 form-text mt-1">{{ __('With an email and a password he signs in at') }} <span dir="ltr">{{ route('reseller.login') }}</span>. {{ __('You can also set them later on his page.') }}</div>
    @endunless
    @if ($seller)
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="e-active" name="is_active" value="1" @checked(old('is_active', $seller->is_active))><label class="form-check-label" for="e-active">{{ __('Active') }}</label></div></div>
    @endif
</div>
