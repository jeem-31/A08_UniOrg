<!-- Skeleton Loader Component -->
<div class="animate-pulse">
    @if($type === 'table')
        <!-- Table Skeleton -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="h-8 bg-gray-200 rounded w-1/4 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-1/3"></div>
            </div>
            <div class="p-6">
                @for($i = 0; $i < 5; $i++)
                <div class="flex items-center space-x-4 mb-4">
                    <div class="h-12 w-12 bg-gray-200 rounded-full"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                    <div class="h-8 w-20 bg-gray-200 rounded"></div>
                </div>
                @endfor
            </div>
        </div>
    @elseif($type === 'card')
        <!-- Card Grid Skeleton -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @for($i = 0; $i < 6; $i++)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="h-40 bg-gray-200 rounded mb-4"></div>
                <div class="h-6 bg-gray-200 rounded w-3/4 mb-2"></div>
                <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
            </div>
            @endfor
        </div>
    @elseif($type === 'list')
        <!-- List Skeleton -->
        <div class="bg-white rounded-lg shadow">
            @for($i = 0; $i < 8; $i++)
            <div class="p-4 border-b border-gray-200 flex items-center space-x-4">
                <div class="h-10 w-10 bg-gray-200 rounded-full"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
            @endfor
        </div>
    @else
        <!-- Default Skeleton -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="h-8 bg-gray-200 rounded w-1/3 mb-4"></div>
            <div class="space-y-3">
                <div class="h-4 bg-gray-200 rounded"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                <div class="h-4 bg-gray-200 rounded w-4/6"></div>
            </div>
        </div>
    @endif
</div>

<style>
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>
