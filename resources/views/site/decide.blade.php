@extends('layouts.site', ['title' => __('Registration request')])

{{-- The page the Approve and Reject buttons of the email open: the request, and one button to confirm. --}}
@section('content')
<section class="site-section">
    <div class="site-wrap site-narrow">
        <div class="card site-status is-{{ $signup->status }}">
            <div class="card-body">
                @if ($signup->status === 'approved')
                    <span class="site-icon"><x-icon name="circle-check" /></span>
                    <h1 class="site-title">{{ $done ? __('Approved') : __('This request is already approved') }}</h1>
                    <p class="site-lead">{{ __(':name can use the whole system now.', ['name' => $signup->name]) }}</p>
                @elseif ($signup->status === 'rejected')
                    <span class="site-icon"><x-icon name="circle-xmark" /></span>
                    <h1 class="site-title">{{ $done ? __('Rejected') : __('This request is already rejected') }}</h1>
                    <p class="site-lead">{{ $signup->reason }}</p>
                @else
                    <span class="site-icon"><x-icon name="hourglass-half" /></span>
                    <h1 class="site-title">{{ $action === 'approve' ? __('Approve this registration?') : __('Reject this registration?') }}</h1>
                    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-3">
                        @csrf
                        @if ($action === 'approve')
                            <p class="site-lead">{{ __('The clinic is opened now, with a licence of one year. You can change it in the panel.') }}</p>
                            <button type="submit" class="btn btn-success btn-lg"><x-icon name="check" class="me-1" /> {{ __('Approve') }}</button>
                        @else
                            <div class="mb-3 text-start"><label class="form-label" for="reason">{{ __('Reason') }}</label><textarea class="form-control" id="reason" name="reason" rows="2" maxlength="500"></textarea></div>
                            <button type="submit" class="btn btn-danger btn-lg"><x-icon name="xmark" class="me-1" /> {{ __('Reject') }}</button>
                        @endif
                    </form>
                @endif
                <dl class="facts mt-4">
                    <div><dt>{{ __('Clinic') }}</dt><dd>{{ $signup->name }}</dd></div>
                    <div><dt>{{ __('Address') }}</dt><dd dir="ltr">{{ $signup->domain() }}</dd></div>
                    <div><dt>{{ __('Owner') }}</dt><dd>{{ $signup->owner_name }}</dd></div>
                    <div><dt>{{ __('Phone') }}</dt><dd dir="ltr">{{ $signup->owner_phone }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd dir="ltr">{{ $signup->owner_email }}</dd></div>
                    <div><dt>{{ __('Sent on') }}</dt><dd dir="ltr">{{ $signup->created_at->format('Y-m-d') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</section>
@endsection
