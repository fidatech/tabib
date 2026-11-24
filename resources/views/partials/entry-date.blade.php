{{--
    The date of the paper being recorded. Entries go on the open working day of the branch and on
    no other day, so the date is shown and cannot be changed.
--}}
<div class="{{ $col ?? 'col-6 col-md-3' }}">
    <label class="form-label">{{ __('Date') }}</label>
    <div class="input-group" title="{{ __('Entries are recorded on the open working day. A closed day is locked.') }}">
        <span class="input-group-text"><x-icon name="calendar-days" /></span>
        <input type="text" class="form-control" value="{{ fmt_date(today_iso()) }}" dir="ltr" readonly tabindex="-1" aria-label="{{ __('Date') }}">
    </div>
</div>
