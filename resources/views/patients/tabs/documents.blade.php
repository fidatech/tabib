{{-- Lab reports, X-rays, letters: kept encrypted; every opening is written in the audit log. --}}
@if ($canWrite)
    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Add a document') }}</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('history.upload', ['patient' => $patient->id]) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-12 col-md-3"><label class="form-label small" for="d-kind">{{ __('Kind') }}</label>
                    <select class="form-select form-select-sm" id="d-kind" name="kind" data-plain>
                        @foreach (\App\Models\PatientDocument::KINDS as $value => $text)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ __($text) }}</option>@endforeach
                    </select></div>
                <div class="col-12 col-md-3"><label class="form-label small" for="d-title">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" id="d-title" name="title" value="{{ old('title') }}" maxlength="150" required placeholder="{{ __('For example: Blood test, CBC') }}"></div>
                <div class="col-6 col-md-2"><label class="form-label small" for="d-date">{{ __('Date of the paper') }}</label>
                    <x-date name="dated_on" :value="old('dated_on')" id="d-date" class="form-control-sm" /></div>
                <div class="col-6 col-md-3"><label class="form-label small" for="d-file">{{ __('File') }} <span class="text-danger">*</span></label>
                    <input type="file" class="form-control form-control-sm @error('file') is-invalid @enderror" id="d-file" name="file" accept="application/pdf,image/png,image/jpeg,image/webp" required>
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100"><x-icon name="file-arrow-up" /></button></div>
            </form>
            <p class="small text-muted mb-0 mt-2">{{ __('PDF or picture, up to 10 MB. Kept encrypted.') }}</p>
        </div>
    </div>
@endif
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($documents as $d)
            @php $error = $d->status === 'entered_in_error'; @endphp
            <div class="list-group-item d-flex flex-wrap gap-2 align-items-center history-entry {{ $error ? 'is-error' : '' }}">
                <span class="icon-soft {{ $d->mime === 'application/pdf' ? 'danger' : 'info' }}"><x-icon :name="$d->mime === 'application/pdf' ? 'file-pdf' : 'file-image'" /></span>
                <span class="flex-grow-1 min-w-0">
                    @if ($error)<span class="history-summary">{{ $d->title }}</span>@else<a class="fw-semibold" href="{{ route('history.document', ['patient' => $patient->id, 'id' => $d->id]) }}" target="_blank" rel="noopener">{{ $d->title }}</a>@endif
                    <span class="d-block small text-muted">{{ __(\App\Models\PatientDocument::KINDS[$d->kind] ?? $d->kind) }} · {{ fmt_date($d->dated_on ?? $d->created_at) }} · {{ human_size($d->size) }} · {{ $d->author?->name }}
                        @if ($error) · <b class="text-danger">{{ __('Entered in error') }}:</b> {{ $d->error_reason }}@endif</span>
                </span>
                @if ($canWrite && ! $error)
                    <details class="small"><summary>{{ __('Mistake?') }}</summary>
                        <form method="POST" action="{{ route('history.error', ['patient' => $patient->id, 'section' => 'documents', 'id' => $d->id]) }}" class="d-flex gap-1 mt-1">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm" required maxlength="255" placeholder="{{ __('Why it is a mistake') }}" aria-label="{{ __('Why it is a mistake') }}">
                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Entered in error') }}</button></form>
                    </details>
                @endif
            </div>
        @empty
            <div class="list-group-item text-muted">{{ __('No document yet.') }}</div>
        @endforelse
    </div>
</div>
