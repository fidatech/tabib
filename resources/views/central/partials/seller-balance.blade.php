{{-- Above zero the reseller owes us; below zero we owe him. --}}
@if (\App\Support\Num::isPositive($balance))
    <span class="fw-semibold text-danger" dir="ltr">${{ money($balance) }}</span><div class="small text-muted">{{ __('he owes us') }}</div>
@elseif (\App\Support\Num::isNegative($balance))
    <span class="fw-semibold text-warning" dir="ltr">${{ money(\App\Support\Num::abs($balance)) }}</span><div class="small text-muted">{{ __('we owe him') }}</div>
@else
    <span class="badge bg-success-subtle text-success">{{ __('Settled') }}</span>
@endif
