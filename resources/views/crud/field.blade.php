@php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $stored = ! empty($field['meta']) ? ($record?->meta[$name] ?? null) : ($record?->{$name} ?? null);
    if ($type === 'password' || ! empty($field['virtual'])) {
        $stored = null;
    }
    $fallback = array_key_exists('value', $field) ? $field['value'] : ($record ? null : ($field['default'] ?? null));
    $value = old($name, $stored ?? $fallback);
    $required = in_array('required', $field['rules'] ?? [], true);
    $readonly = ! empty($field['readonly']);
    // in a pop-up: three boxes in a row, and the explanation behind a "?" beside the label
    $compact = $compact ?? false;
    $tip = $compact && ! empty($field['help']) ? __($field['help']) : null;
@endphp
@if ($type === 'heading')
    <div class="col-12 form-legend-col"><div class="form-legend">@if (! empty($field['icon']))<span class="icon-soft primary"><x-icon :name="$field['icon']" /></span>@endif<div><h2>{{ __($field['label']) }}@if ($tip) <x-help :text="$tip" />@endif</h2>@if (! $compact && ! empty($field['help']))<p>{{ __($field['help']) }}</p>@endif</div></div></div>
    @php
        return;
    @endphp
@endif
@if ($type === 'people')
    {{-- rows of people (the representatives of a company): one row to start, more with the button --}}
    @php
        $peopleRows = array_values(array_filter((array) old($name, []), 'is_array')) ?: [[]];
        $peopleBoxes = ['name' => 'Name', 'father_name' => "Father's name", 'phone' => 'Phone', 'id_number' => 'Identity card number'];
    @endphp
    <div class="col-12" data-repeat data-repeat-next="{{ count($peopleRows) }}">
        <div data-repeat-rows>
            @foreach ($peopleRows as $i => $row)
                <div class="people-row" data-repeat-row>
                    @foreach ($peopleBoxes as $key => $word)
                        <input type="{{ $key === 'phone' ? 'tel' : 'text' }}" class="form-control @error($name.'.'.$i.'.'.$key) is-invalid @enderror" name="{{ $name }}[{{ $i }}][{{ $key }}]" value="{{ $row[$key] ?? '' }}" maxlength="150" placeholder="{{ __($word) }}" aria-label="{{ __($word) }}" @if (in_array($key, ['phone', 'id_number'], true)) dir="ltr" @endif>
                    @endforeach
                    <button type="button" class="btn btn-light people-remove" data-repeat-remove title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button>
                </div>
            @endforeach
        </div>
        <template data-repeat-template>
            <div class="people-row" data-repeat-row>
                @foreach ($peopleBoxes as $key => $word)
                    <input type="{{ $key === 'phone' ? 'tel' : 'text' }}" class="form-control" name="{{ $name }}[__i__][{{ $key }}]" maxlength="150" placeholder="{{ __($word) }}" aria-label="{{ __($word) }}" @if (in_array($key, ['phone', 'id_number'], true)) dir="ltr" @endif>
                @endforeach
                <button type="button" class="btn btn-light people-remove" data-repeat-remove title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button>
            </div>
        </template>
        <button type="button" class="btn btn-sm btn-outline-soft mt-2" data-repeat-add><x-icon name="plus" class="me-1" /> {{ __('Add a representative') }}</button>
    </div>
    @php
        return;
    @endphp
@endif
@if (! empty($field['hidden']))
    @php
        return;
    @endphp
@endif
<div class="{{ $compact && ($field['col'] ?? 6) < 12 ? 'col-12 col-sm-6 col-lg-4' : 'col-12 col-md-'.($field['col'] ?? 6) }}">
    @if ($type === 'currencies')
        <label class="form-label">{{ __($field['label']) }} <span class="text-danger">*</span></label>
        <div class="row g-2">
            @foreach ($field['options'] as $currencyId => $code)
                @php
                    $ticked = old('currencies') !== null ? ! empty(old("currencies.$currencyId.on")) : ($currencyId === ($field['default'] ?? null));
                @endphp
                <div class="col-12 {{ empty($field['sides']) ? 'col-sm-4 col-lg-3' : 'col-xl-6' }}">
                    <div class="input-group">
                        <label class="input-group-text gap-2 mb-0" for="f-cur-{{ $currencyId }}">
                            <input class="form-check-input mt-0" type="checkbox" id="f-cur-{{ $currencyId }}" name="currencies[{{ $currencyId }}][on]" value="1" @checked($ticked)>
                            <strong>{{ $code }}</strong>
                        </label>
                        @if (! empty($field['sides']))
                            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="currencies[{{ $currencyId }}][opening]" value="{{ old("currencies.$currencyId.opening") }}" placeholder="{{ __('Opening balance') }}" aria-label="{{ __('Opening balance') }} {{ $code }}">
                            <select class="form-select erp-side" name="currencies[{{ $currencyId }}][side]" aria-label="{{ __('Opening balance is') }} {{ $code }}">
                                @foreach ($field['sides'] as $side => $sideLabel)
                                    <option value="{{ $side }}" @selected(old("currencies.$currencyId.side", $field['side'] ?? 'debit') === $side)>{{ $sideLabel }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @error('currencies')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (! empty($field['help']))
            <div class="form-text">{{ __($field['help']) }}</div>
        @endif
    @elseif ($type === 'checkbox')
        <div class="form-check form-switch mt-md-4 pt-md-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="f-{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $stored ?? ($field['default'] ?? false)))>
            <label class="form-check-label" for="f-{{ $name }}">{{ __($field['label']) }}@if ($tip) <x-help :text="$tip" />@endif</label>
            @if (! $compact && ! empty($field['help']))
                <div class="form-text">{{ __($field['help']) }}</div>
            @endif
        </div>
    @else
        <label class="form-label" for="f-{{ $name }}">{{ __($field['label']) }}@if ($required) <span class="req" title="{{ __('Required') }}">*</span>@elseif (! empty($field['need_from'])) <span class="req is-later" title="{{ __('Needed for a transaction of :amount AFN or more', ['amount' => number_format($field['need_from'])]) }}">*</span>@endif @if ($tip)<x-help :text="$tip" />@endif</label>
        @if ($type === 'select')
            <select class="form-select @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" @disabled($readonly) @required($required) @if (! empty($field['search'])) data-search @endif @foreach ($field['attrs'] ?? [] as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach @if (! empty($field['quick'])) {{ quick_add($field['quick'][0], __($field['quick'][1]), $field['quick'][2], $field['quick'][3] ?? []) }} @endif>
                @unless ($required && $value !== null && $value !== '')
                    <option value="">{{ __('Choose…') }}</option>
                @endunless
                @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" rows="2">{{ $value }}</textarea>
        @elseif ($type === 'date')
            <x-date :name="$name" :value="$value" :required="$required" id="f-{{ $name }}" class="{{ $errors->has($name) ? 'is-invalid' : '' }}" />
        @elseif ($type === 'decimal')
            <input type="text" inputmode="decimal" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off" dir="ltr" @readonly($readonly) @required($required)>
        @elseif ($type === 'number')
            <input type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $value }}" @readonly($readonly) @required($required)>
        @else
            <input type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $value }}" @if ($type === 'password') autocomplete="new-password" @endif @readonly($readonly) @required($required && $type !== 'password')>
        @endif
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (! $compact && ! empty($field['help']))
            <div class="form-text">{{ __($field['help']) }}</div>
        @endif
    @endif
</div>
