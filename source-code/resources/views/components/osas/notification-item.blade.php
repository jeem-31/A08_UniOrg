@props(['item', 'variant' => 'dropdown'])

@php
    /** @var \App\ViewModels\ResolvedNotificationItem $item */
    $variant = in_array($variant, ['dropdown', 'center'], true) ? $variant : 'dropdown';
    $isCenter = $variant === 'center';
@endphp

<a href="{{ $item->destination }}"
   data-osas-notification-item
   data-read-url="{{ $item->readUrl }}"
   data-read-state="{{ $item->isRead ? 'read' : 'unread' }}"
   @class([
       'group relative block transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-inset',
       'px-5 py-4 hover:bg-blue-50/40' => $isCenter,
       'p-5 border-b border-gray-50 hover:bg-gray-50' => ! $isCenter,
       'bg-blue-50/20' => ! $item->isRead,
       'bg-white' => $isCenter && $item->isRead,
       'opacity-70' => ! $isCenter && $item->isRead,
   ])>
    <div @class(['flex items-start', 'gap-4' => $isCenter, 'gap-3' => ! $isCenter])>
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg {{ $item->colorClass }}" aria-hidden="true">
            <i class="ph {{ $item->icon }}"></i>
        </span>

        <span class="min-w-0 flex-1">
            @if($isCenter)
                <span class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-xs font-black uppercase tracking-wide text-gray-900">{{ $item->title }}</span>
                    <time datetime="{{ $item->createdAt->toIso8601String() }}" class="whitespace-nowrap text-[9px] font-black uppercase tracking-widest text-gray-400">{{ $item->createdLabel }}</time>
                </span>
                <span class="mt-1 block text-xs leading-relaxed text-gray-600">{{ $item->message }}</span>
            @else
                <span class="block text-[10px] font-black uppercase tracking-wide text-gray-500">{{ $item->title }}</span>
                <span class="mt-1 block text-xs font-bold leading-tight text-gray-900">{{ $item->message }}</span>
                <time datetime="{{ $item->createdAt->toIso8601String() }}" class="mt-2 block text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $item->createdLabel }}</time>
            @endif
        </span>

        @unless($item->isRead)
            <span data-osas-notification-unread class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-red-500"><span class="sr-only">Unread</span></span>
        @endunless
    </div>
</a>
