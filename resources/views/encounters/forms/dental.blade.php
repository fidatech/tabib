{{-- The tooth chart (FDI, permanent teeth). $edit: boxes to choose; otherwise what was found. --}}
@php
    $F = \App\Support\Forms::class;
    $teeth = (array) ($encounter->extra['teeth'] ?? []);
    $old = (array) old('extra.teeth', $teeth);
@endphp
<div class="tooth-chart" dir="ltr">
    @foreach ($F::TEETH as $row => $jaw)
        <div class="tooth-row {{ $row === 0 ? 'upper' : 'lower' }}" role="group" aria-label="{{ $row === 0 ? __('Upper jaw') : __('Lower jaw') }}">
            @foreach ($jaw as $t)
                @php $state = $edit ? ($old[$t] ?? '') : ($teeth[$t] ?? ''); @endphp
                <div class="tooth {{ $state ? 'tooth-'.$state : '' }} {{ $loop->index === 7 ? 'mid' : '' }}">
                    <span class="tooth-no">{{ $t }}</span>
                    @if ($edit)
                        <label class="visually-hidden" for="tooth-{{ $t }}">{{ __('Tooth :n', ['n' => $t]) }}</label>
                        <select id="tooth-{{ $t }}" name="extra[teeth][{{ $t }}]" class="tooth-pick" data-tooth>
                            <option value=""></option>
                            @foreach ($F::TOOTH_STATES as $k => [$label, $mark])<option value="{{ $k }}" @selected($state === $k) title="{{ __($label) }}">{{ $mark }}</option>@endforeach
                        </select>
                    @else
                        <span class="tooth-mark" title="{{ $state ? __($F::TOOTH_STATES[$state][0]) : '' }}">{{ $state ? $F::TOOTH_STATES[$state][1] : '' }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
<p class="small text-muted mb-2">@foreach ($F::TOOTH_STATES as $k => [$label, $mark])<span class="me-2 text-nowrap"><b>{{ $mark }}</b> {{ __($label) }}</span>@endforeach</p>
@if ($edit)
    <label class="form-label small" for="teeth-note">{{ __('Notes on the teeth') }}</label>
    <textarea class="form-control mb-3" id="teeth-note" name="extra[teeth_note]" rows="2" maxlength="1000">{{ old('extra.teeth_note', $encounter->extra['teeth_note'] ?? '') }}</textarea>
@elseif (! empty($encounter->extra['teeth_note']))
    <p style="white-space:pre-line">{{ $encounter->extra['teeth_note'] }}</p>
@endif
