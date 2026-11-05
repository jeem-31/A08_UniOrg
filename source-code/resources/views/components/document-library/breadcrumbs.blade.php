@props(['library'])

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <nav aria-label="Document Library breadcrumb" class="min-w-0">
        <ol class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
            @foreach($library->breadcrumbs as $breadcrumb)
                <li class="flex min-w-0 items-center gap-2">
                    @if(!$loop->first)
                        <i class="ph ph-caret-right text-[9px] text-gray-300" aria-hidden="true"></i>
                    @endif
                    @if($breadcrumb->url !== null && !$breadcrumb->current)
                        <a href="{{ $breadcrumb->url }}" class="rounded font-semibold text-[#26488C] hover:underline focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2">
                            {{ $breadcrumb->label }}
                        </a>
                    @else
                        <span @if($breadcrumb->current) aria-current="page" @endif class="min-w-0 truncate font-semibold text-gray-800" title="{{ $breadcrumb->label }}">
                            {{ $breadcrumb->label }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    @if($library->parentUrl !== null)
        <a href="{{ $library->parentUrl }}" class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-bold text-gray-700 hover:border-[#26488C] hover:text-[#26488C] focus:outline-none focus:ring-2 focus:ring-[#26488C] focus:ring-offset-2" aria-label="Back to parent folder">
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            Back
        </a>
    @endif
</div>
