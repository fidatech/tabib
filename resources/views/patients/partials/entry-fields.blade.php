{{--
    The boxes of one section of the history (App\Support\History), new or for an entry.
    $def: the section; $entry: the entry or null; $prefix: unique for the ids on the page.
--}}
@foreach ($def['fields'] as $name => $f)
    @php
        [$label, $type, $rules] = $f;
        $options = $f[3] ?? null;
        $hint = $f[4] ?? null;
        $value = $entry?->{$name};
        $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        $id = $prefix.'-'.$name;
        $required = in_array('required', $rules, true);
    @endphp
    @if ($type === 'hidden')
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-icd10-code>
        @continue
    @endif
    <div class="{{ in_array($type, ['icd10', 'textarea'], true) || $name === 'note' ? 'col-12' : 'col-12 col-md-6' }}">
        <label class="form-label small" for="{{ $id }}">{{ __($label) }} @if ($required)<span class="text-danger">*</span>@endif</label>
        @if ($type === 'select')
            <select class="form-select form-select-sm" id="{{ $id }}" name="{{ $name }}" @required($required) data-plain>
                @unless ($required)<option value="">—</option>@endunless
                @foreach ($options as $key => $text)
                    <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ __($text) }}</option>
                @endforeach
            </select>
        @elseif ($type === 'date')
            <x-date :name="$name" :value="$value" id="{{ $id }}" class="form-control-sm" />
        @elseif ($type === 'icd10')
            <div class="icd10-pick" data-icd10 data-url="{{ route('icd10.search') }}">
                <input type="text" class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="255" autocomplete="off" @required($required)
                    placeholder="{{ __('Type a diagnosis or an ICD-10 code') }}" data-icd10-text>
                <div class="list-group icd10-results" data-icd10-results hidden></div>
            </div>
        @elseif ($type === 'textarea')
            <textarea class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}" rows="2" @required($required)>{{ $value }}</textarea>
        @else
            <input type="text" class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @required($required) @if ($hint) placeholder="{{ __($hint) }}" @endif>
        @endif
    </div>
@endforeach
