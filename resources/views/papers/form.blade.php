@extends('layouts.app', ['title' => __('Add :name', ['name' => $singular])])

@section('content')
<x-page-header :title="__('Add :name', ['name' => $singular])" :icon="$icon" :crumbs="[[$title, route($route.'.index')], [__('Add')]]" />

<form method="POST" action="{{ route($route.'.store') }}" class="card">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            @include('partials.entry-date')
            @foreach ($fields as $field)
                @include('crud.field', ['field' => $field])
            @endforeach
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route($route.'.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
