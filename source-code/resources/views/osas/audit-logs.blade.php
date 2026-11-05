@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Audit Logs - OSAS')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold" style="color: #26488C;">
            <i class="ph ph-clock-counter-clockwise mr-2" style="color: #C4973B;"></i>
            Audit Logs
        </h1>
        <p class="text-gray-600 mt-2">Track system activities and administrative actions</p>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-6 border-b" style="background-color: rgba(38, 72, 140, 0.05);">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Recent Activities</h2>
                    <p class="text-sm text-gray-600 mt-1">Last 50 administrative actions</p>
                </div>
                <div class="text-sm text-gray-600">
                    <i class="ph ph-clock mr-1" style="color: #C4973B;"></i>
                    Last updated: {{ now()->format('M d, Y h:i A') }}
                </div>
            </div>
        </div>

        <div class="divide-y divide-gray-200">
            @forelse($recentActions as $action)
                <div class="p-6 hover:bg-gray-50 transition">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center
                                {{ $action['type'] === 'success' ? 'bg-green-100' : ($action['type'] === 'info' ? 'bg-blue-100' : 'bg-red-100') }}">
                                <i class="ph {{ $action['type'] === 'success' ? 'ph-check' : ($action['type'] === 'info' ? 'ph-calendar-blank' : 'ph-x') }} 
                                   {{ $action['type'] === 'success' ? 'text-green-600' : ($action['type'] === 'info' ? 'text-blue-600' : 'text-red-600') }}"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $action['action'] }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $action['description'] }}</p>
                                    <div class="flex items-center mt-2 text-xs text-gray-500">
                                        <i class="ph ph-user mr-1" style="color: #C4973B;"></i>
                                        <span class="font-medium">{{ $action['user'] }}</span>
                                        <span class="mx-2">•</span>
                                        <i class="ph ph-clock mr-1"></i>
                                        <span>{{ $action['timestamp']->format('M d, Y h:i A') }}</span>
                                        <span class="mx-2">•</span>
                                        <span>{{ $action['timestamp']->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    {{ $action['type'] === 'success' ? 'bg-green-100 text-green-800' : ($action['type'] === 'info' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $action['type'] === 'success' ? 'Approved' : ($action['type'] === 'info' ? 'Period Set' : 'Rejected') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-gray-500">
                    <i class="ph ph-clock-counter-clockwise text-5xl mb-4" style="color: #26488C; opacity: 0.3;"></i>
                    <p class="text-lg font-semibold">No audit logs yet</p>
                    <p class="text-sm mt-2">Administrative actions will appear here</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h3 class="text-sm font-bold text-gray-900 mb-3">Legend</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                    <i class="ph ph-check text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Approved Actions</p>
                    <p class="text-xs text-gray-600">System requests that were approved</p>
                </div>
            </div>
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center mr-3">
                    <i class="ph ph-x text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Rejected Actions</p>
                    <p class="text-xs text-gray-600">System requests that were rejected</p>
                </div>
            </div>
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                    <i class="ph ph-calendar-blank text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Period Changes</p>
                    <p class="text-xs text-gray-600">Accreditation school-year period updates</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
