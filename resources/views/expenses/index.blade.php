@extends('layouts.app', ['title' => __('Expenses')])

@php $cur = setting('currency'); $C = \App\Models\Expense::CATEGORIES; @endphp

@section('content')
<x-page-header :title="__('Expenses')" icon="receipt" :subtitle="$branch->name">
    <a class="btn btn-outline-soft" href="{{ route('commission.index', ['month' => $month]) }}"><x-icon name="hand-holding-dollar" class="me-1" /> {{ __('Doctor commission') }}</a>
</x-page-header>

@include('expenses._month', ['route' => 'expenses.index', 'params' => array_filter(['category' => $category])])

<div class="row g-3">
    <div class="col-12 col-xl-8">
        @if ($canManage)
            <form method="POST" action="{{ route('expenses.store') }}" class="card mb-3">@csrf
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Add an expense') }}</h2></div>
                <div class="card-body row g-2">
                    <div class="col-6 col-md-3"><label class="form-label small" for="ex-date">{{ __('Date') }}</label><x-date name="spent_on" :value="old('spent_on', today_iso())" id="ex-date" :required="true" /></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="ex-cat">{{ __('Kind') }}</label>
                        <select class="form-select" id="ex-cat" name="category">@foreach ($C as $k => $l)@continue($k === 'commission')<option value="{{ $k }}" @selected(old('category') === $k)>{{ __($l) }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="ex-amount">{{ __('Amount') }}</label>
                        <input type="number" class="form-control" id="ex-amount" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required dir="ltr"></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="ex-method">{{ __('Paid by') }}</label>
                        <select class="form-select" id="ex-method" name="method">@foreach (\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                    <div class="col-12 col-md-5"><label class="form-label small" for="ex-to">{{ __('Paid to') }}</label><input type="text" class="form-control" id="ex-to" name="paid_to" value="{{ old('paid_to') }}" maxlength="150"></div>
                    <div class="col-12 col-md-5"><label class="form-label small" for="ex-note">{{ __('Note') }}</label><input type="text" class="form-control" id="ex-note" name="note" value="{{ old('note') }}" maxlength="255"></div>
                    <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">{{ __('Add') }}</button></div>
                </div>
            </form>
        @endif

        <div class="card">
            <div class="card-header d-flex align-items-center gap-2"><h2 class="h5 card-title mb-0 flex-grow-1">{{ __('Expenses of the month') }}</h2>
                <form method="GET"><input type="hidden" name="month" value="{{ $month }}"><label class="visually-hidden" for="ex-filter">{{ __('Kind') }}</label>
                    <select class="form-select form-select-sm" id="ex-filter" name="category" onchange="this.form.submit()"><option value="">{{ __('All kinds') }}</option>
                        @foreach ($C as $k => $l)<option value="{{ $k }}" @selected($category === $k)>{{ __($l) }}</option>@endforeach</select></form></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Paid to') }}</th><th class="text-end">{{ __('Amount') }}</th>@if ($canManage)<th class="no-print"><span class="visually-hidden">{{ __('Actions') }}</span></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($rows as $e)
                            <tr class="{{ $e->voided_at ? 'text-muted text-decoration-line-through' : '' }}">
                                <td dir="ltr">{{ $e->number }}</td><td class="text-nowrap">{{ fmt_date($e->spent_on) }}</td><td>{{ $e->categoryLabel() }}</td>
                                <td>{{ $e->paid_to }}@if ($e->note)<span class="d-block small text-muted">{{ $e->note }}</span>@endif @if ($e->voided_at)<span class="d-block small">{{ __('Void') }}: {{ $e->void_reason }}</span>@endif</td>
                                <td class="text-end text-nowrap" dir="ltr">{{ money($e->amount) }}</td>
                                @if ($canManage)
                                    <td class="text-end no-print">@unless ($e->voided_at)
                                        <form method="POST" action="{{ route('expenses.void', ['id' => $e->id]) }}" class="d-flex gap-1 justify-content-end">@csrf
                                            <label class="visually-hidden" for="void-{{ $e->id }}">{{ __('Why void it') }}</label>
                                            <input type="text" class="form-control form-control-sm void-why" id="void-{{ $e->id }}" name="reason" required maxlength="255" placeholder="{{ __('Why void it') }}">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="{{ __('Void') }}" title="{{ __('Void') }}"><x-icon name="ban" /></button></form>
                                    @endunless</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">{{ __('No expense in this month.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('By kind') }}</h2></div>
            <table class="table mb-0">
                <tbody>
                    @forelse ($byCategory as $k => $amount)
                        <tr><td>{{ __($C[$k] ?? $k) }}</td><td class="text-end" dir="ltr">{{ money($amount) }}</td></tr>
                    @empty
                        <tr><td class="text-muted">—</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end" dir="ltr">{{ money($total, $cur) }}</td></tr></tfoot>
            </table>
        </div>
        <p class="small text-muted mt-2"><a href="{{ route('reports.show', ['report' => 'profit']) }}">{{ __('See the profit of the clinic') }}</a></p>
    </div>
</div>
@endsection
