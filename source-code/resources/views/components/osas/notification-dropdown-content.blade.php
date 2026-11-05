@props(['unreadCount' => 0, 'notifications' => null])

@php
    $notifications = $notifications ?? collect();
@endphp

<div class="p-6 border-b border-gray-50 flex justify-between items-center">
    <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest">Notifications</h3>
    @if($unreadCount > 0)
        <button type="button"
                data-osas-notification-mark-all
                data-read-url="{{ route('osas.notifications.mark-all-read') }}"
                class="text-[10px] font-black text-[#26488C] hover:underline uppercase tracking-tighter">
            Mark all as read
        </button>
    @endif
</div>

<div class="max-h-[400px] overflow-y-auto">
    @forelse($notifications as $notification)
        <x-osas.notification-item :item="$notification" variant="dropdown" />
    @empty
        <div class="p-10 text-center">
            <div class="w-16 h-16 bg-gray-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-bell-slash text-gray-200 text-2xl"></i>
            </div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">No notifications yet</p>
        </div>
    @endforelse
</div>

<div class="p-4 bg-gray-50 text-center border-t border-gray-100">
    <a href="{{ route('osas.notifications.index') }}" class="text-[10px] font-black text-gray-400 hover:text-[#26488C] uppercase tracking-widest transition-colors">View All Notifications</a>
</div>
