{{-- The boxes of a set of vital signs. $encounterId: the visit they are taken in, or null. --}}
<form method="POST" action="{{ route('history.vitals', ['patient' => $patient->id]) }}" class="row g-2 align-items-end">
    @csrf
    @if ($encounterId)<input type="hidden" name="encounter_id" value="{{ $encounterId }}">@endif
    @foreach (\App\Models\Vital::MEASURES as $name => [$label, $unit, $low, $high])
        <div class="col-6 col-md-4 col-xl">
            <label class="form-label small text-nowrap" for="v-{{ $name }}">{{ __($label) }} <span class="text-muted">{{ $unit }}</span></label>
            <input type="text" inputmode="decimal" dir="ltr" class="form-control form-control-sm @error($name) is-invalid @enderror" id="v-{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" autocomplete="off">
            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endforeach
    <div class="col-12 col-xl-auto"><button type="submit" class="btn btn-sm btn-primary w-100"><x-icon name="heart-pulse" class="me-1" /> {{ __('Save') }}</button></div>
</form>
