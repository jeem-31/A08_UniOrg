@extends('layouts.guest')

@section('title', $event->title)

@section('content')
<style>
.gold-icon {
    color: #D7AC28;
}

/* Custom Purple Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #8238B3;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3D0066;
}

/* Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #8238B3 #f1f1f1;
}
</style>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        @if(request()->get('from') === 'dashboard')
            <a href="{{ route('guest.dashboard') }}" class="inline-flex items-center transition" style="color: #8238B3;" onmouseover="this.style.color='#3D0066'" onmouseout="this.style.color='#8238B3'">
                <i class="ph ph-arrow-left mr-2 gold-icon"></i>Back to Home
            </a>
        @else
            <a href="{{ route('guest.events.index') }}" class="inline-flex items-center transition" style="color: #8238B3;" onmouseover="this.style.color='#3D0066'" onmouseout="this.style.color='#8238B3'">
                <i class="ph ph-arrow-left mr-2 gold-icon"></i>Back to Events
            </a>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 h-[600px]">
            <!-- Left Side - Image Carousel -->
            <div class="relative aspect-square w-full overflow-hidden group" x-data="{ currentImage: 0, images: {{ json_encode($event->images ?? []) }} }">
                @if($event->images && count($event->images) > 0)
                    <!-- Images -->
                    @foreach($event->images as $index => $image)
                        <div x-show="currentImage === {{ $index }}" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="w-full h-full">
                            <img src="{{ storage_url($image) }}" alt="{{ $event->title }}" class="w-full h-full object-contain">
                        </div>
                    @endforeach
                    
                    <!-- Navigation Buttons (only show if more than 1 image) -->
                    @if(count($event->images) > 1)
                        <!-- Previous Button - Hidden by default, shows on hover -->
                        <button @click="currentImage = currentImage > 0 ? currentImage - 1 : images.length - 1" 
                                class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                            <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        
                        <!-- Next Button - Hidden by default, shows on hover -->
                        <button @click="currentImage = currentImage < images.length - 1 ? currentImage + 1 : 0" 
                                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                            <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                        
                        <!-- Image Counter - Hidden by default, shows on hover with light gold background -->
                        <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-yellow-600 bg-opacity-80 text-white text-base font-bold px-4 py-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition">
                            <span x-text="currentImage + 1"></span> / <span x-text="images.length"></span>
                        </div>
                    @endif
                @else
                    <div class="w-full h-full flex items-center justify-center" style="background: linear-gradient(to bottom right, #8238B3, #3D0066);">
                        <i class="ph ph-calendar-blank text-white text-9xl opacity-50 gold-icon"></i>
                    </div>
                @endif
                
                <!-- Event Badge on Image -->
                <div class="absolute top-6 left-6">
                    <span class="px-4 py-2 text-sm font-semibold rounded-full shadow-lg bg-purple-600 text-white">
                        EVENT
                    </span>
                </div>
            </div>

            <!-- Right Side - Content -->
            <div class="p-8 lg:p-10 flex flex-col h-[600px] overflow-y-auto custom-scrollbar">
                <!-- Date and Time -->
                <div class="flex items-center text-sm text-gray-500 mb-6">
                    <i class="ph ph-clock mr-2 gold-icon"></i>
                    <span>{{ $event->start_date->format('F d, Y h:i A') }}</span>
                </div>

                <!-- Title -->
                <h1 class="text-4xl font-bold text-gray-900 mb-6 leading-tight">
                    {{ $event->title }}
                </h1>

                <!-- Event Info Grid -->
                <div class="mb-6 pb-6 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="flex items-center">
                            <div class="bg-purple-100 p-3 rounded-lg mr-3">
                                <i class="ph ph-calendar text-purple-600 text-xl" style="color: #8238B3;"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date</p>
                                <p class="font-semibold text-gray-900">{{ $event->start_date->format('M d, Y') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-lg mr-3">
                                <i class="ph ph-clock text-blue-600 text-xl" style="color: #8238B3;"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Time</p>
                                <p class="font-semibold text-gray-900">{{ $event->start_date->format('h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-lg mr-3">
                                <i class="ph ph-map-pin text-green-600 text-xl" style="color: #8238B3;"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Venue</p>
                                <p class="font-semibold text-gray-900">{{ $event->venue }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description/Content -->
                <div class="prose max-w-none text-gray-700 leading-relaxed flex-1">
                    <style>
                        .prose a {
                            color: #8238B3;
                            text-decoration: underline;
                        }
                        .prose a:hover {
                            color: #3D0066;
                        }
                    </style>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Event Details</h2>
                    <div id="event-content">{!! $event->description !!}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<script>
// Make all links in event content open in new tab
document.addEventListener('DOMContentLoaded', function() {
    const eventContent = document.getElementById('event-content');
    if (eventContent) {
        const links = eventContent.querySelectorAll('a');
        links.forEach(link => {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }
});
</script>
