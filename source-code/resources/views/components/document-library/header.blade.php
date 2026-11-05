@props(['library'])

@php
    $keyword = is_string($library->queryParameters['q'] ?? null) ? $library->queryParameters['q'] : '';
    $canonicalQuery = [];
    parse_str((string) (parse_url($library->canonicalUrl, PHP_URL_QUERY) ?? ''), $canonicalQuery);
    unset($canonicalQuery['q'], $canonicalQuery['page'], $canonicalQuery['highlight']);
    $clearKeywordUrl = route('osas.smart-search').($canonicalQuery === [] ? '' : '?'.http_build_query($canonicalQuery));
    $keywordErrorId = isset($library->validationMessages['q']) ? 'document-library-search-error' : null;
@endphp

<header class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-[#C4973B]">OSAS workspace</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">Document Library</h1>
            <p class="mt-1 text-sm text-gray-600">
                @if($library->heading === 'Document Library')
                    Browse current and historical accreditation documents by organization.
                @else
                    Current location: <span class="font-bold text-gray-800">{{ $library->heading }}</span>
                @endif
            </p>
        </div>

        <form action="{{ route('osas.smart-search') }}" method="GET" role="search" class="w-full lg:max-w-xl">
            @foreach($canonicalQuery as $name => $value)
                @if(is_array($value))
                    @foreach($value as $item)
                        @if(is_scalar($item))
                            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                        @endif
                    @endforeach
                @elseif(is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="document-library-search" class="mb-1 block text-sm font-bold text-gray-800">Search documents</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="document-library-search" name="q" type="search" value="{{ $keyword }}" placeholder="Filename, organization, requirement, tags, or summary" @if($keywordErrorId) aria-invalid="true" aria-describedby="{{ $keywordErrorId }}" @endif class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:border-[#26488C] focus:ring-2 focus:ring-[#26488C]/20">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#26488C] px-4 py-2.5 text-sm font-black text-white hover:bg-[#1a3461] focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2">
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    Search
                </button>
            </div>
            @if($keywordErrorId)
                <p id="{{ $keywordErrorId }}" class="mt-1 text-sm text-red-700">{{ implode(' ', $library->validationMessages['q']) }}</p>
            @endif
            @if($keyword !== '')
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-gray-600">Searching for <strong class="text-gray-900">“{{ $keyword }}”</strong></span>
                    <a href="{{ $clearKeywordUrl }}" class="rounded font-bold text-[#26488C] underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-[#26488C]" aria-label="Clear search keyword {{ $keyword }}">Clear keyword</a>
                </div>
            @endif
        </form>
    </div>
</header>
