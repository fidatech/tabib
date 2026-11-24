{{--
    The history of the patient, section by section. Nothing is deleted: an entry is changed (the old
    value is kept), resolved or stopped, or marked "entered in error" with a reason.
--}}
<div class="row g-3">
    @foreach ($sections as $key => $def)
        <div class="col-12 col-xl-6" id="h-{{ $key }}">
            <div class="card h-100 mb-0 history-card">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="icon-soft primary"><x-icon :name="$def['icon']" /></span>
                    <h2 class="h5 card-title mb-0 flex-grow-1">{{ __($def['title']) }}</h2>
                    <span class="badge bg-light text-dark">{{ $def['entries']->where('status', 'active')->count() }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($def['entries'] as $entry)
                        @php $changes = $revisions[class_basename($entry).'#'.$entry->id] ?? collect(); @endphp
                        <li class="list-group-item history-entry {{ $entry->isError() ? 'is-error' : '' }}">
                            <div class="d-flex gap-2 align-items-start">
                                <div class="flex-grow-1 min-w-0">
                                    <span class="history-summary">{{ $entry->summary() }}</span>
                                    @if ($entry->note)<span class="d-block small text-muted">{{ $entry->note }}</span>@endif
                                    <span class="d-block small text-muted">
                                        {{ fmt_date($entry->created_at) }} · {{ $entry->author?->name }}@if ($entry->source === 'patient') · {{ __('told by the patient') }}@endif
                                        @if ($entry->isError()) · <b class="text-danger">{{ __('Entered in error') }}:</b> {{ $entry->error_reason }}@endif
                                    </span>
                                </div>
                                @unless ($entry->isError())
                                    <span class="badge {{ $entry->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ __($def['statuses'][$entry->status] ?? $entry->status) }}</span>
                                @endunless
                            </div>
                            @if ($changes->isNotEmpty())
                                <details class="small mt-1"><summary>{{ trans_choice(':count change|:count changes', $changes->count()) }}</summary>
                                    <ul class="history-changes">
                                        @foreach ($changes as $rev)
                                            <li>{{ fmt_date($rev->created_at) }} {{ $rev->created_at->format('H:i') }} · {{ $rev->user?->name }}:
                                                @foreach ((array) $rev->changes as $field => $change)
                                                    <span>{{ __($def['fields'][$field][0] ?? ucfirst(str_replace('_', ' ', $field))) }}: <s>{{ is_scalar($change['from'] ?? null) ? $change['from'] : '—' }}</s> → {{ is_scalar($change['to'] ?? null) ? $change['to'] : '—' }}</span>@unless ($loop->last);@endunless
                                                @endforeach
                                                @if ($rev->reason)<em>({{ $rev->reason }})</em>@endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                            @if ($canWrite && ! $entry->isError())
                                <details class="history-edit mt-1"><summary class="small">{{ __('Change') }}</summary>
                                    <form method="POST" action="{{ route('history.update', ['patient' => $patient->id, 'section' => $key, 'id' => $entry->id]) }}" class="row g-2 mt-1">
                                        @csrf @method('PUT')
                                        @include('patients.partials.entry-fields', ['def' => $def, 'entry' => $entry, 'prefix' => 'e'.$key.$entry->id])
                                        @if (count($def['statuses']) > 1)
                                            <div class="col-12 col-md-6"><label class="form-label small" for="s{{ $key.$entry->id }}">{{ __('Status') }}</label>
                                                <select class="form-select form-select-sm" id="s{{ $key.$entry->id }}" name="status" data-plain>
                                                    @foreach ($def['statuses'] as $value => $text)<option value="{{ $value }}" @selected($entry->status === $value)>{{ __($text) }}</option>@endforeach
                                                </select></div>
                                        @endif
                                        <div class="col-12 col-md-6"><label class="form-label small" for="r{{ $key.$entry->id }}">{{ __('Why it is changed') }}</label>
                                            <input type="text" class="form-control form-control-sm" id="r{{ $key.$entry->id }}" name="reason" maxlength="255"></div>
                                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
                                    </form>
                                    <form method="POST" action="{{ route('history.error', ['patient' => $patient->id, 'section' => $key, 'id' => $entry->id]) }}" class="d-flex gap-2 mt-2">
                                        @csrf
                                        <input type="text" class="form-control form-control-sm" name="reason" maxlength="255" required placeholder="{{ __('Why it is a mistake') }}" aria-label="{{ __('Why it is a mistake') }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">{{ __('Entered in error') }}</button>
                                    </form>
                                </details>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">{{ __('None written.') }}</li>
                    @endforelse
                </ul>
                @if ($canWrite)
                    <div class="card-footer">
                        <details @if ($errors->any() && old('_section') === $key) open @endif>
                            <summary class="btn btn-sm btn-outline-soft"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __($def['singular'])]) }}</summary>
                            <form method="POST" action="{{ route('history.store', ['patient' => $patient->id, 'section' => $key]) }}" class="row g-2 mt-2">
                                @csrf
                                <input type="hidden" name="_section" value="{{ $key }}">
                                @include('patients.partials.entry-fields', ['def' => $def, 'entry' => null, 'prefix' => 'n'.$key])
                                <div class="col-12 col-md-6"><label class="form-label small" for="src{{ $key }}">{{ __('Source') }}</label>
                                    <select class="form-select form-select-sm" id="src{{ $key }}" name="source" data-plain>
                                        <option value="clinician">{{ __('Found or confirmed by the clinic') }}</option>
                                        <option value="patient">{{ __('Told by the patient') }}</option>
                                    </select></div>
                                <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button></div>
                            </form>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- the way of life: one form --}}
    <div class="col-12 col-xl-6" id="h-social">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex align-items-center gap-2"><span class="icon-soft primary"><x-icon name="person-walking" /></span><h2 class="h5 card-title mb-0">{{ __('Way of life') }}</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('history.social', ['patient' => $patient->id]) }}" class="row g-2">
                    @csrf
                    @foreach (['smoking' => 'Smoking', 'naswar' => 'Naswar', 'alcohol' => 'Alcohol'] as $habit => $label)
                        <div class="col-12 col-md-4"><label class="form-label small" for="soc-{{ $habit }}">{{ __($label) }}</label>
                            <select class="form-select form-select-sm" id="soc-{{ $habit }}" name="{{ $habit }}" @disabled(! $canWrite) data-plain>
                                <option value="">—</option>
                                @foreach (\App\Models\PatientSocial::HABITS as $value => $text)<option value="{{ $value }}" @selected($social->{$habit} === $value)>{{ __($text) }}</option>@endforeach
                            </select></div>
                    @endforeach
                    <div class="col-12 col-md-6"><label class="form-label small" for="soc-occ">{{ __('Work') }}</label>
                        <input type="text" class="form-control form-control-sm" id="soc-occ" name="occupation" value="{{ $social->occupation }}" maxlength="150" @disabled(! $canWrite)></div>
                    <div class="col-12 col-md-6"><label class="form-label small" for="soc-mar">{{ __('Marital status') }}</label>
                        <select class="form-select form-select-sm" id="soc-mar" name="marital_status" @disabled(! $canWrite) data-plain>
                            <option value="">—</option>
                            @foreach (\App\Models\PatientSocial::MARITAL as $value => $text)<option value="{{ $value }}" @selected($social->marital_status === $value)>{{ __($text) }}</option>@endforeach
                        </select></div>
                    <div class="col-12"><label class="form-label small" for="soc-note">{{ __('Note') }}</label>
                        <input type="text" class="form-control form-control-sm" id="soc-note" name="note" value="{{ $social->note }}" maxlength="500" @disabled(! $canWrite)></div>
                    @if ($canWrite)<div class="col-12"><button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>@endif
                </form>
            </div>
        </div>
    </div>
</div>
@include('partials.icd10-search')
