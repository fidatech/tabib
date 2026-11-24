{{-- What a pharmacist sees after scanning the QR code: whether the prescription is real and still good, and what it holds. No sign-in; only the initials of the patient. --}}
@php $company = setting('company_name') ?: config('app.name'); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-primary="tabib" data-icons="{{ asset_v('icons.svg') }}">
<head>
    @include('partials.head', ['title' => __('Check a prescription')])
</head>
<body class="auth-body">
<main class="container py-4 rx-verify">
    <h1 class="h4 mb-3"><x-icon name="prescription-bottle-medical" class="me-1" /> {{ $company }}</h1>
    @if (! $genuine)
        <div class="alert alert-danger"><x-icon name="triangle-exclamation" class="me-1" /> <strong>{{ __('Do not dispense.') }}</strong> {{ __('This prescription was changed after it was signed.') }}</div>
    @elseif ($rx->status === 'cancelled')
        <div class="alert alert-danger"><x-icon name="ban" class="me-1" /> <strong>{{ __('Cancelled. Do not dispense.') }}</strong> {{ __('Cancelled on :date.', ['date' => fmt_date($rx->cancelled_at)]) }}</div>
    @else
        <div class="alert alert-success"><x-icon name="circle-check" class="me-1" /> <strong>{{ __('This prescription is genuine.') }}</strong> {{ __('Signed by :name on :date.', ['name' => $rx->doctor?->name(), 'date' => fmt_date($rx->signed_at)]) }}</div>
    @endif
    <div class="card">
        <div class="card-body">
            <p class="mb-2"><b>{{ __('Number') }}:</b> <span dir="ltr">{{ $rx->number }}</span> · <b>{{ __('Patient') }}:</b> {{ $initials }}
                @if ($rx->doctor?->licence_no) · <b>{{ __('Licence') }}:</b> <span dir="ltr">{{ $rx->doctor->licence_no }}</span>@endif</p>
            <ol class="mb-0">
                @foreach ($rx->items as $item)
                    <li><b dir="ltr">{{ trim($item->name.' '.$item->strength.' '.$item->form) }}</b> — {{ $item->directions() }} @if ($item->quantity)({{ $item->quantity }})@endif</li>
                @endforeach
            </ol>
        </div>
    </div>
</main>
</body>
</html>
