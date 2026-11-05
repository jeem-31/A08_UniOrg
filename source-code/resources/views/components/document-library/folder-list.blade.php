@props(['folders'])

<div class="min-w-0 space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div class="min-w-0">
            <h2 id="document-library-folders-heading" class="text-lg font-black text-gray-900">Folders</h2>
            <p class="text-sm text-gray-500">Open a folder to continue through the document hierarchy.</p>
        </div>
        <p class="text-sm font-semibold text-gray-600">{{ count($folders) }} {{ count($folders) === 1 ? 'folder' : 'folders' }}</p>
    </div>

    <ul class="grid min-w-0 grid-cols-1 gap-3" aria-labelledby="document-library-folders-heading">
        @foreach($folders as $folder)
            @php $folderId = 'document-library-folder-'.$loop->iteration; @endphp
            <li class="min-w-0">
                <a
                    href="{{ $folder->url }}"
                    class="group flex min-w-0 w-full flex-col items-stretch gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[#26488C]/50 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2 sm:flex-row sm:items-center sm:gap-4"
                    aria-labelledby="{{ $folderId }}-label {{ $folderId }}-type {{ $folderId }}-count"
                >
                    <span class="flex min-w-0 items-center gap-3 sm:flex-1 sm:gap-4">
                        @if($folder->type === 'organization' && $folder->logoUrl !== null)
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-gray-200" aria-hidden="true">
                                <img src="{{ $folder->logoUrl }}" alt="" class="h-full w-full object-contain p-1" onerror="this.src='https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png'">
                            </span>
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#26488C]" aria-hidden="true">
                                <i class="ph {{ $folder->type === 'organization' ? 'ph-folder' : 'ph-folder-open' }} text-xl"></i>
                            </span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span id="{{ $folderId }}-label" class="block break-words font-black text-gray-900 group-hover:text-[#26488C] sm:truncate" title="{{ $folder->label }}">{{ $folder->label }}</span>
                            <span id="{{ $folderId }}-type" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ $folder->type === 'organization' ? 'Organization folder' : 'Accreditation year folder' }}
                            </span>
                        </span>
                    </span>
                    <span id="{{ $folderId }}-count" class="self-start rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-700 sm:shrink-0 sm:self-auto">
                        {{ $folder->documentCount }} {{ $folder->documentCount === 1 ? 'document' : 'documents' }}
                    </span>
                    <i class="ph ph-caret-right hidden shrink-0 text-xs text-gray-400 sm:block" aria-hidden="true"></i>
                </a>
            </li>
        @endforeach
    </ul>
</div>
