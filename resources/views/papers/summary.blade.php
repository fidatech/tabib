{{-- The summary of a patient on paper (A4): to give him, or to send with him. "Save as PDF" in the print window makes the file. --}}
@php
    $title = __('Summary of :name', ['name' => $patient->name]);
    $size = 'A4';
    $back = route('patients.show', ['id' => $patient->id]);
    $lists = [
        'Problems' => $problems, 'Medicines taken now' => $medications, 'Operations' => $surgeries, 'Family history' => $family, 'Vaccines' => $immunizations,
    ];
@endphp
@extends('papers.layout')

@section('paper')
    <h2>{{ __('Patient summary') }}</h2>
    <div class="who">
        <span><b>{{ __('Patient') }}:</b> {{ $patient->name }}{{ $patient->father_name ? ' ('.$patient->father_name.')' : '' }}</span>
        <span><b>{{ __('Number') }}:</b> <span class="n">{{ $patient->number }}</span></span>
        <span><b>{{ __('Age') }}:</b> {{ $patient->ageText() }}</span>
        @if ($patient->blood_group)<span><b>{{ __('Blood group') }}:</b> {{ $patient->blood_group }}</span>@endif
        @if ($patient->phone)<span><b>{{ __('Phone') }}:</b> <span class="n">{{ $patient->phone }}</span></span>@endif
        <span><b>{{ __('Printed') }}:</b> {{ fmt_date(today_iso()) }}</span>
    </div>

    @if ($allergies->isNotEmpty())
        <div class="allergy">{{ __('Allergies') }}: {{ $allergies->map->summary()->implode(' · ') }}</div>
    @elseif ($patient->knowsNoAllergies())
        <p class="small">{{ __('Allergies') }}: {{ __('None known') }}</p>
    @else
        <p class="small">{{ __('Allergies') }}: {{ __('Not asked yet') }}</p>
    @endif

    @foreach ($lists as $label => $rows)
        @if ($rows->isNotEmpty())
            <h3>{{ __($label) }}</h3>
            <ul style="margin:2px 0">@foreach ($rows as $r)<li>{{ $r->summary() }}</li>@endforeach</ul>
        @endif
    @endforeach

    @if ($vitals->isNotEmpty())
        <h3>{{ __('Vital signs') }}</h3>
        <table>
            <tbody>@foreach ($vitals as $v)<tr><td class="n">{{ fmt_date($v->taken_at) }}</td><td>{{ $v->summary() }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    @if ($visits->isNotEmpty())
        <h3>{{ __('Visits') }}</h3>
        <table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Doctor') }}</th><th>{{ __('Complaint') }}</th><th>{{ __('Diagnosis') }}</th></tr></thead>
            <tbody>
                @foreach ($visits as $e)
                    <tr><td class="n">{{ fmt_date($e->started_at) }}</td><td>{{ $e->doctor?->name() }}</td><td>{{ \Illuminate\Support\Str::limit((string) $e->complaint, 80) }}</td>
                        <td>{{ $e->diagnoses->map->label()->implode('; ') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($orders->isNotEmpty())
        <h3>{{ __('Results') }}</h3>
        <table>
            <tbody>@foreach ($orders as $o)<tr><td class="n">{{ fmt_date($o->resulted_at) }}</td><td dir="ltr">{{ $o->summary() }}</td><td style="white-space:pre-line">{{ $o->result ?: __('(file in the chart)') }}</td></tr>@endforeach</tbody>
        </table>
    @endif
@endsection
