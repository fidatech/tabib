{{-- People of the main branch who may oversee choose whose books they look at. --}}
@if (! empty($branches))
    <div class="col-12 col-md-auto">
        <label class="form-label small mb-1">{{ __('Branch') }}</label>
        <select class="form-select" name="branch" aria-label="{{ __('Branch') }}" onchange="this.form.submit()">
            @foreach ($branches as $id => $name)
                <option value="{{ $id }}" @selected((int) ($branch?->id) === (int) $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
@endif
