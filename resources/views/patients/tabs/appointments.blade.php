{{-- Who the patient is and his appointments: what the front desk sees. --}}
<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('About the patient') }}</h2></div>
            <div class="card-body">
                <dl class="patient-facts">
                    @if ($patient->date_of_birth && ! $patient->dob_estimated)<div><dt><x-icon name="calendar-day" /> {{ __('Date of birth') }}</dt><dd>{{ fmt_date($patient->date_of_birth) }}</dd></div>@endif
                    @if ($patient->phone2)<div><dt><x-icon name="phone" /> {{ __('Second phone') }}</dt><dd dir="ltr">{{ $patient->phone2 }}</dd></div>@endif
                    @if ($patient->tazkira)<div><dt><x-icon name="id-card" /> {{ __('Tazkira number') }}</dt><dd dir="ltr">{{ $patient->tazkira }}</dd></div>@endif
                    @if ($patient->address || $patient->province)<div><dt><x-icon name="location-dot" /> {{ __('Address') }}</dt><dd>{{ implode(', ', array_filter([$patient->address, $patient->province])) }}</dd></div>@endif
                    @if ($patient->emergency_name || $patient->emergency_phone)<div><dt><x-icon name="user-shield" /> {{ __('Emergency contact') }}</dt><dd>{{ $patient->emergency_name }} <span dir="ltr">{{ $patient->emergency_phone }}</span></dd></div>@endif
                    <div><dt><x-icon name="user-plus" /> {{ __('Registered') }}</dt><dd>{{ fmt_date($patient->created_at) }}</dd></div>
                </dl>
                @if ($patient->notes)<p class="small text-muted mb-0 mt-3" style="white-space:pre-line">{{ $patient->notes }}</p>@endif
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Coming appointments') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($upcoming as $a)
                    @include('appointments.row', ['a' => $a, 'showDate' => true])
                @empty
                    <div class="text-center text-muted small py-4">{{ __('No coming appointment.') }}</div>
                @endforelse
            </div>
        </div>
        <div class="card mb-0">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Past appointments') }}</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($past as $a)
                    @include('appointments.row', ['a' => $a, 'showDate' => true])
                @empty
                    <div class="text-center text-muted small py-4">{{ __('No visit yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
