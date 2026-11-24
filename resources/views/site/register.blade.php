@extends('layouts.site', ['title' => __('Register your clinic')])

@section('content')
<section class="site-section">
    <div class="site-wrap site-narrow">
        <h1 class="site-title">{{ __('Register your clinic') }}</h1>
        <p class="site-lead">{{ __('Fill in the form. Our team checks the request; once it is approved, your system is opened at your own address.') }}</p>

        {{-- three steps; without JavaScript every step is shown at once and the form still works --}}
        <ol class="site-wizard-steps" data-wizard-steps aria-label="{{ __('Steps') }}">
            <li class="is-current" data-step-label="1"><span>1</span> {{ __('The clinic') }}</li>
            <li data-step-label="2"><span>2</span> {{ __('The owner') }}</li>
            <li data-step-label="3"><span>3</span> {{ __('Price and send') }}</li>
        </ol>

        <form method="POST" action="{{ route('site.register.store') }}" class="card" data-wizard novalidate>
            @csrf
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ __('Please correct the boxes marked below.') }}</div>
                @endif
                <div class="site-trap" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <fieldset class="site-wizard-step" data-step="1">
                <legend class="site-form-head">{{ __('The clinic') }}</legend>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name">{{ __('Name of the clinic') }} <span class="req">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="150" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="name_local">{{ __('Name in Dari or Pashto') }}</label>
                        <input type="text" class="form-control" id="name_local" name="name_local" value="{{ old('name_local') }}" maxlength="150" dir="rtl">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="clinic_type">{{ __('Kind of clinic') }}</label>
                        <select class="form-select @error('clinic_type') is-invalid @enderror" id="clinic_type" name="clinic_type" data-plain>
                            @foreach (\App\Models\Central\Signup::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('clinic_type', 'general') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                        @error('clinic_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="licence_no">{{ __('Health ministry licence number') }}</label>
                        <input type="text" class="form-control" id="licence_no" name="licence_no" value="{{ old('licence_no') }}" maxlength="60" dir="ltr">
                        <div class="form-text">{{ __('Not needed now; you can add it later in the settings.') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="slug">{{ __('Web address of your clinic (subdomain)') }} <span class="req">*</span></label>
                        <div class="input-group" dir="ltr">
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" maxlength="40" required autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="url" pattern="[a-z][a-z0-9\-]{2,39}" placeholder="{{ __('yourclinic') }}" aria-describedby="slugHelp slugState"
                                data-check="{{ route('site.check') }}" data-free="{{ __('This address is free.') }}" data-taken="{{ __('This address is taken; choose another.') }}" data-short="{{ __('The address starts with a letter and has 3 to 40 small letters, digits or dashes.') }}">
                            <span class="input-group-text">.{{ config('tenancy.base_domain') }}</span>
                        </div>
                        @error('slug')<div class="invalid-feedback d-block" data-slug-error>{{ $message }}</div>@enderror
                        {{-- filled as it is typed: free or taken, and the full address the clinic will get --}}
                        <div class="site-slug-state" id="slugState" role="status" aria-live="polite" hidden></div>
                        <div class="form-text" id="slugHelp">{{ __('Write the name of your clinic in small English letters, without spaces. Example: for "Shifa Family Clinic" write shifa. Your staff open the system at this address.') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="province">{{ __('Province') }}</label>
                        <input type="text" class="form-control" id="province" name="province" value="{{ old('province') }}" maxlength="100">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="city">{{ __('City') }}</label>
                        <input type="text" class="form-control" id="city" name="city" value="{{ old('city') }}" maxlength="100">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="doctors">{{ __('Number of doctors') }}</label>
                        <input type="text" inputmode="numeric" dir="ltr" class="form-control @error('doctors') is-invalid @enderror" id="doctors" name="doctors" value="{{ old('doctors', 1) }}" autocomplete="off">
                        @error('doctors')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="branches">{{ __('Branches besides the main one') }}</label>
                        <input type="text" inputmode="numeric" dir="ltr" class="form-control @error('branches') is-invalid @enderror" id="branches" name="branches" value="{{ old('branches', 0) }}" autocomplete="off">
                        @error('branches')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                </fieldset>

                <fieldset class="site-wizard-step" data-step="2">
                <legend class="site-form-head">{{ __('The owner') }}</legend>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="owner_name">{{ __('Full name') }} <span class="req">*</span></label>
                        <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="150" required autocomplete="name">
                        @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="owner_phone">{{ __('Phone') }} <span class="req">*</span></label>
                        <input type="tel" class="form-control @error('owner_phone') is-invalid @enderror" id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" maxlength="40" required minlength="7" dir="ltr" autocomplete="tel">
                        @error('owner_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="owner_email">{{ __('Email') }} <span class="req">*</span></label>
                        <input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" maxlength="190" required dir="ltr" autocomplete="email" aria-describedby="emailHelp">
                        @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="emailHelp">{{ __('You sign in with this email and the password below.') }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password">{{ __('Password') }} <span class="req">*</span></label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password_confirmation">{{ __('Password again') }} <span class="req">*</span></label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                </fieldset>

                <fieldset class="site-wizard-step" data-step="3">
                <legend class="site-form-head">{{ __('Price and send') }}</legend>
                <div class="site-prices site-prices-compact">
                    <article class="site-price">
                        <h3>{{ __('Main branch') }}</h3>
                        <p class="site-amount"><span dir="ltr">${{ $prices['main_first'] + 0 }}</span> <small>{{ __('first year') }}</small></p>
                        <p>{{ __('then :amount a year for hosting', ['amount' => '$'.($prices['main_renewal'] + 0)]) }}</p>
                    </article>
                    <article class="site-price">
                        <h3>{{ __('Each further branch') }}</h3>
                        <p class="site-amount"><span dir="ltr">${{ $prices['branch_first'] + 0 }}</span> <small>{{ __('first year') }}</small></p>
                        <p>{{ __('then :amount a year for hosting', ['amount' => '$'.($prices['branch_renewal'] + 0)]) }}</p>
                    </article>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="message">{{ __('Anything we should know') }}</label>
                    <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" @checked(old('terms')) required>
                    <label class="form-check-label" for="terms">{{ __('I agree that the records of our patients are kept by Tabib for our clinic only, and that our clinic is responsible for what it writes in them.') }} <span class="req">*</span></label>
                    @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                </fieldset>
            </div>
            <div class="card-footer d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-soft btn-lg" data-wizard-back hidden><x-icon name="arrow-left" class="me-1" /> {{ __('Back') }}</button>
                <span class="small text-muted" data-wizard-note>{{ __('Main branch :first the first year, then :renewal a year.', ['first' => '$'.($prices['main_first'] + 0), 'renewal' => '$'.($prices['main_renewal'] + 0)]) }}</span>
                <button type="button" class="btn btn-gradient btn-lg" data-wizard-next hidden>{{ __('Next') }} <x-icon name="arrow-right" class="ms-1" /></button>
                <button type="submit" class="btn btn-gradient btn-lg" data-wizard-send>{{ __('Send the request') }}</button>
            </div>
        </form>
    </div>
</section>
{{-- the address: no spaces or capital letters as he types, taken from the name while he has not written one, and checked at once --}}
<script>
(function () {
    var slug = document.getElementById('slug'), name = document.getElementById('name'), state = document.getElementById('slugState');
    if (!slug || !state) return;
    var own = slug.value !== '', timer = null, last = 0, domain = @json('.'.config('tenancy.base_domain'));
    var clean = function (v) { return v.toLowerCase().replace(/[^a-z0-9-]+/g, '').replace(/^[^a-z]+/, '').slice(0, 40); };
    var show = function (kind, text) {
        state.hidden = !text; state.className = 'site-slug-state' + (kind ? ' is-' + kind : ''); state.textContent = text || '';
        slug.classList.toggle('is-valid', kind === 'ok'); slug.classList.toggle('is-invalid', kind === 'bad');
        var old = document.querySelector('[data-slug-error]'); if (old) old.remove();
    };
    var check = function () {
        var v = slug.value, n = ++last;
        clearTimeout(timer);
        if (v === '') { show('', ''); return; }
        if (!/^[a-z][a-z0-9-]{2,39}$/.test(v)) { show('bad', slug.getAttribute('data-short')); return; }
        show('', v + domain + ' …');
        timer = setTimeout(function () {
            fetch(slug.getAttribute('data-check') + '?address=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (n !== last || !d) { if (n === last) show('', ''); return; }
                    show(d.free ? 'ok' : 'bad', d.free ? slug.getAttribute('data-free') + ' ' + v + domain : slug.getAttribute('data-taken'));
                }).catch(function () { if (n === last) show('', ''); });
        }, 350);
    };
    slug.addEventListener('input', function () {
        var v = clean(slug.value);
        if (v !== slug.value) slug.value = v;
        own = v !== '';
        check();
    });
    if (name) name.addEventListener('input', function () { if (!own) { slug.value = clean(name.value); check(); } });
    if (slug.value) check();
})();

/* the three steps: one at a time, and the boxes of a step are checked before the next opens */
(function () {
    var form = document.querySelector('[data-wizard]'); if (!form) return;
    var steps = [].slice.call(form.querySelectorAll('[data-step]')), labels = [].slice.call(document.querySelectorAll('[data-step-label]'));
    var back = form.querySelector('[data-wizard-back]'), next = form.querySelector('[data-wizard-next]'), send = form.querySelector('[data-wizard-send]'), note = form.querySelector('[data-wizard-note]');
    // after a refused send, open the first step with a mistake
    var bad = form.querySelector('.is-invalid'), at = 0;
    if (bad) { steps.forEach(function (s, i) { if (s.contains(bad) && at === 0) at = i; }); }
    var show = function (n) {
        at = n;
        steps.forEach(function (s, i) { s.hidden = i !== n; });
        labels.forEach(function (l, i) { l.classList.toggle('is-current', i === n); l.classList.toggle('is-done', i < n); });
        back.hidden = n === 0; next.hidden = n === steps.length - 1; send.hidden = n !== steps.length - 1; note.hidden = n !== 0;
        var first = steps[n].querySelector('input:not([type=hidden]),select,textarea'); if (first && n > 0) first.focus();
    };
    var valid = function (n) {
        var boxes = [].slice.call(steps[n].querySelectorAll('input,select,textarea'));
        for (var i = 0; i < boxes.length; i++) { if (!boxes[i].checkValidity()) { boxes[i].reportValidity(); return false; } }
        var p = form.querySelector('#password'), c = form.querySelector('#password_confirmation');
        if (n === 1 && p.value !== c.value) { c.setCustomValidity(@json(__('The two passwords are not the same.'))); c.reportValidity(); c.setCustomValidity(''); return false; }
        return true;
    };
    next.addEventListener('click', function () { if (valid(at)) show(at + 1); });
    back.addEventListener('click', function () { show(at - 1); });
    form.addEventListener('submit', function (e) { for (var i = 0; i < steps.length; i++) { if (!valid(i)) { e.preventDefault(); show(i); valid(i); return; } } });
    show(at);
})();
</script>
@endsection
