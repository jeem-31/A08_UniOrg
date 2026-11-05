@props(['paginator', 'label' => 'Results'])

@if($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $pageNumbers = collect([1, $lastPage, $currentPage - 1, $currentPage, $currentPage + 1])
            ->filter(fn ($page) => $page >= 1 && $page <= $lastPage)
            ->unique()
            ->sort()
            ->values();
        $previousPage = null;
    @endphp

    <nav class="dean-users-pagination" aria-label="{{ $label }} pagination">
        @if($paginator->onFirstPage())
            <span class="dean-users-pagination__control is-disabled" aria-disabled="true"><i class="ph ph-caret-left"></i></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" data-dean-navigation data-dean-partial="users" data-dean-skeleton="users" aria-label="Previous page"><i class="ph ph-caret-left"></i></a>
        @endif

        @foreach($pageNumbers as $page)
            @if($previousPage !== null && $page > $previousPage + 1)
                <span class="dean-users-pagination__ellipsis" aria-hidden="true">…</span>
            @endif

            @if($page === $currentPage)
                <span class="is-current" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" data-dean-navigation data-dean-partial="users" data-dean-skeleton="users" aria-label="Go to page {{ $page }}">{{ $page }}</a>
            @endif
            @php $previousPage = $page; @endphp
        @endforeach

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" data-dean-navigation data-dean-partial="users" data-dean-skeleton="users" aria-label="Next page"><i class="ph ph-caret-right"></i></a>
        @else
            <span class="dean-users-pagination__control is-disabled" aria-disabled="true"><i class="ph ph-caret-right"></i></span>
        @endif
    </nav>
@endif
