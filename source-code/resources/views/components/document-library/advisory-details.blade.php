@props(['details', 'documentId'])

<div id="document-details-{{ $documentId }}" class="border-t border-gray-200 bg-blue-50/40 p-4">
    @if(!empty($details->advisory))
    <p class="rounded-lg border border-blue-100 bg-white p-3 text-sm font-semibold text-[#26488C]">{{ $details->advisory }}</p>
@endif

    @if($details->documentType !== null || $details->completionDate !== null)
        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @if($details->documentType !== null)
                <div><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Document Type</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $details->documentType }}</dd></div>
            @endif
            @if($details->completionDate !== null)
                <div><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Processing completed</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $details->completionDate }}</dd></div>
            @endif
        </dl>
    @endif

    @if($details->summary !== null)
        <div class="mt-4"><h4 class="text-xs font-bold uppercase tracking-wide text-gray-500">Summary</h4><p class="mt-1 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $details->summary }}</p></div>
    @endif

    @if(count($details->tags) > 0)
        <div class="mt-4"><h4 class="text-xs font-bold uppercase tracking-wide text-gray-500">Auto-Tags</h4><ul class="mt-2 flex flex-wrap gap-2" aria-label="Auto-Tags">@foreach($details->tags as $tag)<li class="rounded-full border border-blue-100 bg-white px-2.5 py-1 text-xs font-bold text-[#26488C]">{{ $tag }}</li>@endforeach</ul></div>
    @endif

    @if(count($details->context) > 0)
        <div class="mt-4"><h4 class="text-xs font-bold uppercase tracking-wide text-gray-500">Generated context</h4><dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">@foreach($details->context as $field)<div><dt class="text-xs font-semibold text-gray-500">{{ $field->label }}</dt><dd class="text-sm text-gray-800">{{ $field->value }}</dd></div>@endforeach</dl></div>
    @endif
</div>
