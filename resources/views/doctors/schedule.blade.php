@extends('layouts.app', ['title' => __('Hours and days off').' · '.$doctor->name()])

@php $W = \App\Services\Availability::WEEKDAYS; @endphp

@section('content')
<x-page-header :title="__('Hours and days off').' · '.$doctor->name()" icon="business-time" :crumbs="[[__('Doctors'), route('doctors.index')], [$doctor->name()]]" />

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <form method="POST" action="{{ route('doctors.schedule.hours', ['id' => $doctor->id]) }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Working hours') }}</h2></div>
            <fieldset class="card-body" @disabled(! $canManage)>
                <p class="small text-muted">{{ __('A day left empty is a day the doctor does not work. When no day has hours, he works the hours of the clinic (:from–:to), every day.', ['from' => setting('opening_time', '08:00'), 'to' => setting('closing_time', '17:00')]) }}</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ __('Day') }}</th><th colspan="2">{{ __('First period') }}</th><th colspan="2">{{ __('Second period') }}</th></tr></thead>
                        <tbody>
                            @foreach ($W as $d => $name)
                                @php $list = ($hours[$d] ?? collect())->values(); @endphp
                                <tr>
                                    <th class="fw-semibold">{{ __($name) }}</th>
                                    @foreach ([1, 2] as $p)
                                        @php $h = $list[$p - 1] ?? null; @endphp
                                        <td><label class="visually-hidden" for="h-{{ $d }}-{{ $p }}-f">{{ __($name) }} {{ __('from') }}</label>
                                            <input type="time" class="form-control form-control-sm" id="h-{{ $d }}-{{ $p }}-f" name="days[{{ $d }}][{{ $p }}][from]" value="{{ old("days.$d.$p.from", $h?->starts) }}" dir="ltr"></td>
                                        <td><label class="visually-hidden" for="h-{{ $d }}-{{ $p }}-t">{{ __($name) }} {{ __('to') }}</label>
                                            <input type="time" class="form-control form-control-sm" id="h-{{ $d }}-{{ $p }}-t" name="days[{{ $d }}][{{ $p }}][to]" value="{{ old("days.$d.$p.to", $h?->ends) }}" dir="ltr"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canManage)<div class="text-end mt-3"><button class="btn btn-primary" type="submit">{{ __('Save the hours') }}</button></div>@endif
            </fieldset>
        </form>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Days off') }}</h2></div>
            @if ($canManage)
                <form method="POST" action="{{ route('doctors.leaves.store', ['id' => $doctor->id]) }}" class="card-body row g-2 border-bottom">@csrf
                    <div class="col-6"><label class="form-label small" for="l-from">{{ __('From') }}</label><x-date name="starts_on" :value="old('starts_on')" id="l-from" :required="true" /></div>
                    <div class="col-6"><label class="form-label small" for="l-to">{{ __('To') }}</label><x-date name="ends_on" :value="old('ends_on')" id="l-to" :required="true" /></div>
                    <div class="col-12"><label class="form-label small" for="l-reason">{{ __('Why') }}</label><input type="text" class="form-control" id="l-reason" name="reason" maxlength="150" placeholder="{{ __('Leave, training, travel ...') }}"></div>
                    <div class="col-12 text-end"><button class="btn btn-outline-primary" type="submit"><x-icon name="plane-departure" class="me-1" /> {{ __('Add the days off') }}</button></div>
                </form>
            @endif
            <div class="list-group list-group-flush">
                @forelse ($leaves as $l)
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="flex-grow-1"><b>{{ fmt_date($l->starts_on) }}</b> – <b>{{ fmt_date($l->ends_on) }}</b> <span class="small text-muted">{{ $l->reason }}</span></span>
                        @if ($canManage)
                            <form method="POST" action="{{ route('doctors.leaves.destroy', ['id' => $doctor->id, 'leave' => $l->id]) }}" data-confirm="{{ __('Remove these days off?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-icon name="trash" /></button></form>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-muted">{{ __('No days off.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
