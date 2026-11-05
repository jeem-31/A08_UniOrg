@props(['state', 'tone' => 'empty', 'id' => null, 'shouldFocus' => false])

@php
    $isError = $tone === 'error';
    $stateId = $id ?? 'document-library-state-'.preg_replace('/[^a-z0-9_-]+/i', '-', $state->kind);
@endphp

<div
    id="{{ $stateId }}"
    class="min-w-0 scroll-mt-4 rounded-xl border {{ $isError ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white' }} p-4 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 sm:p-6"
    @if($isError) role="alert" @else role="status" @endif
    aria-labelledby="{{ $stateId }}-title"
    aria-describedby="{{ $stateId }}-message"
    @if($shouldFocus) tabindex="-1" autofocus @endif
    data-library-state="{{ $state->kind }}"
>
    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full {{ $isError ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}" aria-hidden="true">
        <i class="ph {{ $isError ? 'ph-warning' : 'ph-folder-open' }}"></i>
    </span>
    <h2 id="{{ $stateId }}-title" class="text-lg font-black text-gray-900">{{ $state->title }}</h2>
    <p id="{{ $stateId }}-message" class="mx-auto mt-1 max-w-2xl text-sm text-gray-700">{{ $state->message }}</p>

    @if(count($state->actions) > 0)
        <div class="mt-4 flex flex-col items-stretch justify-center gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            @foreach($state->actions as $action)
                <a href="{{ $action->url }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#26488C] px-4 py-2 text-sm font-bold text-white hover:bg-[#1a3461] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2">{{ $action->label }}</a>
            @endforeach
        </div>
    @endif
</div>
