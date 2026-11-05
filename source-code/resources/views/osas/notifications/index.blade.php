@extends('layouts.osas')

@section('title', 'Notifications')

@section('content')
<div class="container-fluid px-6 py-5">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-5">
        <div>
            <nav class="flex text-gray-400 text-[10px] mb-1 uppercase tracking-widest font-black" aria-label="Breadcrumb">
                <a href="{{ route('osas.dashboard') }}" class="hover:text-[#26488C] transition-colors">OSAS</a>
                <i class="ph ph-caret-right text-[8px] mx-2 text-gray-300 mt-0.5"></i>
                <span class="text-gray-300">Notifications</span>
            </nav>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
                Notification <span class="text-[#26488C]">Center</span>
            </h1>
            <p class="text-gray-500 mt-1 text-xs font-medium">Review all OSAS updates and open the related record.</p>
        </div>

        @if($unreadCount > 0)
            <button type="button"
                    data-osas-notification-mark-all
                    data-read-url="{{ route('osas.notifications.mark-all-read') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white rounded-lg bg-[#26488C] hover:bg-[#1a3461] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#26488C] focus-visible:ring-offset-2 transition-colors shadow-sm">
                <i class="ph ph-checks" aria-hidden="true"></i> Mark All as Read
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center"><i class="ph ph-bell"></i></div>
            <div><p class="text-lg font-black text-gray-900 leading-none">{{ $totalCount }}</p><p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">All Notifications</p></div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center"><i class="ph ph-envelope"></i></div>
            <div><p data-osas-notification-unread-count class="text-lg font-black text-gray-900 leading-none">{{ $unreadCount }}</p><p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Unread</p></div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center"><i class="ph ph-envelope-open"></i></div>
            <div><p class="text-lg font-black text-gray-900 leading-none">{{ max(0, $totalCount - $unreadCount) }}</p><p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Read</p></div>
        </div>
    </div>
    <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/40 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-black text-gray-800 uppercase tracking-widest">Recent Notifications</h2>
                <p class="text-[10px] text-gray-400 mt-0.5">Click an item to open its related page.</p>
            </div>
            @if($unreadCount > 0)
                <span data-osas-notification-unread-badge class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 text-red-600 text-[9px] font-black uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span><span data-osas-notification-unread-count>{{ $unreadCount }}</span> New
                </span>
            @endif
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($notifications as $notification)
                <x-osas.notification-item :item="$notification" variant="center" />
            @empty
                <div class="px-8 py-16 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ph ph-bell-slash text-2xl text-gray-200"></i>
                    </div>
                    <h3 class="text-sm font-black text-gray-900">No notifications yet</h3>
                    <p class="text-xs text-gray-500 mt-1">New accreditation, event, and chat updates will appear here.</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="px-5 py-3 bg-gray-50/30 border-t border-gray-100">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
