{{--
    What Fida Technologies has to tell this clinic, on top of every screen: its licence is about
    to end (shown to those who manage the clinic), and the announcements (shown to everyone).
--}}
@php
    $strips = [];
    try {
        $t = tenant();
        $u = auth()->user();
        if ($t && $u) {
            if ($u->can('settings.view')) {
                $l = \App\Services\Licence::state($t);
                if ($l['state'] === 'soon') {
                    $strips[] = ['warning', __('The licence of the system ends in :days days.', ['days' => $l['days']]), __('Please renew it in time, so your work is not stopped.'), route('service.licence'), __('Licence and Invoices')];
                } elseif (in_array($l['state'], ['grace', 'ended'], true)) {
                    $strips[] = ['danger', __('The licence of the system has ended.'), $l['lock_in'] !== null ? __('The system will be locked in :days days unless the licence is renewed.', ['days' => $l['lock_in']]) : '', route('service.licence'), __('Licence and Invoices')];
                }
            }
            foreach (\App\Models\Central\Announcement::showing($t, today_iso()) as $a) {
                $strips[] = [$a->level, $a->title, $a->body, null, null];
            }
        }
    } catch (\Throwable $e) {
        report($e);   // a notice must never stop a clinic from working
    }
@endphp
@foreach ($strips as [$level, $title, $text, $url, $link])
    <div class="service-strip is-{{ $level }} no-print" role="status">
        <x-icon :name="$level === 'info' ? 'bullhorn' : 'triangle-exclamation'" />
        <div class="flex-grow-1"><strong>{{ $title }}</strong><span style="white-space:pre-line">{{ $text }}</span></div>
        @if ($url)<a class="btn btn-sm btn-light-secondary text-nowrap" href="{{ $url }}">{{ $link }}</a>@endif
    </div>
@endforeach
