@props(['library', 'filterOptions' => [], 'filterLabels' => []])

@php
    $filterDefinitions = [
        'department' => 'Colleges',
    ];
    $yearOptions = is_array($filterOptions['year'] ?? null)
        ? array_values(array_filter($filterOptions['year'], 'is_string'))
        : [];
    $yearChoices = array_reverse($yearOptions);
    $selectedYearFrom = is_string($library->queryParameters['year_from'] ?? null) ? $library->queryParameters['year_from'] : '';
    $selectedArchiveScope = is_string($library->queryParameters['archive_scope'] ?? null) ? $library->queryParameters['archive_scope'] : '';
    $archiveScopeOptions = [
        '' => 'All documents',
        'current' => 'Current period',
        'archive' => 'Archive',
    ];
    $sortOptions = [
        'newest' => 'Newest submission',
        'oldest' => 'Oldest submission',
        'filename_asc' => 'Filename A–Z',
        'filename_desc' => 'Filename Z–A',
    ];
    $query = [];
    parse_str((string) (parse_url($library->canonicalUrl, PHP_URL_QUERY) ?? ''), $query);
    unset($query['page'], $query['highlight']);
    $selectedSort = is_string($library->queryParameters['sort'] ?? null)
        ? $library->queryParameters['sort']
        : 'newest';
    $selected = static function (string $key) use ($library): array {
        $values = $library->queryParameters[$key] ?? [];
        return is_array($values)
            ? array_values(array_filter($values, 'is_string'))
            : [];
    };
    $urlWithout = static function (array $remove, array $replace = []) use ($query): string {
        $parameters = $query;
        foreach ($remove as $key) {
            unset($parameters[$key]);
        }
        foreach ($replace as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                unset($parameters[$key]);
            } else {
                $parameters[$key] = $value;
            }
        }
        return route('osas.smart-search').($parameters === [] ? '' : '?'.http_build_query($parameters));
    };
    $allFilterParamKeys = ['department', 'year_from', 'archive_scope', 'requirement', 'document_type', 'application_kind', 'application_status', 'submitted_from', 'submitted_to'];
    $filterKeys = $allFilterParamKeys;
    $hasFilters = collect($filterKeys)->contains(fn (string $key): bool => isset($library->queryParameters[$key]));
    $hasKeyword = is_string($library->queryParameters['q'] ?? null);
    $clearAllUrl = $urlWithout(array_merge(['q'], $filterKeys));
@endphp

@if(count($library->validationMessages) > 0)
    @php
        $errorTargets = [
            'q' => 'document-library-search',
            'department' => 'document-library-filter-department',
            'year_from' => 'document-library-filter-year',
            'archive_scope' => 'document-library-filter-archive-scope',
            'sort' => 'document-library-sort',
            'page' => 'document-library-results',
        ];
    @endphp
    <div
        id="document-library-validation-summary"
        role="alert"
        aria-labelledby="document-library-validation-heading"
        tabindex="-1"
        @if($library->failure === null) autofocus @endif
        class="scroll-mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2"
    >
        <h2 id="document-library-validation-heading" class="font-black">Some library query values could not be used</h2>
        <p class="mt-1 text-sm">Review the following messages. Valid query values and your current location were preserved.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            @foreach($library->validationMessages as $field => $messages)
                @php $errorTarget = $errorTargets[$field] ?? 'document-library-results'; @endphp
                @foreach($messages as $message)
                    <li><a href="#{{ $errorTarget }}" class="rounded font-semibold underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif

