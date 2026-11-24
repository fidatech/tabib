{{-- Top of the dashboard: whose system this is and who is signed in, the Solar Hijri calendar with the time, and the subscription. --}}
@php
    $me = auth()->user();
    $clinic = tenant();
    $licence = \App\Services\Licence::state($clinic);
    $local = text_dir() === 'rtl' ? setting('company_name_local') : null;
    $name = $local ?: (setting('company_name') ?: $clinic->name);
    $mine = $me->branch ?: \App\Models\Branch::main();
    $branches = \App\Models\Branch::withBooks()->where('is_active', true)->count();
    $allowed = $clinic->max_branches !== null ? 1 + $clinic->max_branches : null;
    $tone = ['ok' => 'is-ok', 'soon' => 'is-soon'][$licence['state']] ?? 'is-ended';
    $word = ['ok' => 'Active', 'soon' => 'Ends soon', 'grace' => 'Ended, still open', 'ended' => 'Ended'][$licence['state']];
    $share = $licence['days'] === null ? 100 : max(0, min(100, (int) round($licence['days'] * 100 / 365)));

    // the month of the Solar Hijri calendar that holds today, week by week from Saturday
    $now = now();
    [$jy, $jm, $jd] = \App\Support\Shamsi::fromGregorian($now->year, $now->month, $now->day);
    [$gy, $gm, $gd] = \App\Support\Shamsi::toGregorian($jy, $jm, 1);
    $first = \Illuminate\Support\Carbon::create($gy, $gm, $gd);
    [$ny, $nm, $nd] = \App\Support\Shamsi::toGregorian($jm === 12 ? $jy + 1 : $jy, $jm === 12 ? 1 : $jm + 1, 1);
    $length = (int) $first->diffInDays(\Illuminate\Support\Carbon::create($ny, $nm, $nd));
    $lead = ($first->dayOfWeek + 1) % 7;   // Saturday is the first day of the week
    $weekdays = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    $hour = (int) $now->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $role = $me->getRoleNames()->map(fn ($r) => __($r))->implode(', ');
@endphp
<section class="dash-top" aria-label="{{ $name }}">
    <div class="dash-id">
        <span class="dash-id-avatar" aria-hidden="true">{{ $me->initials() }}</span>
        <div class="min-w-0">
            <p class="dash-id-greeting">{{ __($greeting) }},</p>
            <h2 class="dash-id-user">{{ $me->name }}</h2>
            <ul class="dash-id-facts">
                <li><x-icon name="code-branch" /> {{ $mine->name }}</li>
                @if ($role !== '')<li><x-icon name="user-shield" /> {{ $role }}</li>@endif
            </ul>
        </div>
    </div>

    <div class="dash-cal">
        <div class="dash-cal-now">
            <div class="dash-cal-date">
                <strong>{{ $jd }} {{ __(\App\Support\Shamsi::MONTHS[$jm]) }} {{ $jy }}</strong>
                <span dir="ltr">{{ $now->format('l, j F Y') }}</span>
            </div>
            <div class="dash-clock" dir="ltr" data-clock data-zone="{{ config('app.timezone') }}" aria-label="{{ __('Time') }}">
                <x-icon name="clock" set="regular" /> <span data-clock-time>{{ $now->format('h:i') }}</span><span class="dash-clock-rest">:<span data-clock-seconds>{{ $now->format('s') }}</span> <span data-clock-half>{{ $now->format('A') }}</span></span>
            </div>
        </div>
        {{-- this week of the Solar Hijri month, from Saturday --}}
        <ol class="dash-week" aria-label="{{ __(\App\Support\Shamsi::MONTHS[$jm]) }} {{ $jy }}">
            @php $start = $jd - (($now->dayOfWeek + 1) % 7); @endphp
            @foreach ($weekdays as $i => $day)
                @php $n = $start + $i; @endphp
                <li class="{{ $n === $jd ? 'is-today' : '' }} {{ $i === 6 ? 'is-off' : '' }}" @if ($n === $jd) aria-current="date" @endif><span>{{ __($day) }}</span><b>{{ $n >= 1 && $n <= $length ? $n : '·' }}</b></li>
            @endforeach
        </ol>
    </div>

    <div class="dash-plan {{ $tone }}">
        <div class="dash-plan-head"><span><x-icon name="shield-halved" /> {{ __('Subscription') }}</span><span class="dash-plan-state">{{ __($word) }}</span></div>
        <div class="dash-plan-days">
            @if ($licence['days'] === null)
                <strong>{{ __('No date') }}</strong>
            @elseif ($licence['days'] >= 0)
                <strong><span dir="ltr">{{ $licence['days'] }}</span> {{ __('days left') }}</strong>
            @else
                <strong><span dir="ltr">{{ abs($licence['days']) }}</span> {{ __('days since it ended') }}</strong>
            @endif
            @if ($clinic->licence_expires_on)<span>{{ __('until :date', ['date' => fmt_date($clinic->licence_expires_on->toDateString())]) }}</span>@endif
        </div>
        <div class="dash-plan-bar" role="progressbar" aria-label="{{ __('Subscription') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $share }}"><span style="width:{{ $share }}%"></span></div>
        <div class="dash-plan-foot">
            <span>{{ __('Branches') }}: <b dir="ltr">{{ $branches }}{{ $allowed !== null ? ' / '.$allowed : '' }}</b></span>
            @can('settings.view')
                <a href="{{ route('service.licence') }}">{{ __('Subscription') }} <x-icon name="arrow-right" /></a>
            @endcan
        </div>
    </div>
</section>
<script>
(function () {
    var box = document.querySelector('[data-clock]'); if (!box) return;
    var time = box.querySelector('[data-clock-time]'), seconds = box.querySelector('[data-clock-seconds]'), half = box.querySelector('[data-clock-half]');
    var format;
    try { format = new Intl.DateTimeFormat('en-US', { timeZone: box.getAttribute('data-zone'), hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }); } catch (e) { return; }
    var tick = function () {
        var parts = {}; format.formatToParts(new Date()).forEach(function (p) { parts[p.type] = p.value; });
        time.textContent = parts.hour + ':' + parts.minute; seconds.textContent = parts.second; half.textContent = parts.dayPeriod;
    };
    tick(); setInterval(tick, 1000);
})();
</script>
