@extends('layouts.site', ['title' => __('Your request')])

@section('content')
<section class="site-section">
    <div class="site-wrap site-narrow">
        <div class="card site-status is-{{ $signup->status }}">
            <div class="card-body">
                @if ($signup->status === 'approved')
                    <span class="site-icon"><x-icon name="circle-check" /></span>
                    <h1 class="site-title">{{ __('Your clinic is ready') }}</h1>
                    <p class="site-lead">{{ __('Sign in at your own address with the email and the password you chose.') }}</p>
                    @if ($signup->tenant)
                        <p><a class="btn btn-primary btn-lg" dir="ltr" href="//{{ $signup->tenant->domain }}/login">{{ $signup->tenant->domain }}</a></p>
                    @endif
                @elseif ($signup->status === 'rejected')
                    <span class="site-icon"><x-icon name="circle-xmark" /></span>
                    <h1 class="site-title">{{ __('The request was not approved') }}</h1>
                    <p class="site-lead">{{ $signup->reason }}</p>
                    <p><a class="btn btn-primary" href="{{ route('site.register') }}">{{ __('Send a new request') }}</a></p>
                @elseif ($signup->tenant)
                    <span class="site-icon"><x-icon name="circle-check" /></span>
                    <h1 class="site-title">{{ __('Your clinic is registered') }}</h1>
                    <p class="site-lead">{{ __('Sign in now with the email and the password you chose. Until our team approves it, only your subscription page opens.') }}</p>
                    <p><a class="btn btn-primary btn-lg" dir="ltr" href="//{{ $signup->tenant->domain }}/login">{{ $signup->tenant->domain }}</a></p>
                @else
                    <span class="site-icon"><x-icon name="hourglass-half" /></span>
                    <h1 class="site-title">{{ __('We have your request') }}</h1>
                    <p class="site-lead">{{ __('Our team checks it and contacts you. Keep the address of this page: it shows when your clinic is ready.') }}</p>
                @endif
                <dl class="facts mt-4">
                    <div><dt>{{ __('Clinic') }}</dt><dd>{{ $signup->name }}</dd></div>
                    <div><dt>{{ __('Address') }}</dt><dd dir="ltr">{{ $signup->domain() }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd dir="ltr">{{ $signup->owner_email }}</dd></div>
                    <div><dt>{{ __('Sent on') }}</dt><dd dir="ltr">{{ $signup->created_at->format('Y-m-d') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</section>
@endsection
