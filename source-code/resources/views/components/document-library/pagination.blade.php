@props(['pagination'])

@if($pagination->lastPage > 1)
    <nav class="flex min-w-0 flex-col items-stretch gap-3 rounded-xl border border-gray-200 bg-white p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between" aria-label="Document results pagination">
        @if($pagination->previousUrl !== null)
            <a href="{{ $pagination->previousUrl }}" rel="prev" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-gray-700 hover:border-[#26488C] hover:text-[#26488C] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2" aria-label="Previous document results page">Previous</a>
        @else
            <span class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-100 px-3 py-2 text-sm font-bold text-gray-500" role="link" aria-disabled="true" aria-label="Previous document results page unavailable">Previous unavailable</span>
        @endif

        <div class="flex min-w-0 flex-wrap items-center justify-center gap-2 text-center text-sm">
            @if($pagination->currentPage > 1)
                <a href="{{ $pagination->firstUrl }}" class="rounded px-2 py-1 font-bold text-[#26488C] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C]" aria-label="First document results page">First</a>
            @endif
            <span aria-current="page" class="font-semibold text-gray-700">Page {{ $pagination->currentPage }} of {{ $pagination->lastPage }}</span>
            @if($pagination->currentPage < $pagination->lastPage)
                <a href="{{ $pagination->lastUrl }}" class="rounded px-2 py-1 font-bold text-[#26488C] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C]" aria-label="Last document results page">Last</a>
            @endif
        </div>

        @if($pagination->nextUrl !== null)
            <a href="{{ $pagination->nextUrl }}" rel="next" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-bold text-gray-700 hover:border-[#26488C] hover:text-[#26488C] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2" aria-label="Next document results page">Next</a>
        @else
            <span class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-100 px-3 py-2 text-sm font-bold text-gray-500" role="link" aria-disabled="true" aria-label="Next document results page unavailable">Next unavailable</span>
        @endif
    </nav>
@endif
