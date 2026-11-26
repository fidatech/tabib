@extends('layouts.app', ['title' => __('Doctor commission')])

@section('content')
<x-page-header :title="__('Doctor commission')" icon="hand-holding-dollar" :subtitle="$branch->name" :crumbs="[[__('Expenses'), route('expenses.index', ['month' => $month])], [__('Doctor commission')]]" />

@include('expenses._month', ['route' => 'commission.index'])

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ __('Doctor') }}</th><th>{{ __('Share') }}</th><th class="text-end">{{ __('Money taken') }}</th><th class="text-end">{{ __('Patients seen') }}</th>
                <th class="text-end">{{ __('Due') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Left to pay') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
            <tbody>
                @forelse ($statements as $s)
                    <tr>
                        <td>{{ $s['doctor']->name() }}</td>
                        <td class="small">{{ \App\Services\Commission::describe($s['doctor']) }}</td>
                        <td class="text-end" dir="ltr">{{ money($s['collected']) }}</td>
                        <td class="text-end">{{ $s['visits'] }}</td>
                        <td class="text-end fw-semibold" dir="ltr">{{ money($s['due']) }}</td>
                        <td class="text-end" dir="ltr">{{ money($s['paid']) }}</td>
                        <td class="text-end {{ \App\Support\Num::cmp($s['left'], '0') > 0 ? 'text-danger fw-semibold' : '' }}" dir="ltr">{{ money($s['left']) }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('commission.statement', ['doctor' => $s['doctor']->id, 'month' => $month]) }}">{{ __('Statement') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">{{ __('No doctor.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">{{ __('The share of a doctor is written on his page (Doctors). A percent counts the money taken in the month for his invoices, less what was given back; an amount counts the appointments done.') }}</p>
@endsection
