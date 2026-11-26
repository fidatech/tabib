{{-- The month shown, with the previous and the next one. $route, $month, $params --}}
@php $m = \Illuminate\Support\Carbon::parse($month.'-01'); $params ??= []; @endphp
<form method="GET" class="d-flex gap-2 align-items-end mb-3 no-print flex-wrap">
    @foreach ($params as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <a class="btn btn-light-secondary" href="{{ route($route, $params + ['month' => $m->copy()->subMonth()->format('Y-m')]) }}" aria-label="{{ __('Previous month') }}" title="{{ __('Previous month') }}"><x-icon name="chevron-left" class="flip-rtl" /></a>
    <div><label class="form-label small mb-1" for="month-pick">{{ __('Month') }}</label>
        <input type="month" class="form-control" id="month-pick" name="month" value="{{ $month }}" onchange="this.form.submit()" dir="ltr"></div>
    <a class="btn btn-light-secondary" href="{{ route($route, $params + ['month' => $m->copy()->addMonth()->format('Y-m')]) }}" aria-label="{{ __('Next month') }}" title="{{ __('Next month') }}"><x-icon name="chevron-right" class="flip-rtl" /></a>
    <span class="small text-muted ms-1 mb-2">{{ fmt_date($m) }} – {{ fmt_date($m->copy()->endOfMonth()) }}</span>
</form>
