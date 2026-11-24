{{-- How a medicine is taken: the boxes for adding one and for changing one. $i: the item or null; $id: a prefix for the ids. --}}
<div class="col-6 col-md-2"><label class="form-label small" for="{{ $id }}-amount">{{ __('Dose') }}</label>
    <input type="number" step="any" min="0" class="form-control" id="{{ $id }}-amount" name="dose_amount" value="{{ $i?->dose_amount }}" dir="ltr"></div>
<div class="col-6 col-md-2"><label class="form-label small" for="{{ $id }}-unit">{{ __('Unit') }}</label>
    <select class="form-select" id="{{ $id }}-unit" name="dose_unit"><option value=""></option>@foreach ($units as $k => $v)<option value="{{ $k }}" @selected($i?->dose_unit === $k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-6 col-md-3"><label class="form-label small" for="{{ $id }}-freq">{{ __('How often') }}</label>
    <select class="form-select" id="{{ $id }}-freq" name="frequency"><option value=""></option>@foreach ($freqs as $k => $v)<option value="{{ $k }}" @selected($i?->frequency === $k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-6 col-md-3"><label class="form-label small" for="{{ $id }}-timing">{{ __('When') }}</label>
    <select class="form-select" id="{{ $id }}-timing" name="timing"><option value=""></option>@foreach ($timings as $k => $v)<option value="{{ $k }}" @selected($i?->timing === $k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-6 col-md-2"><label class="form-label small" for="{{ $id }}-days">{{ __('Days') }}</label>
    <input type="number" min="1" max="365" class="form-control" id="{{ $id }}-days" name="days" value="{{ $i?->days }}" dir="ltr"></div>
<div class="col-6 col-md-4"><label class="form-label small" for="{{ $id }}-qty">{{ __('Quantity to give') }}</label>
    <input type="text" maxlength="40" class="form-control" id="{{ $id }}-qty" name="quantity" value="{{ $i?->quantity }}" placeholder="{{ __('Worked out when empty') }}"></div>
<div class="col-12 col-md-8"><label class="form-label small" for="{{ $id }}-ins">{{ __('Other instructions') }}</label>
    <input type="text" maxlength="255" class="form-control" id="{{ $id }}-ins" name="instructions" value="{{ $i?->instructions }}"></div>
