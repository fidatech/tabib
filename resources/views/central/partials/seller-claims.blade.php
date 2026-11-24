{{-- Money resellers reported and that waits: confirm it (it becomes a payment) or reject it with a reason. --}}
@if ($claims->isNotEmpty())
    <div class="card mb-3 border-warning">
        <div class="card-header"><h2 class="h5 card-title mb-0"><x-icon name="hourglass-half" class="me-1 text-warning" /> {{ __('Payments reported by resellers') }} <span class="badge bg-warning-subtle text-warning">{{ $claims->count() }}</span></h2></div>
        <div class="card-body">
            <table class="table table-hover w-100 align-middle mb-0">
                <thead><tr><th>{{ __('Date') }}</th>@if ($withSeller ?? true)<th>{{ __('Reseller') }}</th>@endif<th>{{ __('Clinic') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Paid by') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($claims as $claim)
                        <tr>
                            <td dir="ltr" class="text-nowrap">{{ $claim->paid_on->format('Y-m-d') }}</td>
                            @if ($withSeller ?? true)<td><a href="{{ route('admin.sellers.show', ['id' => $claim->seller_id]) }}">{{ $claim->seller->name }}</a></td>@endif
                            <td>{{ $claim->tenant?->name }}@if ($claim->note)<div class="small text-muted">{{ $claim->note }}</div>@endif</td>
                            <td>{{ $claim->invoice?->number ?? __('On account') }}</td>
                            <td>{{ __(\App\Models\Central\Payment::METHODS[$claim->method] ?? $claim->method) }}@if ($claim->reference)<div class="small text-muted" dir="ltr">{{ $claim->reference }}</div>@endif</td>
                            <td class="text-end fw-semibold" dir="ltr">${{ money($claim->amount) }}</td>
                            <td class="text-end no-print">
                                @if (auth('admin')->user()->allows('billing.manage'))
                                    <div class="act-group">
                                        <form method="POST" action="{{ route('admin.sellers.confirm', ['claim' => $claim->id]) }}" data-confirm="{{ __('Confirm this payment of :amount?', ['amount' => '$'.money($claim->amount)]) }}" data-confirm-text="{{ __('It becomes a payment of the clinic, taken by the reseller, with his commission.') }}" data-confirm-ok="{{ __('Confirm') }}" data-confirm-style="primary">@csrf<button class="btn btn-sm btn-success" type="submit"><x-icon name="check" class="me-1" /> {{ __('Confirm') }}</button></form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#refuse{{ $claim->id }}">{{ __('Reject') }}</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @foreach ($claims as $claim)
        <div class="modal fade" id="refuse{{ $claim->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.sellers.refuse', ['claim' => $claim->id]) }}">@csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('Reject') }} · <span dir="ltr">${{ money($claim->amount) }}</span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body"><label class="form-label" for="rr{{ $claim->id }}">{{ __('Reason') }} <span class="text-danger">*</span></label><textarea class="form-control" id="rr{{ $claim->id }}" name="reason" rows="3" required maxlength="500"></textarea><div class="form-text">{{ __('The reseller reads this in his panel.') }}</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Reject') }}</button></div>
        </form></div></div>
    @endforeach
@endif
