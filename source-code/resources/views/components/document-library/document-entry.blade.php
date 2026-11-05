@props(['document', 'showFolderPath' => false])

<article
    id="document-{{ $document->id }}"
    role="listitem"
    aria-labelledby="document-{{ $document->id }}-filename"
    class="group min-w-0 scroll-mt-4 px-4 py-2.5 transition-colors focus-within:bg-blue-50/40 lg:px-5"
    data-document-id="{{ $document->id }}"
>
    <div class="grid min-w-0 grid-cols-1 gap-x-6 gap-y-2 lg:grid-cols-12 lg:items-center">
        <div class="min-w-0 lg:col-span-5">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-base text-[#26488C]" aria-hidden="true">
                    <i class="ph ph-file-text"></i>
                </span>
                <div class="min-w-0">
                    <p id="document-{{ $document->id }}-filename" class="break-words text-sm font-bold text-gray-900 lg:truncate" title="{{ $document->originalFilename }}">{{ $document->originalFilename }}</p>
                    <p class="mt-px text-xs font-semibold text-[#26488C]">
                        @if($document->entryAvailable && $document->applicationKind !== 'Unknown')
                            <span>{{ $document->applicationKind }}</span><span aria-hidden="true"> · </span>
                        @endif
                        <span>{{ $document->requirement }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="min-w-0 lg:col-span-3">
            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 lg:hidden">Organization</p>
            <p class="mt-0.5 break-words text-sm text-gray-700 lg:mt-0 lg:truncate" title="{{ $document->organizationName }}">{{ $document->organizationName }}</p>
        </div>

        <div class="min-w-0 lg:col-span-2">
            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 lg:hidden">Submitted</p>
            <p class="mt-0.5 break-words text-sm text-gray-700 lg:mt-0">{{ $document->submissionDate ?? 'Date unavailable' }}</p>
        </div>

        <div class="min-w-0 lg:col-span-2" data-library-action-group>
            @if($document->viewDocumentAvailable && $document->viewDocumentUrl !== null)
                <button type="button" data-library-preview-trigger data-preview-url="{{ $document->viewDocumentUrl }}" data-preview-filename="{{ $document->originalFilename }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#26488C] px-3 py-1.5 text-center text-sm font-bold text-white hover:bg-[#1a3461] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2" aria-haspopup="dialog" aria-label="View Document: {{ $document->originalFilename }} (opens a preview)"><i class="ph ph-file-text" aria-hidden="true"></i>View Document</button>
            @else
                <span id="document-{{ $document->id }}-view-unavailable-message" class="sr-only">{{ $document->viewDocumentUnavailableMessage ?? 'View Document is unavailable.' }}</span>
                <span class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-gray-100 px-3 py-1.5 text-center text-sm font-bold text-gray-600" role="link" aria-disabled="true" aria-describedby="document-{{ $document->id }}-view-unavailable-message"><i class="ph ph-file-x" aria-hidden="true"></i>View Document unavailable</span>
            @endif
        </div>
    </div>

    @if(!$document->entryAvailable)
        <p id="document-{{ $document->id }}-unavailable" role="status" class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-950">{{ $document->unavailableMessage }}</p>
    @endif

    @if($showFolderPath && $document->entryAvailable && $document->openFolderUrl !== null)
        <div class="mt-2">
            <a href="{{ $document->openFolderUrl }}" class="rounded font-bold text-[#26488C] underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C]" aria-label="Open Folder containing {{ $document->originalFilename }}">Open Folder</a>
        </div>
    @endif
</article>