<section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="document-library-filter-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
        <div class="min-w-0">
            <h2 id="document-library-filter-heading" class="text-base font-black text-gray-900">Filter and sort documents</h2>
            <p class="mt-0.5 text-xs text-gray-500">Narrow results by college, accreditation year, and sort order.</p>
        </div>
        @if($hasKeyword || $hasFilters)
            <a href="{{ $clearAllUrl }}" class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:border-[#26488C] hover:text-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2">Clear All</a>
        @endif
    </div>

    <div id="document-library-filter-panel" class="border-t border-gray-200 p-4 sm:p-5">
        <form action="{{ route('osas.smart-search') }}" method="GET">
            @foreach($query as $name => $value)
                @continue(in_array($name, ['department', 'year_from', 'archive_scope', 'sort', 'page', 'highlight'], true))
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

            <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($filterDefinitions as $key => $label)
                    @php
                        $values = is_array($filterOptions[$key] ?? null) ? $filterOptions[$key] : [];
                        $chosen = $selected($key);
                        $messageId = isset($library->validationMessages[$key]) ? "{$key}-filter-error" : null;
                    @endphp
                    <div id="document-library-filter-{{ $key }}" class="min-w-0">
                        <label for="document-library-{{ $key }}" class="mb-1 block text-sm font-bold text-gray-900">{{ $label }}</label>
                        @if(count($values) > 0)
                            <select id="document-library-{{ $key }}" name="{{ $key }}" @if($messageId) aria-invalid="true" aria-describedby="{{ $messageId }}" @endif class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                                <option value="">All {{ strtolower($label) }}</option>
                                @foreach($values as $value)
                                    <option value="{{ $value }}" @selected(in_array($value, $chosen, true))>{{ $filterLabels[$key][$value] ?? $value }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="text-xs italic text-gray-500">No options available.</p>
                        @endif
                        @if($messageId)
                            <p id="{{ $messageId }}" class="mt-1 text-sm text-red-700">{{ implode(' ', $library->validationMessages[$key]) }}</p>
                        @endif
                    </div>
                @endforeach

                <div id="document-library-filter-year" class="min-w-0">
                    <label for="document-library-year" class="mb-1 block text-sm font-bold text-gray-900">Accreditation Year</label>
                    @if(count($yearOptions) > 0)
                        <select id="document-library-year" name="year_from" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                            <option value="">Any year</option>
                            @foreach($yearChoices as $yearValue)
                                <option value="{{ $yearValue }}" @selected($selectedYearFrom === $yearValue)>{{ $yearValue }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-xs italic text-gray-500">No options available.</p>
                    @endif
                    @if(isset($library->validationMessages['year_from']))
                        <p id="document-library-filter-year-error" class="mt-1 text-sm text-red-700">{{ implode(' ', $library->validationMessages['year_from']) }}</p>
                    @endif
                </div>

                <div id="document-library-filter-archive-scope" class="min-w-0">
                    <label for="document-library-archive-scope" class="mb-1 block text-sm font-bold text-gray-900">Archive</label>
                    <select id="document-library-archive-scope" name="archive_scope" @if(isset($library->validationMessages['archive_scope'])) aria-invalid="true" aria-describedby="document-library-archive-scope-error" @endif class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                        @foreach($archiveScopeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedArchiveScope === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if(isset($library->validationMessages['archive_scope']))
                        <p id="document-library-archive-scope-error" class="mt-1 text-sm text-red-700">{{ implode(' ', $library->validationMessages['archive_scope']) }}</p>
                    @endif
                </div>

                <div id="document-library-sort" class="min-w-0">
                    <label for="document-library-sort-select" class="mb-1 block text-sm font-bold text-gray-900">Sort by</label>
                    <select id="document-library-sort-select" name="sort" @if(isset($library->validationMessages['sort'])) aria-invalid="true" aria-describedby="document-library-sort-error" @endif class="w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 focus:border-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if(isset($library->validationMessages['sort']))
                        <p id="document-library-sort-error" class="mt-1 text-sm text-red-700">{{ implode(' ', $library->validationMessages['sort']) }}</p>
                    @endif
                </div>

                <div class="flex flex-col items-stretch gap-2 sm:flex-row lg:flex-col" data-library-filter-actions>
                    <button type="submit" class="rounded-lg bg-[#26488C] px-4 py-2 text-sm font-black text-white hover:bg-[#1a3461] focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2">Apply filters</button>
                    @if($hasFilters)
                        <a href="{{ $urlWithout($filterKeys) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:border-[#26488C] hover:text-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2">Clear filters</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</section>

@if($hasFilters)
    <section class="rounded-xl border border-blue-100 bg-blue-50 p-4" aria-labelledby="active-library-filters-heading">
        <div class="flex flex-wrap items-center gap-2">
            <h2 id="active-library-filters-heading" class="mr-1 text-sm font-black text-blue-950">Active filters</h2>
            @foreach($filterDefinitions as $key => $label)
                @foreach($selected($key) as $value)
                    @php
                        $remaining = array_values(array_filter($selected($key), fn (string $selectedValue): bool => $selectedValue !== $value));
                        $removeUrl = $urlWithout([], [$key => $remaining]);
                    @endphp
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-blue-950 ring-1 ring-blue-200">
                        {{ $label }}: {{ $filterLabels[$key][$value] ?? $value }}
                        <a href="{{ $removeUrl }}" class="rounded px-1 text-blue-800 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-[#26488C]" aria-label="Remove {{ $label }} filter {{ $filterLabels[$key][$value] ?? $value }}">×</a>
                    </span>
                @endforeach
            @endforeach
            @if($selectedYearFrom !== '')
                @php
                    $removeYearUrl = $urlWithout([], ['year_from' => null]);
                @endphp
                <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-blue-950 ring-1 ring-blue-200">
                    Accreditation Year: {{ $selectedYearFrom }}
                    <a href="{{ $removeYearUrl }}" class="rounded px-1 text-blue-800 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-[#26488C]" aria-label="Remove Accreditation Year filter">×</a>
                </span>
            @endif
            @if($selectedArchiveScope !== '')
                @php
                    $removeArchiveUrl = $urlWithout([], ['archive_scope' => null]);
                    $archiveLabel = $archiveScopeOptions[$selectedArchiveScope] ?? $selectedArchiveScope;
                @endphp
                <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-blue-950 ring-1 ring-blue-200">
                    Archive: {{ $archiveLabel }}
                    <a href="{{ $removeArchiveUrl }}" class="rounded px-1 text-blue-800 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-[#26488C]" aria-label="Remove Archive filter">×</a>
                </span>
            @endif
        </div>
    </section>
@endif