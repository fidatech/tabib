@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" :icon="$icon" :subtitle="trim(($branch?->name ?? '').($hint ? ' · '.__($hint) : ''), ' ·')">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @if ($canManage)
        <a class="btn btn-primary" href="{{ route($route.'.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => $singular]) }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        @if (! empty($branches))
            <form method="GET" class="row g-2 align-items-end mb-3 no-print">
                @include('partials.branch-filter')
            </form>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
                @if ($canManage)
                    <p>{{ __('Press "Add :name" to create the first one.', ['name' => $singular]) }}</p>
                @endif
            </div>
        @else
            <table class="table table-hover w-100">
                <thead>
                    <tr>
                        @foreach (array_keys($columns) as $label)
                            <th>{{ __($label) }}</th>
                        @endforeach
                        <th class="text-end no-print">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                            @foreach ($columns as $column)
                                <td>{{ $column($row) }}</td>
                            @endforeach
                            <td class="text-end text-nowrap no-print">
                                <div class="act-group">
                                    @if ($row->isCancelled())<span class="badge bg-secondary-subtle text-secondary">{{ __('Cancelled') }}</span>@endif
                                    <x-act kind="receipt" :href="route($route.'.receipt', ['id' => $row->getKey()])" blank />
                                    @if ($canManage && ! $row->isCancelled())
                                        <form method="POST" action="{{ route($route.'.destroy', ['id' => $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-act kind="cancel" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
