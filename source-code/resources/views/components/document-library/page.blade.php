@props(['library', 'filterOptions' => [], 'filterLabels' => []])

@php
    $resultDescriptionIds = [];
    if (count($library->validationMessages) > 0) {
        $resultDescriptionIds[] = 'document-library-validation-summary';
    }
    if ($library->failure !== null) {
        $resultDescriptionIds[] = 'document-library-retrieval-error';
    }
@endphp

<div
    id="document-library"
    class="mx-auto min-w-0 w-full max-w-7xl space-y-5 [overflow-wrap:anywhere]"
    data-document-library-region
    data-library-level="{{ $library->level }}"
    data-library-canonical-url="{{ $library->canonicalUrl }}"
>
    <x-document-library.header :library="$library" />
    <x-document-library.breadcrumbs :library="$library" />
    <x-document-library.query-controls :library="$library" :filter-options="$filterOptions" :filter-labels="$filterLabels" />

    <section
        id="document-library-results"
        class="min-w-0 scroll-mt-4 space-y-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2"
        aria-labelledby="document-library-results-heading"
        @if($resultDescriptionIds !== []) aria-describedby="{{ implode(' ', $resultDescriptionIds) }}" @endif
        tabindex="-1"
    >
        <h2 id="document-library-results-heading" class="sr-only">{{ $library->heading }}</h2>

        @if($library->failure !== null)
            <x-document-library.feedback
                :state="$library->failure"
                tone="error"
                id="document-library-retrieval-error"
                :should-focus="true"
            />
        @endif

        @if(count($library->folders) > 0)
            <x-document-library.folder-list :folders="$library->folders" />
        @elseif(count($library->documents) > 0)
            <x-document-library.document-list
                :documents="$library->documents"
                :pagination="$library->pagination"
                :show-folder-path="$library->level === 'search'"
            />
            <x-document-library.pagination :pagination="$library->pagination" />
        @elseif($library->emptyState !== null)
            <x-document-library.feedback :state="$library->emptyState" />
        @endif
    </section>

    <x-document-library.live-status :pagination="$library->pagination" />
</div>
