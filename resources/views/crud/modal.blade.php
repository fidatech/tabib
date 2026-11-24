{{-- Compact: three boxes in a row, and explanations behind a "?" instead of a sentence under the box. --}}
{{-- Only the form of a "new record" screen, shown in the "add new" pop-up beside a pick list (see ERP quick add in app.js). --}}
<form method="POST" action="{{ $action }}" data-quick-form enctype="multipart/form-data" class="form-compact">
    @csrf
    <input type="hidden" name="_quick" value="1">
    <div class="alert alert-danger" data-quick-errors hidden></div>
    <div class="row">
        @foreach ($fields as $field)
            @include('crud.field', ['field' => $field, 'compact' => true])
        @endforeach
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
