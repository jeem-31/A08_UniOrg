@props(['pagination'])

<p id="document-library-live-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true">
    @if($pagination->total === 0)
        0 documents found.
    @elseif($pagination->firstResult > 0)
        {{ $pagination->total }} {{ $pagination->total === 1 ? 'document' : 'documents' }} found.
        @if($pagination->total === 1)
            Showing the only match.
        @else
            Showing {{ $pagination->firstResult }} through {{ $pagination->lastResult }}.
        @endif
    @else
        {{ $pagination->total }} {{ $pagination->total === 1 ? 'document' : 'documents' }} found in the displayed folders.
    @endif
</p>
