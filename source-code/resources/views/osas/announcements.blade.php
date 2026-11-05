@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'System Announcements - OSAS')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold" style="color: #26488C;">
            <i class="ph ph-megaphone mr-2" style="color: #C4973B;"></i>
            System Announcements
        </h1>
        <p class="text-gray-600 mt-2">Broadcast important messages to all organizations</p>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-12 text-center">
        <div class="max-w-md mx-auto">
            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6"
                 style="background-color: rgba(38, 72, 140, 0.1);">
                <i class="ph ph-megaphone text-5xl" style="color: #26488C;"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">System Announcements</h2>
            <p class="text-gray-600 mb-6">
                This feature allows you to create and manage system-wide announcements that will be visible to all organizations.
            </p>
            <div class="p-6 rounded-lg mb-6" style="background-color: rgba(196, 151, 59, 0.1);">
                <p class="text-sm font-semibold" style="color: #C4973B;">
                    <i class="ph ph-info mr-2"></i>
                    Feature Coming Soon
                </p>
                <p class="text-sm text-gray-700 mt-2">
                    For now, you can use the floating chat widget (bottom-right) to send messages to organizations.
                </p>
            </div>
            <button type="button" onclick="window.openOsasChatPanel && window.openOsasChatPanel()"
               class="inline-block px-6 py-3 text-white rounded-lg hover:opacity-90 transition font-semibold"
               style="background-color: #26488C;">
                <i class="ph ph-chats mr-2"></i> Open OSAS Chat
            </button>
        </div>
    </div>
</div>
@endsection
