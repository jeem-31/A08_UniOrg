@extends('layouts.app')

@section('title', $event->title)

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

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8);
}

/* Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--primary-color) #f1f1f1;
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

.student-event-detail-page {
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

.student-event-detail-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }
.event-detail-back {
    gap: .1rem;
    padding: .55rem .85rem;
    border: 0;
    border-radius: 999px;
    background: var(--neu-bg);
    box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow);
    font-weight: 600;
    transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s cubic-bezier(.2, .8, .2, 1), background-color .25s ease !important;
}

.event-detail-back:hover, .event-detail-back:focus-visible { box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow) !important; }
.event-detail-card {
    border: 0;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 12px 12px 26px var(--neu-dark-shadow), -12px -12px 26px var(--neu-light-shadow) !important;
    animation: event-detail-in .55s both cubic-bezier(.2, .8, .2, 1);
}

.event-detail-media { background: #DEE4EE; box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .4), inset -5px -5px 12px rgba(255, 255, 255, .75); }
.event-detail-media > div[x-show] { background: #DEE4EE; }
.event-detail-media img { padding: 1.25rem; }
.event-detail-placeholder { background: rgba(var(--primary-color-rgb, 0, 113, 227), .10) !important; box-shadow: inset 5px 5px 12px rgba(var(--primary-color-rgb, 0, 113, 227), .14), inset -5px -5px 12px rgba(255, 255, 255, .6) !important; }
.event-detail-placeholder > i { color: var(--primary-color, #0071e3) !important; opacity: .62; }
.event-detail-media button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow) !important;
}

.event-detail-media button:hover { background: var(--neu-bg) !important; color: var(--primary-color, #0071e3) !important; box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow) !important; }
.event-detail-content { background: transparent; }
.event-detail-meta { color: var(--neu-muted) !important; }
.event-detail-meta i { color: var(--primary-color, #0071e3) !important; }
.event-detail-title {
    border-color: var(--neu-divider) !important;
    color: var(--neu-text) !important;
    letter-spacing: -.035em !important;
}

.event-detail-info-label { color: var(--neu-muted) !important; }
.event-detail-info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .55rem .65rem !important;
    border-radius: .78rem !important;
    background: rgba(var(--primary-color-rgb, 0, 113, 227), .13) !important;
    color: var(--primary-color, #0071e3) !important;
    font-size: 1.05rem;
    box-shadow: 4px 4px 8px rgba(var(--primary-color-rgb, 0, 113, 227), .22), -4px -4px 8px rgba(255, 255, 255, .8);
}

.event-detail-copy { color: var(--neu-muted) !important; }
.event-detail-copy .prose { color: inherit; }
.event-detail-copy .prose strong { color: var(--neu-text); }
.event-detail-copy .prose a { color: var(--primary-color, #0071e3); }
.event-detail-copy h2 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }

.event-detail-register-btn {
    border-radius: .9rem !important;
    background: var(--primary-color, #0071e3) !important;
    box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
}
.event-detail-register-btn:hover { transform: translateY(-2px); filter: brightness(1.03); }

.event-detail-status { border: 0 !important; border-radius: .9rem !important; background: var(--neu-bg) !important; box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .45), inset -4px -4px 8px rgba(255, 255, 255, .75) !important; }
.event-detail-status .ph, .event-detail-status strong { color: var(--neu-text) !important; }
.event-detail-status > div > p:last-child { color: var(--neu-muted) !important; }
.event-detail-status--done .ph, .event-detail-status--done strong { color: var(--neu-muted) !important; }
.event-detail-status--registered { background: rgba(36, 138, 80, .10) !important; box-shadow: inset 4px 4px 8px rgba(36, 138, 80, .18), inset -4px -4px 8px rgba(255, 255, 255, .6) !important; }
.event-detail-status--registered .ph, .event-detail-status--registered strong { color: #248a50 !important; }
.event-detail-status--registered > div > p:last-child { color: #4f9a6f !important; }
.event-detail-status--pending { background: rgba(166, 106, 0, .10) !important; box-shadow: inset 4px 4px 8px rgba(166, 106, 0, .16), inset -4px -4px 8px rgba(255, 255, 255, .6) !important; }
.event-detail-status--pending .ph, .event-detail-status--pending strong { color: #a66a00 !important; }
.event-detail-status--pending > div > p:last-child { color: #c08a3a !important; }
.event-detail-status--unavailable { background: rgba(220, 38, 38, .08) !important; box-shadow: inset 4px 4px 8px rgba(220, 38, 38, .14), inset -4px -4px 8px rgba(255, 255, 255, .6) !important; }
.event-detail-status--unavailable .ph, .event-detail-status--unavailable strong { color: #B91C1C !important; }
.event-detail-status--unavailable > div > p:last-child { color: #C25B4E !important; }

.event-detail-card:focus-within { box-shadow: 12px 12px 26px var(--neu-dark-shadow), -12px -12px 26px var(--neu-light-shadow), 0 0 0 3px rgba(var(--primary-color-rgb, 0, 113, 227), .25) !important; }
.event-detail-back:focus-visible, .event-detail-media button:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .35); outline-offset: 3px; }

/* Registration modal */
.event-modal {
    --neu-bg: #E9EDF3;
    --neu-text: #2E3A4C;
    --neu-muted: #5A6B80;
    --neu-faint: #7C8A9E;
    --neu-divider: rgba(163, 177, 198, .45);
    --neu-dark-shadow: rgba(163, 177, 198, .6);
    --neu-light-shadow: rgba(255, 255, 255, .85);
    background: rgba(17, 17, 19, .46) !important;
    backdrop-filter: blur(4px);
}
.event-modal:not(.hidden) { animation: event-backdrop-in .28s ease-out both; }
.event-modal:not(.hidden) .event-modal__surface { animation: event-modal-in .42s cubic-bezier(.2, .8, .2, 1) both; }
.event-modal__surface { border: 0; border-radius: 1.5rem !important; background: #ffffff !important; box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important; }
#registerModal .bg-purple-100 { background: rgba(var(--primary-color-rgb, 0, 113, 227), .13) !important; box-shadow: 5px 5px 10px rgba(var(--primary-color-rgb, 0, 113, 227), .22), -5px -5px 10px rgba(255, 255, 255, .85) !important; }
#registerModal .bg-blue-50 { border: 0 !important; border-radius: 1rem !important; background: #E8F1FE !important; box-shadow: inset 3px 3px 8px rgba(37, 99, 235, .10), inset -3px -3px 8px rgba(255, 255, 255, .7) !important; }
#registerModal .bg-gray-50 { border-radius: 1rem !important; background: #DDE4EE !important; box-shadow: inset 3px 3px 8px rgba(163, 177, 198, .4), inset -3px -3px 8px rgba(255, 255, 255, .8) !important; }
#registerModal .bg-red-50 { border-radius: 1rem !important; }
#registerModal button.bg-gray-200 { border-radius: .9rem !important; background: var(--neu-bg) !important; color: var(--neu-text) !important; box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important; }
#registerModal button.bg-gray-200:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }
#registerModal #eventRegistrationSubmit { border-radius: .9rem !important; background: var(--primary-color, #0071e3) !important; box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
#registerModal button:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .35); outline-offset: 3px; }

@keyframes event-detail-in { from { opacity: 0; transform: translateY(12px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes event-backdrop-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes event-modal-in { from { opacity: 0; transform: translateY(18px) scale(.975); } to { opacity: 1; transform: translateY(0) scale(1); } }

@media (max-width: 640px) {
    .student-event-detail-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
    .student-event-detail-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
    .event-detail-card { border-radius: 1.25rem !important; }
    .event-detail-media { height: clamp(22rem, 88vw, 28rem) !important; }
    .event-detail-media img { padding: .35rem; }
    .event-modal__surface { border-radius: 1.25rem !important; }
}

@media (prefers-reduced-motion: reduce) {
    .event-detail-card, .event-modal:not(.hidden), .event-modal:not(.hidden) .event-modal__surface { animation: none !important; }
    .event-detail-back, .event-detail-register-btn { transition-duration: .01ms !important; }
}
</style>
@php
    // Determine where to go back based on the referer
    $referer = request()->header('referer');
    $backText = 'Back to Events';
    $backRoute = route('events.index');
    
    // Check if coming from dashboard/homepage
    if($referer && str_contains($referer, route('dashboard'))) {
        $backText = 'Back to Homepage';
        $backRoute = route('dashboard');
    }
@endphp

<div class="student-event-detail-page min-h-screen">
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <div class="mb-4 sm:mb-6">
        <a href="{{ $backRoute }}" data-student-navigation class="event-detail-back inline-flex items-center text-sm sm:text-base transition hover:opacity-80" style="color: var(--complementary-color);">
            <i class="ph ph-arrow-left mr-2"></i>{{ $backText }}
        </a>
    </div>

    <article class="event-detail-card bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:h-[600px]">
            <!-- Left Side - Image Carousel -->
            <div class="event-detail-media relative w-full overflow-hidden group h-64 sm:h-80 md:h-96 lg:h-full" x-data="{ currentImage: 0, images: {{ json_encode($event->images ?? []) }} }">
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
                        <!-- Previous Button -->
                        <button @click="currentImage = currentImage > 0 ? currentImage - 1 : images.length - 1" 
                                class="absolute left-2 sm:left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 sm:opacity-0 sm:group-hover:opacity-100 rounded-full">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        
                        <!-- Next Button -->
                        <button @click="currentImage = currentImage < images.length - 1 ? currentImage + 1 : 0" 
                                class="absolute right-2 sm:right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 sm:opacity-0 sm:group-hover:opacity-100 rounded-full">
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
                    <div class="event-detail-placeholder w-full h-full flex items-center justify-center">
                        <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                    </div>
                @endif
            </div>

            <!-- Right Side - Content -->
            <div class="event-detail-content p-5 sm:p-8 lg:p-10 flex flex-col h-full lg:overflow-y-auto custom-scrollbar">
                <!-- Date and Time -->
                <div class="event-detail-meta flex items-center text-xs sm:text-sm text-gray-500 mb-4 sm:mb-6">
                    <i class="ph ph-clock mr-2"></i>
                    <span>{{ $event->start_date->format('F d, Y h:i A') }}</span>
                </div>

                <!-- Title -->
                <h1 class="event-detail-title text-2xl sm:text-3xl md:text-4xl font-black tracking-tight text-gray-900 mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200 leading-tight break-words">
                    {{ $event->title }}
                </h1>

                <!-- Event Info Grid -->
                <div class="mb-5 sm:mb-6 pb-5 sm:pb-6 border-b border-gray-200" style="border-color: rgba(163, 177, 198, .45) !important;">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-3 sm:mb-4">
                        <div class="flex items-center">
                            <div class="event-detail-info-icon mr-3 flex-shrink-0">
                                <i class="ph ph-calendar"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="event-detail-info-label text-xs sm:text-sm">Date</p>
                                <p class="font-semibold text-gray-900 text-sm sm:text-base truncate">{{ $event->start_date->format('M d, Y') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <div class="event-detail-info-icon mr-3 flex-shrink-0">
                                <i class="ph ph-clock"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="event-detail-info-label text-xs sm:text-sm">Time</p>
                                <p class="font-semibold text-gray-900 text-sm sm:text-base truncate">{{ $event->start_date->format('h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 sm:pt-4 border-t border-gray-200" style="border-color: rgba(163, 177, 198, .45) !important;">
                        <div class="flex items-center">
                            <div class="event-detail-info-icon mr-3 flex-shrink-0">
                                <i class="ph ph-map-pin"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="event-detail-info-label text-xs sm:text-sm">Venue</p>
                                <p class="font-semibold text-gray-900 text-sm sm:text-base break-words">{{ $event->venue }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description/Content -->
                <div class="event-detail-copy prose max-w-none text-sm sm:text-base text-gray-700 leading-relaxed flex-1 break-words">
                    <h2 class="text-lg sm:text-xl md:text-2xl font-black tracking-tight text-gray-900 mb-3 sm:mb-4">Event Details</h2>
                    <div id="event-content">{!! $event->description !!}</div>
                </div>

                <!-- Register Button -->
                @php
                    $isRegistered = $event->registrations()->where('user_id', auth()->id())->exists();
                    $isEventEnded = $event->end_date->isPast();
                    $membershipState = $membershipEligibility['status'] ?? 'unavailable';
                    $membershipFee = $membershipEligibility['fee'] ?? null;
                    $membershipPaymentsUrl = $membershipEligibility['payments_url'] ?? route('payments.index');
                @endphp
                
                <div class="mt-6 sm:mt-8 pt-4 sm:pt-6 border-t border-gray-200" style="border-color: rgba(163, 177, 198, .45) !important;">
                    @if($isEventEnded)
                        <div class="event-detail-status event-detail-status--done border rounded-lg p-3 sm:p-4 flex items-center">
                            <i class="ph ph-flag-checkered text-xl sm:text-2xl mr-3"></i>
                            <div>
                                <p class="font-semibold text-sm sm:text-base">Event Completed</p>
                                <p class="text-xs sm:text-sm">This event has already ended.</p>
                            </div>
                        </div>
                    @elseif($isRegistered)
                        <div class="event-detail-status event-detail-status--registered border rounded-lg p-3 sm:p-4 flex items-center">
                            <i class="ph ph-check-circle text-xl sm:text-2xl mr-3"></i>
                            <div>
                                <p class="font-semibold text-sm sm:text-base">You're registered!</p>
                                <p class="text-xs sm:text-sm">You have successfully registered for this event.</p>
                            </div>
                        </div>
                    @elseif($membershipState === 'paid')
                        <button onclick="openRegisterModal()" class="event-detail-register-btn w-full text-white font-bold py-3 sm:py-4 px-4 sm:px-6 flex items-center justify-center text-sm sm:text-base">
                            <i style="color: var(--complementary-color);" class="ph ph-user-plus mr-2 sm:mr-3"></i>
                            Register for this Event
                        </button>
                    @elseif($membershipState === 'pending')
                        <div class="event-detail-status event-detail-status--pending border rounded-lg p-3 sm:p-4 flex items-center">
                            <i class="ph ph-clock text-xl sm:text-2xl mr-3"></i>
                            <div>
                                <p class="font-semibold text-sm sm:text-base">Membership Fee verification pending</p>
                                <p class="text-xs sm:text-sm">You can register after your Membership Fee payment is approved.</p>
                            </div>
                        </div>
                    @elseif(in_array($membershipState, ['unconfigured', 'unavailable'], true))
                        <div class="event-detail-status event-detail-status--unavailable border rounded-lg p-3 sm:p-4 flex items-center">
                            <i class="ph ph-warning-circle text-xl sm:text-2xl mr-3"></i>
                            <div>
                                <p class="font-semibold text-sm sm:text-base">Membership Fee payment is unavailable</p>
                                <p class="text-xs sm:text-sm">Please contact your Mother Organization officer for payment instructions.</p>
                            </div>
                        </div>
                    @else
                        <a href="{{ $membershipPaymentsUrl }}" data-student-navigation class="event-detail-register-btn w-full text-white font-bold py-3 sm:py-4 px-4 sm:px-6 flex items-center justify-center text-sm sm:text-base">
                            <i class="ph ph-credit-card mr-2 sm:mr-3"></i>
                            {{ $membershipState === 'rejected' ? 'Resubmit Membership Fee' : 'Pay Membership Fee' }}@if($membershipFee) (₱{{ number_format($membershipFee->amount, 2) }})@endif
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </article>
</div>
</div>

<!-- Registration Confirmation Modal -->
<div id="registerModal" class="event-modal hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="event-modal__surface bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all" onclick="event.stopPropagation()">
        <div class="p-5 sm:p-8">
            <!-- Icon -->
            <div class="flex justify-center mb-4 sm:mb-6">
                <div class="bg-purple-100 rounded-full p-3 sm:p-4">
                    <i style="color: var(--primary-color);" class="ph ph-user-check text-3xl sm:text-4xl"></i>
                </div>
            </div>
            
            <!-- Title -->
            <h3 class="text-xl sm:text-2xl font-black tracking-tight text-gray-900 text-center mb-3 sm:mb-4">Confirm Registration</h3>
            
            <!-- Message -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                <p class="text-sm sm:text-base text-gray-700 text-center mb-3 sm:mb-4">Are you sure you want to register for this event?</p>
                <div class="text-xs sm:text-sm text-gray-600">
                    <p class="flex items-start">
                        <i class="ph ph-info text-blue-500 mt-1 mr-2 flex-shrink-0"></i>
                        <span>Your student information (name and student ID) will be shared with the event organizer.</span>
                    </p>
                </div>
            </div>
            
            <!-- Student Info Display -->
            <div class="bg-gray-50 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6 space-y-2 sm:space-y-3">
                <div class="flex items-center text-sm sm:text-base">
                    <i style="color: var(--primary-color);" class="ph ph-user mr-3 flex-shrink-0"></i>
                    <span class="text-gray-900 font-semibold truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                </div>
                <div class="flex items-center text-sm sm:text-base">
                    <i style="color: var(--primary-color);" class="ph ph-identification-card mr-3 flex-shrink-0"></i>
                    <span class="text-gray-900 font-semibold truncate">{{ auth()->user()->student_id }}</span>
                </div>
            </div>

            <div id="registrationFeedback" class="hidden mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert" aria-live="polite">
                <p id="registrationFeedbackMessage" class="font-semibold"></p>
                <a id="registrationPaymentsLink" href="{{ $membershipPaymentsUrl }}" data-student-navigation class="hidden mt-2 inline-flex items-center font-semibold text-red-900 underline underline-offset-2">
                    Go to Payments <i class="ph ph-arrow-right ml-1"></i>
                </a>
            </div>
            
            <!-- Buttons -->
            <div class="flex gap-2 sm:gap-3">
                <button onclick="closeRegisterModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 sm:py-3 px-4 sm:px-6 rounded-lg transition duration-300 text-sm sm:text-base">
                    Cancel
                </button>
                <form id="eventRegistrationForm" action="{{ route('events.register', $event) }}" method="POST" class="flex-1">
                    @csrf
                    <button id="eventRegistrationSubmit" type="submit" class="w-full text-white font-semibold py-2.5 sm:py-3 px-4 sm:px-6 flex items-center justify-center text-sm sm:text-base">
                        <i id="eventRegistrationIcon" style="color: var(--complementary-color);" class="ph ph-check-circle mr-2"></i>
                        <span id="eventRegistrationText">Confirm</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Toast Messages -->
@if(session('success'))
<div x-data="{ show: true }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-x-8"
     x-transition:enter-end="opacity-100 transform translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-x-0"
     x-transition:leave-end="opacity-0 transform translate-x-8"
     x-init="setTimeout(() => show = false, 4000)"
     class="fixed top-4 sm:top-6 right-4 sm:right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-4 sm:px-6 py-3 sm:py-4 flex items-center space-x-3 max-w-[calc(100vw-2rem)] sm:max-w-md">
    <div class="flex-shrink-0">
        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
            <i class="ph ph-check-circle text-xl text-green-600"></i>
        </div>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Success!</p>
        <p class="text-xs sm:text-sm text-gray-600 break-words">{{ session('success') }}</p>
    </div>
    <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
        <i class="ph ph-x text-lg"></i>
    </button>
</div>
@endif

@if(session('error'))
<div x-data="{ show: true }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-x-8"
     x-transition:enter-end="opacity-100 transform translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-x-0"
     x-transition:leave-end="opacity-0 transform translate-x-8"
     x-init="setTimeout(() => show = false, 4000)"
     class="fixed top-4 sm:top-6 right-4 sm:right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-4 sm:px-6 py-3 sm:py-4 flex items-center space-x-3 max-w-[calc(100vw-2rem)] sm:max-w-md">
    <div class="flex-shrink-0">
        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
            <i class="ph ph-warning-circle text-xl text-red-600"></i>
        </div>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Error!</p>
        <p class="text-xs sm:text-sm text-gray-600 break-words">{{ session('error') }}</p>
    </div>
    <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
        <i class="ph ph-x text-lg"></i>
    </button>
</div>
@endif

<script>
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.add('hidden');
}

function setRegistrationFeedback(message, paymentsUrl = null) {
    const feedback = document.getElementById('registrationFeedback');
    const messageNode = document.getElementById('registrationFeedbackMessage');
    const paymentsLink = document.getElementById('registrationPaymentsLink');
    if (!feedback || !messageNode || !paymentsLink) return;

    messageNode.textContent = message;
    if (paymentsUrl) {
        paymentsLink.href = paymentsUrl;
        paymentsLink.classList.remove('hidden');
    } else {
        paymentsLink.classList.add('hidden');
    }
    feedback.classList.remove('hidden');
}

function clearRegistrationFeedback() {
    document.getElementById('registrationFeedback')?.classList.add('hidden');
}

function bindEventRegistrationForm() {
    const form = document.getElementById('eventRegistrationForm');
    if (!form || form.dataset.bound === 'true') return;
    form.dataset.bound = 'true';

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = document.getElementById('eventRegistrationSubmit');
        const icon = document.getElementById('eventRegistrationIcon');
        const text = document.getElementById('eventRegistrationText');
        if (!submit || submit.disabled) return;

        clearRegistrationFeedback();
        submit.disabled = true;
        submit.classList.add('opacity-50', 'cursor-not-allowed');
        icon.className = 'ph ph-spinner ph-spin mr-2';
        text.textContent = 'Processing...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload.success) {
                setRegistrationFeedback(payload.message || 'Unable to register for this event. Please try again.', payload.payments_url || null);
                return;
            }

            closeRegisterModal();
            if (typeof window.refreshCurrentStudentPage === 'function') {
                window.refreshCurrentStudentPage().catch(() => {
                    openRegisterModal();
                    setRegistrationFeedback('You are registered. Refresh this event page to view the updated status.');
                });
            }
        } catch (error) {
            console.error('Event registration failed:', error);
            setRegistrationFeedback('Unable to reach the server. Please check your connection and try again.');
        } finally {
            submit.disabled = false;
            submit.classList.remove('opacity-50', 'cursor-not-allowed');
            icon.className = 'ph ph-check-circle mr-2';
            text.textContent = 'Confirm';
        }
    });
}

// Close modal when clicking outside
document.getElementById('registerModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRegisterModal();
    }
});

bindEventRegistrationForm();

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
@endsection
