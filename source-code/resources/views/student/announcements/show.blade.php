@extends('layouts.app')

@section('title', $announcement->title)

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<style>
/* Custom Purple Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 3px;
}

/* Rich Text Content Styling */
.prose {
    max-width: none;
}
.prose a {
    color: var(--primary-color);
    text-decoration: underline;
    font-weight: 500;
    transition: color 0.2s;
}
.prose a:hover {
    color: #6B21A8;
}
.prose strong {
    font-weight: 700;
    color: #1F2937;
}
.prose ul, .prose ol {
    margin-left: 1.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}
.prose ul {
    list-style-type: disc;
}
.prose ol {
    list-style-type: decimal;
}
.prose li {
    margin-bottom: 0.25rem;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8);
}

/* Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--primary-color) #f1f1f1;
}

.student-announcement-detail-page {
    --neu-bg: #E9EDF3;
    --neu-text: #2E3A4C;
    --neu-muted: #5A6B80;
    --neu-faint: #7C8A9E;
    --neu-divider: rgba(163, 177, 198, .45);
    --neu-dark-shadow: rgba(163, 177, 198, .6);
    --neu-light-shadow: rgba(255, 255, 255, .85);
    min-height: calc(100vh + 4rem) !important;
    margin-top: -2rem;
    margin-bottom: -2rem;
    background: var(--neu-bg);
    color: var(--neu-text);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
}

.student-announcement-detail-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }
.announcement-detail-back {
    gap: .1rem;
    padding: .55rem .85rem;
    border: 0;
    border-radius: 999px;
    background: var(--neu-bg);
    box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow);
    font-weight: 600;
    transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s cubic-bezier(.2, .8, .2, 1), background-color .25s ease !important;
}

.announcement-detail-back:hover, .announcement-detail-back:focus-visible { box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow) !important; }
.announcement-detail-card {
    border: 0;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 12px 12px 26px var(--neu-dark-shadow), -12px -12px 26px var(--neu-light-shadow) !important;
    animation: announcement-detail-in .55s both cubic-bezier(.2, .8, .2, 1);
}

.announcement-detail-media { background: #DEE4EE; box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .4), inset -5px -5px 12px rgba(255, 255, 255, .75); }
.announcement-detail-media > div[x-show] { background: #DEE4EE; }
.announcement-detail-media img { padding: 1.25rem; }
.announcement-detail-placeholder { background: rgba(var(--primary-color-rgb, 0, 113, 227), .10) !important; box-shadow: inset 5px 5px 12px rgba(var(--primary-color-rgb, 0, 113, 227), .14), inset -5px -5px 12px rgba(255, 255, 255, .6) !important; }
.announcement-detail-placeholder > i { color: var(--primary-color, #0071e3) !important; opacity: .62; }
.announcement-detail-type { border-radius: 999px !important; box-shadow: 0 2px 8px rgba(163, 177, 198, .5) !important; }
.announcement-detail-type.bg-red-500 { background: rgba(255, 255, 255, .92) !important; color: #B91C1C !important; }
.announcement-detail-type.bg-blue-500 { background: rgba(255, 255, 255, .92) !important; color: #1D4ED8 !important; }
.announcement-detail-type.bg-green-500 { background: rgba(255, 255, 255, .92) !important; color: #15803D !important; }
.announcement-detail-type.bg-gray-700 { background: rgba(255, 255, 255, .92) !important; color: #475569 !important; }
.announcement-detail-media button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow) !important;
}

.announcement-detail-media button:hover { background: var(--neu-bg) !important; color: var(--primary-color, #0071e3) !important; box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow) !important; }
.announcement-detail-content { background: transparent; }
.announcement-detail-meta { color: var(--neu-muted) !important; }
.announcement-detail-meta i { color: var(--primary-color, #0071e3) !important; }
.announcement-detail-meta img.ring-1 { box-shadow: 0 0 0 1px rgba(163, 177, 198, .5) !important; }
.announcement-detail-meta span[class*="bg-gray-100"] { background: #DDE3EC !important; }
.announcement-detail-title {
    border-color: var(--neu-divider) !important;
    color: var(--neu-text) !important;
    letter-spacing: -.035em !important;
}

.announcement-detail-copy { color: var(--neu-muted) !important; }
.announcement-detail-copy .prose { color: inherit; }
.announcement-detail-copy .prose strong { color: var(--neu-text); }
.announcement-detail-copy .prose a { color: var(--primary-color, #0071e3); }
.announcement-detail-card:focus-within { box-shadow: 12px 12px 26px var(--neu-dark-shadow), -12px -12px 26px var(--neu-light-shadow), 0 0 0 3px rgba(var(--primary-color-rgb, 0, 113, 227), .25) !important; }
.announcement-detail-back:focus-visible, .announcement-detail-media button:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .35); outline-offset: 3px; }

@keyframes announcement-detail-in { from { opacity: 0; transform: translateY(12px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

@media (max-width: 640px) {
    .student-announcement-detail-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
    .student-announcement-detail-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
    .announcement-detail-card { border-radius: 1.25rem !important; }
    .announcement-detail-media { height: clamp(22rem, 88vw, 28rem) !important; }
    .announcement-detail-media img { padding: .35rem; }
}

@media (prefers-reduced-motion: reduce) {
    .announcement-detail-card { animation: none !important; }
    .announcement-detail-back { transition-duration: .01ms !important; }
}
</style>
@php
    // Determine where to go back based on the referer
    $referer = request()->header('referer');
    $backText = 'Back to Announcements';
    $backRoute = route('student.announcements.index');
    
    // Check if coming from dashboard/homepage
    if($referer && str_contains($referer, route('dashboard'))) {
        $backText = 'Back to Homepage';
        $backRoute = route('dashboard');
    }
@endphp

<div class="student-announcement-detail-page min-h-screen">
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <div class="mb-4 sm:mb-6">
        <a href="{{ $backRoute }}" data-student-navigation class="announcement-detail-back inline-flex items-center text-sm sm:text-base transition hover:opacity-80" style="color: var(--complementary-color);">
            <i class="ph ph-arrow-left mr-2"></i>{{ $backText }}
        </a>
    </div>

    <article class="announcement-detail-card bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:h-[600px]">
            <!-- Left Side - Image Carousel -->
            <div class="announcement-detail-media relative w-full overflow-hidden group h-64 sm:h-80 md:h-96 lg:h-full" x-data="{ currentImage: 0, images: {{ json_encode($announcement->images ?? []) }} }">
                @if($announcement->images && count($announcement->images) > 0)
                    <!-- Images -->
                    @foreach($announcement->images as $index => $image)
                        <div x-show="currentImage === {{ $index }}" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             class="w-full h-full">
                            <img src="{{ storage_url($image) }}" alt="{{ $announcement->title }}" class="w-full h-full object-contain">
                        </div>
                    @endforeach
                    
                    <!-- Navigation Buttons (only show if more than 1 image) -->
                    @if(count($announcement->images) > 1)
                        <!-- Previous Button - Always visible on mobile, hidden on desktop until hover -->
                        <button @click="currentImage = currentImage > 0 ? currentImage - 1 : images.length - 1" 
                                class="absolute left-2 sm:left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 sm:opacity-0 sm:group-hover:opacity-100 bg-black bg-opacity-30 sm:bg-transparent rounded-full">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        
                        <!-- Next Button -->
                        <button @click="currentImage = currentImage < images.length - 1 ? currentImage + 1 : 0" 
                                class="absolute right-2 sm:right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 sm:opacity-0 sm:group-hover:opacity-100 bg-black bg-opacity-30 sm:bg-transparent rounded-full">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                        
                        <!-- Image Counter -->
                        <div class="absolute bottom-3 sm:bottom-6 left-1/2 transform -translate-x-1/2 bg-opacity-80 text-white text-sm sm:text-base font-bold px-3 sm:px-4 py-1.5 sm:py-2 rounded-full shadow-lg sm:opacity-0 sm:group-hover:opacity-100 transition" style="background-color: var(--complementary-color);">
                            <span x-text="currentImage + 1"></span> / <span x-text="images.length"></span>
                        </div>
                    @endif
                @else
                    <div class="announcement-detail-placeholder w-full h-full flex items-center justify-center" style="background: linear-gradient(to bottom right, var(--primary-color), rgba(var(--primary-color-rgb, 130, 56, 179), 0.8));">
                        <i class="ph ph-megaphone text-white text-7xl sm:text-9xl opacity-50" style="color: var(--complementary-color);"></i>
                    </div>
                @endif
                
                <!-- Type Badge on Image -->
                <div class="absolute top-3 sm:top-6 left-3 sm:left-6">
                    <span class="announcement-detail-type px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-semibold rounded-full shadow-lg
                        {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                        {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                        {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                        {{ $announcement->type === 'general' ? 'bg-gray-700 text-white' : '' }}">
                        {{ ucfirst($announcement->type) }}
                    </span>
                </div>
            </div>

            <!-- Right Side - Content -->
            <div class="announcement-detail-content p-5 sm:p-8 lg:p-10 flex flex-col h-full lg:overflow-y-auto custom-scrollbar">
                <!-- Date, time, and poster -->
                <div class="announcement-detail-meta flex flex-wrap items-center gap-x-4 gap-y-2 text-xs sm:text-sm text-gray-500 mb-4 sm:mb-6">
                    <span class="inline-flex items-center">
                        <i class="ph ph-clock mr-2" style="color: var(--complementary-color);"></i>
                        <span>{{ $announcement->created_at->format('F d, Y h:i A') }}</span>
                    </span>
                    <span class="inline-flex items-center">
                        @if($announcement->user?->photo)
                            <img src="{{ storage_url($announcement->user->photo) }}" alt="{{ $announcement->user->announcement_display_name }}" class="mr-2 h-7 w-7 rounded-full object-cover ring-1 ring-gray-200">
                        @else
                            <span class="mr-2 flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                                {{ strtoupper(substr($announcement->user?->first_name ?? 'U', 0, 1)) }}
                            </span>
                        @endif
                        <span>{{ $announcement->user?->announcement_display_name ?? 'Unknown user' }}</span>
                    </span>
                </div>

                <!-- Title -->
                <h1 class="announcement-detail-title text-2xl sm:text-3xl md:text-4xl font-black tracking-tight text-gray-900 mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200 leading-tight break-words">
                    {{ $announcement->title }}
                </h1>

                <!-- Content -->
                <div class="announcement-detail-copy text-sm sm:text-base text-gray-700 leading-relaxed flex-1">
                    <div class="max-w-none prose prose-purple break-words" id="announcement-content">
                        {!! $announcement->content !!}
                    </div>
                </div>
            </div>
        </div>
    </article>
</div>
</div>

<script>
// Make all links in announcement content open in new tab
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('announcement-content');
    if (content) {
        const links = content.querySelectorAll('a');
        links.forEach(link => {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }
});
</script>

@endsection
