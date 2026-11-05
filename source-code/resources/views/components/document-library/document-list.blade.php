@props(['documents', 'pagination', 'showFolderPath' => false])

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-lg font-black text-gray-900">Documents</h2>
            <p class="mt-0.5 text-sm text-gray-500">Submitted files are read-only in the Document Library.</p>
        </div>
        <p class="text-sm font-semibold text-gray-700">
            Showing {{ $pagination->firstResult }}–{{ $pagination->lastResult }} of {{ $pagination->total }}
        </p>
    </div>

    <div class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="hidden border-b border-gray-100 bg-gray-50/70 px-5 py-2 lg:grid lg:grid-cols-12 lg:gap-x-6">
            <span class="lg:col-span-5 text-xs font-black uppercase tracking-wider text-gray-500">Document</span>
            <span class="lg:col-span-3 text-xs font-black uppercase tracking-wider text-gray-500">Organization</span>
            <span class="lg:col-span-2 text-xs font-black uppercase tracking-wider text-gray-500">Submitted</span>
            <span class="lg:col-span-2 text-right text-xs font-black uppercase tracking-wider text-gray-500">Actions</span>
        </div>
        <div class="min-w-0 divide-y divide-gray-100" role="list" aria-label="Submitted documents">
            @foreach($documents as $document)
                <x-document-library.document-entry :document="$document" :show-folder-path="$showFolderPath" />
            @endforeach
        </div>
    </div>
</div>