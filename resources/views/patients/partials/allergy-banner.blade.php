{{-- The allergies on top of every page of the patient, in red, so nobody misses them. $allergies: the active ones. --}}
@if ($allergies->isNotEmpty())
    <div class="alert alert-danger d-flex gap-2 align-items-start patient-allergy" role="alert">
        <x-icon name="triangle-exclamation" class="mt-1" />
        <div><strong>{{ __('Allergies') }}:</strong>
            @foreach ($allergies as $al)
                <span class="me-2">{{ $al->substance }}@if ($al->reaction) <span class="small">({{ $al->reaction }})</span>@endif @if ($al->severity === 'severe')<span class="badge bg-danger">{{ __('Severe') }}</span>@endif</span>@unless ($loop->last)·@endunless
            @endforeach
        </div>
    </div>
@elseif ($patient->knowsNoAllergies())
    <div class="alert alert-success py-2 small" role="note"><x-icon name="circle-check" class="me-1" /> {{ __('No known allergies') }}</div>
@else
    <div class="alert alert-warning py-2 small" role="note"><x-icon name="circle-info" class="me-1" /> {{ __('Allergies not asked yet.') }}
        @can('clinical.manage')<a href="{{ route('patients.show', ['id' => $patient->id, 'tab' => 'history']) }}#h-allergies">{{ __('Write them') }}</a>@endcan</div>
@endif
