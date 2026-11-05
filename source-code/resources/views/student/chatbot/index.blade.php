@extends('layouts.app')

@section('title', 'FAQ & Chatbot')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="student-chat-page min-h-screen">
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8" x-data="chatbotApp()">
    <header class="chat-hero">
        <div class="chat-hero__copy">
            <p class="chat-eyebrow">Help & support</p>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">FAQ & Help</h1>
            <p>Get answers to frequently asked questions</p>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
        <div class="lg:col-span-2 order-2 lg:order-1">
            <div id="student-chat-panel" class="chat-panel bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6 sm:mb-8">
                <!-- FAQ Mode Header -->
                <div x-cloak x-show="!chatMode">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-3 sm:mb-4">
                        <i style="color: var(--complementary-color);" class="ph ph-chats mr-2"></i>Ask {{ auth()->user()->organization->org_acronym ?? 'SOCCS' }} Chat
                    </h2>
                </div>
                
                <!-- Chat Mode Header -->
                <div x-cloak x-show="chatMode" style="background-color: #ffffff; border: 1px solid rgba(0, 0, 0, .08); color: #000000;" class="chat-mode-header px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-3 sm:mb-4 -mx-4 sm:-mx-6 -mt-4 sm:-mt-6">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            <i style="color: var(--complementary-color);" class="ph ph-chats text-xl sm:text-2xl mr-2 sm:mr-3 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <h2 class="text-sm sm:text-lg font-bold truncate">Chat with {{ auth()->user()->organization->org_acronym ?? 'SOCCS' }} Admin</h2>
                                <p class="text-xs sm:text-sm opacity-90 hidden sm:block">Get personalized assistance</p>
                            </div>
                        </div>
                        <button @click="chatMode = false; messages = []" style="color: #000000;" class="hover:opacity-80 transition text-xs sm:text-sm flex-shrink-0">
                            <i style="color: var(--complementary-color);" class="ph ph-arrow-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Back to FAQs</span><span class="sm:hidden">Back</span>
                        </button>
                    </div>
                </div>
                
                <!-- Chat Messages Area -->
                <div class="relative">
                    <div id="chat-messages" class="chat-messages-surface h-72 sm:h-96 overflow-y-auto overflow-x-hidden mb-3 sm:mb-4 p-3 sm:p-4 bg-gray-50 rounded-lg flex items-center justify-center">
                        <!-- FAQ Mode Welcome -->
                        <div x-cloak x-show="!chatMode" class="text-center text-gray-500 py-6 sm:py-8">
                            <i class="ph ph-chats text-5xl sm:text-6xl mb-3 sm:mb-4 text-gray-300"></i>
                            <p class="text-sm sm:text-base">Click on any FAQ to see the answer here!</p>
                        </div>
                        
                        <!-- Chat Mode Messages -->
                        <div x-cloak x-show="chatMode" class="w-full">
                            <template x-for="(message, index) in messages" :key="message.id">
                                <div class="mb-3 sm:mb-4" :class="message.sender_type === 'student' ? 'text-right' : ''">
                                    <div class="inline-block max-w-[85%] sm:max-w-md px-3 sm:px-4 py-2 sm:py-3 rounded-lg transition-all text-sm sm:text-base"
                                         :class="imageOnlyMessage(message) ? 'chat-bubble-image-only' : ''"
                                         :style="bubbleStyle(message)">
                                        <p class="font-medium break-words" x-text="message.message"></p>
                                        <div x-html="attachmentHtml(message)"></div>
                                    </div>
                                    <p class="text-xs mt-1 opacity-75" :class="message.sender_type === 'student' ? 'text-right mr-2' : 'ml-2'" x-cloak x-show="showMessageMeta(message, index)">
                                        <span x-cloak x-show="isPendingMessage(message)" class="italic">Sending...</span>
                                        <span x-cloak x-show="!isPendingMessage(message)" x-text="formatTime(message.created_at)"></span>
                                    </p>
                                </div>
                            </template>
                            
                            <div x-cloak x-show="messages.length === 0" class="text-center text-gray-500 py-6 sm:py-8">
                                <i class="ph ph-chats text-5xl sm:text-6xl mb-3 sm:mb-4 text-gray-300"></i>
                                <p class="text-sm sm:text-base">Start a conversation with SOCCS admin</p>
                            </div>
                        </div>
                    </div>
                    <button x-cloak x-show="showJumpLatest" @click="jumpToLatest()" type="button"
                            aria-label="Jump to latest messages" title="Jump to latest messages"
                            class="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 w-9 h-9 rounded-full hover:scale-105 active:scale-95 transition-all duration-200 flex items-center justify-center"
                            style="background-color: #ffffff; color: var(--primary-color); border: 1px solid rgba(0, 0, 0, .08);">
                        <i class="ph ph-arrow-down text-lg"></i>
                    </button>
                </div>
                
                <!-- FAQ Mode - Chat with Admin Button -->
                <div x-cloak x-show="!chatMode" class="chat-faq-cta border-2 rounded-lg p-3 sm:p-4 text-center" style="background-color: #ffffff; border-color: rgba(0, 0, 0, .08);">
                    <p class="font-semibold mb-2 sm:mb-3 text-sm sm:text-base" style="color: #000000;">Can't find what you're looking for?</p>
                    <button @click="startChat()" style="background-color: var(--primary-color); color: #ffffff; border: 1px solid var(--primary-color);" class="chat-start-btn inline-flex items-center px-4 sm:px-6 py-2.5 sm:py-3 hover:opacity-90 rounded-lg font-semibold transition shadow-lg hover:shadow-xl text-sm sm:text-base">
                        <i style="color: var(--complementary-color);" class="ph ph-chats mr-2"></i>
                        Chat with {{ auth()->user()->organization->org_acronym ?? 'SOCCS' }} Admin
                    </button>
                </div>
                
                <!-- Chat Mode - Input Form -->
                <form x-cloak x-show="chatMode" @submit.prevent="sendMessage" data-no-loading class="mt-2">
                    <!-- Attachment preview -->
                    <div class="flex justify-end mb-2" x-show="attachmentFile" x-cloak>
                        <div class="relative inline-flex items-center p-1.5 bg-white border border-gray-200 rounded-xl group">
                            <template x-if="attachmentFile && attachmentFile.type.indexOf('image/') === 0">
                                <img :src="attachmentPreviewUrl" class="w-14 h-14 object-cover rounded-lg border border-gray-100" alt="Attachment preview">
                            </template>
                            <template x-if="attachmentFile && attachmentFile.type.indexOf('image/') !== 0">
                                <span class="inline-flex items-center justify-center min-w-[48px] px-3 py-2 rounded-lg text-white text-[10px] font-black tracking-widest" :style="'background:' + fileTypeColor(attachmentFile.name) + ';'" x-text="fileTypeLabel(attachmentFile.name)"></span>
                            </template>
                            <button type="button" @click="clearAttachment()" class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-white border border-gray-200 shadow-sm text-gray-500 hover:text-red-500 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" title="Remove attachment"><i class="ph ph-x text-[10px]"></i></button>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <label class="chat-attach-btn cursor-pointer shrink-0 flex items-center justify-center" title="Attach a file">
                            <i class="ph ph-paperclip text-lg"></i>
                            <input type="file" id="studentAttachmentInput" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="hidden" @change="pickAttachment($event)">
                        </label>
                        <input type="text" x-model="newMessage" @input="handleTyping()" placeholder="Type your message..."
                            style="border-color: rgba(0, 0, 0, .08); color: #000000;" class="chat-input flex-1 px-3 sm:px-4 py-2 border rounded-lg focus:outline-none text-sm sm:text-base">
                        <button type="submit" :disabled="sending || (!newMessage.trim() && !attachmentFile)" style="background-color: var(--primary-color); color: #ffffff; border: 1px solid var(--primary-color);" class="chat-send-btn flex-shrink-0">
                            <i style="color: var(--complementary-color);" class="ph ph-paper-plane-tilt"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-1 order-1 lg:order-2">
            <div class="chat-panel chat-panel--delay bg-white rounded-lg shadow-lg p-4 sm:p-6" :class="{'opacity-50 pointer-events-none': chatMode}">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-3 sm:mb-4">
                    <i style="color: var(--complementary-color);" class="ph ph-star mr-2"></i>Popular FAQs
                </h2>
                <div class="space-y-2 sm:space-y-3 h-60 sm:h-80 overflow-y-auto pr-2">
                    @forelse($faqs as $faq)
                        <button 
                            class="faq-card w-full text-left p-3 border rounded-lg transition hover-faq-btn"
                            style="--faq-index: {{ $loop->index }}; border-color: rgba(0, 0, 0, .08); color: #000000;"
                            onclick='askFaqQuestion({{ json_encode($faq->question) }}, {{ json_encode($faq->answer) }})'>
                            <div class="font-semibold text-gray-900 text-sm">{{ $faq->question }}</div>
                            @if($faq->category)
                                <span class="inline-block mt-1 px-2 py-1 text-xs rounded-full 
                                    {{ $faq->category === 'general' ? 'bg-gray-100 text-gray-700' : '' }}
                                    {{ $faq->category === 'payment' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $faq->category === 'event' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $faq->category === 'membership' ? 'membership-badge' : '' }}
                                    {{ $faq->category === 'technical' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ucfirst($faq->category) }}
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="faq-empty text-center py-10 px-4">
                            <span class="faq-empty__icon"><i class="ph ph-chats" aria-hidden="true"></i></span>
                            <p class="faq-empty__title">No FAQs available yet</p>
                            <p class="faq-empty__text">Check back soon — or ask the admin directly using the chat button on the left.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="chat-help-card rounded-lg p-6 mt-6 border" style="background-color: #ffffff; border-color: rgba(0, 0, 0, .08);">
                <h3 class="font-bold mb-2" style="color: #000000;">
                    <i class="ph ph-info mr-2"></i>Need More Help?
                </h3>
                <p class="text-sm text-gray-700 mb-4">
                    If you can't find the answer you're looking for, feel free to contact us or submit a complaint/suggestion.
                </p>
                <a href="{{ route('complaints.index', ['create' => 'true']) }}" style="background-color: var(--primary-color); color: #ffffff; border: 1px solid var(--primary-color);" class="chat-help-btn block text-center px-4 py-2 rounded-lg hover:opacity-90 transition">
                    Contact Us
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<style>
    [x-cloak] {
        display: none !important;
    }

    /* Image-only messages render as a bare image with no bubble box */
    .chat-bubble-image-only {
        padding: 0 !important;
    }

    /* Hover effect for FAQ buttons */
    .hover-faq-btn:hover {
        background-color: rgba(var(--complementary-color-rgb), 0.1);
        border-color: var(--primary-color);
    }
    
    /* Membership badge styling */
    .membership-badge {
        background-color: rgba(var(--complementary-color-rgb), 0.15);
        color: var(--complementary-color);
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }
    
    @keyframes typing {
        0%, 100% { opacity: 0.3; }
        50% { opacity: 1; }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3) translateY(20px);
        }
        50% {
            opacity: 1;
            transform: scale(1.05) translateY(-5px);
        }
        70% {
            transform: scale(0.9) translateY(2px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    @keyframes slideInBounce {
        0% {
            opacity: 0;
            transform: translateX(50px) scale(0.8);
        }
        60% {
            opacity: 1;
            transform: translateX(-10px) scale(1.1);
        }
        80% {
            transform: translateX(5px) scale(0.95);
        }
        100% {
            transform: translateX(0) scale(1);
        }
    }
    
    .message-user {
        animation: slideInBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .message-bot {
        animation: bounceIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .chat-message-animate {
        animation: bounceIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    .no-animation {
        animation: none !important;
    }
    
    .typing-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 12px 16px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        animation: fadeIn 0.3s ease-out;
    }
    
    .typing-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }
    
    .typing-dot:nth-child(1) {
        background-color: var(--complementary-color);
    }
    
    .typing-dot:nth-child(2) {
        background-color: var(--complementary-color);
        animation-delay: 0.2s;
    }
    
    .typing-dot:nth-child(3) {
        background-color: var(--complementary-color);
        animation-delay: 0.4s;
    }

    /* Neumorphism */
    .student-chat-page {
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

    html:has(.student-chat-page),
    body:has(.student-chat-page),
    main:has(.student-chat-page) {
        background: var(--neu-bg) !important;
    }

    .student-chat-page .text-gray-900, .student-chat-page .text-gray-800,
    .student-chat-page .text-gray-700 { color: var(--neu-text) !important; }
    .student-chat-page .text-gray-600, .student-chat-page .text-gray-500 { color: var(--neu-muted) !important; }
    .student-chat-page .text-gray-400, .student-chat-page .text-gray-300 { color: var(--neu-faint) !important; }

    .student-chat-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

    .chat-hero { padding: 0 0 clamp(2rem, 4vw, 3rem); }
    .chat-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #7c3aed);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .chat-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
    .chat-hero__copy > p:last-child {
        max-width: 38rem;
        margin: .8rem 0 0;
        color: var(--neu-muted);
        font-size: clamp(.95rem, 2vw, 1.05rem);
        line-height: 1.55;
    }

    .chat-panel {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow) !important;
        animation: chat-panel-in .55s both cubic-bezier(.2, .8, .2, 1);
    }
    #student-chat-panel { scroll-margin-top: 4.5rem; }
    .chat-panel--delay { animation-delay: .1s; }
    .chat-panel h2 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }

    .chat-mode-header {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
    }
    .chat-mode-header h2 { color: var(--neu-text) !important; }
    .chat-mode-header button { font-weight: 600; }

    .chat-messages-surface {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: #E3E9F1 !important;
        box-shadow: inset 4px 4px 9px rgba(163, 177, 198, .5), inset -4px -4px 9px rgba(255, 255, 255, .9) !important;
    }
    #chat-messages .inline-block {
        border: 0 !important;
        border-radius: 1.1rem !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        box-shadow: 4px 4px 9px rgba(163, 177, 198, .5), -4px -4px 9px rgba(255, 255, 255, .9) !important;
    }
    #chat-messages .inline-block[style*="background-color: var(--primary-color)"] {
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85) !important;
    }
    #chat-messages > .text-center > i { color: rgba(var(--primary-color-rgb, 124, 58, 237), .30) !important; }

    .typing-indicator {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 4px 4px 9px rgba(163, 177, 198, .5), -4px -4px 9px rgba(255, 255, 255, .9) !important;
    }

    .chat-faq-cta {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
    }
    .chat-faq-cta p { color: var(--neu-text) !important; }

    .chat-start-btn {
        border-radius: 999px !important;
        border: 0 !important;
        background: var(--primary-color, #0071e3) !important;
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important;
    }
    .chat-start-btn:hover { transform: translateY(-2px); filter: brightness(1.04); }

    .chat-input {
        border: 0 !important;
        border-radius: 999px !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        padding-left: 1.15rem !important;
        padding-right: 1.15rem !important;
        box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
        transition: box-shadow .28s cubic-bezier(.2, .8, .2, 1);
    }

    .chat-attach-btn {
        width: 2.9rem !important;
        height: 2.9rem !important;
        border: 0 !important;
        border-radius: 50% !important;
        background: var(--neu-bg) !important;
        color: var(--neu-muted) !important;
        box-shadow: 5px 5px 12px rgba(163, 177, 198, .5), -5px -5px 12px rgba(255, 255, 255, .9) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s ease, color .28s ease !important;
    }
    .chat-attach-btn:hover {
        color: var(--primary-color, #0071e3) !important;
        box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
    }
    .chat-attach-btn:hover i {
        transform: scale(1.08);
    }
    .chat-attach-btn i {
        transition: transform .28s cubic-bezier(.2, .8, .2, 1);
    }
    .chat-input::placeholder { color: var(--neu-faint); }
    .chat-input:focus {
        outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
        outline-offset: 2px;
        box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
    }

    .chat-send-btn {
        width: 2.9rem !important;
        height: 2.9rem !important;
        border-radius: 50% !important;
        border: 0 !important;
        background: var(--primary-color, #0071e3) !important;
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important;
    }
    .chat-send-btn:hover { transform: translateY(-2px); filter: brightness(1.04); }

    .faq-card {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        box-shadow: 5px 5px 10px var(--neu-dark-shadow), -5px -5px 10px var(--neu-light-shadow) !important;
        transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1), background-color .3s ease;
        animation: faq-card-in .6s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--faq-index, 0) * 40ms);
    }
    .faq-card:hover {
        transform: translateY(-3px);
        background: var(--neu-bg) !important;
        box-shadow: 7px 7px 14px var(--neu-dark-shadow), -7px -7px 14px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
    }
    .faq-card .font-semibold { color: var(--neu-text) !important; }
    .student-chat-page .rounded-full {
        border: 0 !important;
        box-shadow: 3px 3px 6px rgba(163, 177, 198, .5), -3px -3px 6px rgba(255, 255, 255, .9) !important;
    }
    .student-chat-page .bg-gray-100 { background-color: #DDE4EE !important; }
    .student-chat-page .bg-green-100 { background-color: rgba(16, 185, 129, .13) !important; }
    .student-chat-page .bg-blue-100 { background-color: rgba(37, 99, 235, .13) !important; }
    .student-chat-page .bg-red-100 { background-color: rgba(220, 38, 38, .12) !important; }

    .faq-empty {
        border: 1px dashed rgba(var(--primary-color-rgb, 124, 58, 237), .3);
        border-radius: 1.25rem;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .03);
    }
    .faq-empty__icon {
        display: inline-flex;
        width: 3rem;
        height: 3rem;
        align-items: center;
        justify-content: center;
        margin-bottom: .9rem;
        border-radius: 50%;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .12);
        color: var(--primary-color, #0071e3);
        font-size: 1.5rem;
        box-shadow: 4px 4px 8px rgba(var(--primary-color-rgb, 124, 58, 237), .2), -4px -4px 8px rgba(255, 255, 255, .9);
    }
    .faq-empty__title {
        margin: 0;
        color: var(--neu-text);
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }
    .faq-empty__text {
        max-width: 16rem;
        margin: .4rem auto 0;
        color: var(--neu-muted);
        font-size: .8rem;
        line-height: 1.5;
    }

    .chat-help-card {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
    }
    .chat-help-card h3 { color: var(--neu-text) !important; }
    .chat-help-card h3 > i { color: var(--primary-color, #0071e3) !important; }
    .chat-help-card p { color: var(--neu-muted) !important; }

    .chat-help-btn {
        border-radius: .9rem !important;
        border: 0 !important;
        background: var(--primary-color, #0071e3) !important;
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important;
    }
    .chat-help-btn:hover { transform: translateY(-2px); filter: brightness(1.04); }

    .chat-start-btn:focus-visible, .chat-send-btn:focus-visible,
    .chat-help-btn:focus-visible, .chat-input:focus-visible, .faq-card:focus-visible,
    .chat-attach-btn:focus-visible {
        outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
        outline-offset: 3px;
    }

    @keyframes chat-panel-in { from { opacity: 0; transform: translateY(14px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes faq-card-in { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-chat-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-chat-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .chat-hero { padding-bottom: 2.25rem; }
        .chat-panel { border-radius: 1.25rem !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        .chat-panel, .faq-card { animation: none !important; }
        .faq-card, .chat-start-btn, .chat-send-btn, .chat-help-btn, .chat-attach-btn { transition-duration: .01ms !important; }
    }
</style>

<script>
    function chatbotApp() {
        return {
            chatMode: false,
            messages: [],
            newMessage: '',
            pollInterval: null,
            isLoadingMessages: false,
            isUserTyping: false,
            typingTimeout: null,
            attachmentFile: null,
            attachmentPreviewUrl: null,
            sending: false,
            showJumpLatest: false,
            
            init() {
                // Simple initialization
                console.log('Chat app initialized');
                this.setupRealtimeListener();
                this.setupLightbox();
                this.setupJumpLatest();
            },

            setupLightbox() {
                document.addEventListener('click', (e) => {
                    const trigger = e.target.closest('[data-lightbox]');
                    if (!trigger) return;
                    e.preventDefault();
                    const url = trigger.getAttribute('data-lightbox');
                    if (!url || url === '#') return;
                    const existing = document.getElementById('student-chat-lightbox');
                    if (existing) existing.remove();

                    const overlay = document.createElement('div');
                    overlay.id = 'student-chat-lightbox';
                    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:24px;';
                    overlay.innerHTML = `
                        <img src="${url}" alt="Attachment" style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;box-shadow:0 20px 60px rgba(0,0,0,.5);">
                    `;
                    const close = () => overlay.remove();
                    overlay.addEventListener('click', (ev) => { if (ev.target === overlay) close(); });
                    document.addEventListener('keydown', function onKey(e) {
                        if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
                    });
                    document.body.appendChild(overlay);
                });
            },
            
            setupRealtimeListener() {
                if (typeof window.ChatRealtime === 'undefined' || typeof window.ChatRealtime.onMessage !== 'function') {
                    // window.ChatRealtime may not exist yet if the app bundle is
                    // still loading; retry until it becomes available.
                    if (!window.__studentChatRealtimeRetry) {
                        window.__studentChatRealtimeRetry = true;
                        const retryTimer = setInterval(() => {
                            if (typeof window.ChatRealtime !== 'undefined' && typeof window.ChatRealtime.onMessage === 'function') {
                                clearInterval(retryTimer);
                                window.__studentChatRealtimeRetry = false;
                                this.setupRealtimeListener();
                            }
                        }, 250);
                        setTimeout(() => { clearInterval(retryTimer); window.__studentChatRealtimeRetry = false; }, 30000);
                    }
                    console.warn('ChatRealtime not available - chat updates will come from polling');
                    return;
                }
                window.ChatRealtime.onMessage((message) => {
                    if (!message) return;
                    
                    // Only handle messages for this student
                    if (String(message.user_id) !== String({{ auth()->id() }})) return;
                    
                    // Skip messages already shown
                    const alreadyExists = this.messages.some(m => String(m.id) === String(message.id));
                    if (alreadyExists) return;
                    
                    // Skip our own send while a temp (optimistic) copy is
                    // still pending - the POST response updates that copy
                    // in place, so pushing again would create a duplicate.
                    const hasPendingTwin = this.messages.some(m =>
                        m.id && String(m.id).startsWith('temp-') &&
                        m.sender_type === message.sender_type &&
                        m.message === message.message &&
                        Boolean(m.attachment_path) === Boolean(message.attachment_path)
                    );
                    if (hasPendingTwin) return;
                    
                    this.messages.push(message);
                    
                    this.$nextTick(() => {
                        const container = document.getElementById('chat-messages');
                        if (container) {
                            setTimeout(() => {
                                if (this.isNearBottom(container)) {
                                    container.scrollTop = container.scrollHeight;
                                }
                            }, 50);
                        }
                    });
                    
                    // Refresh badge when not actively viewing the chat
                    if (!this.chatMode && typeof window.updateBadgeCount === 'function') {
                        window.updateBadgeCount();
                    }
                });
            },
            
            async checkExistingMessages() {
                try {
                    const response = await fetch('{{ route("chatbot.messages") }}');
                    const data = await response.json();
                    if (data.messages && data.messages.length > 0) {
                        this.messages = data.messages;
                    }
                } catch (error) {
                    console.error('Error checking messages:', error);
                }
            },
            
            startChat() {
                console.log('Starting chat mode...');
                
                // Clear FAQ messages from the chat area (only non-Alpine elements)
                const chatMessagesDiv = document.getElementById('chat-messages');
                if (chatMessagesDiv) {
                    // Remove all direct children that are not managed by Alpine (FAQ messages)
                    const children = Array.from(chatMessagesDiv.children);
                    children.forEach(child => {
                        // Only remove elements that don't have x-show attribute (FAQ messages)
                        if (!child.hasAttribute('x-show') && !child.hasAttribute('x-if')) {
                            child.remove();
                        }
                    });
                }
                
                this.chatMode = true;
                
                // Mark messages as read when user clicks "Chat with Admin"
                this.markMessagesAsRead();
                
                this.loadMessages(true);
                
                // Start smart polling with faster updates
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                }
                this.pollInterval = setInterval(() => {
                    this.loadMessages();
                }, 500); // Poll every 500ms for faster message reception
            },
            
            async markMessagesAsRead() {
                try {
                    // Call a new endpoint to mark messages as read
                    await fetch('{{ route("chatbot.mark-read") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    
                    // Update badge immediately
                    if (window.updateBadgeCount) {
                        window.updateBadgeCount();
                    }
                } catch (error) {
                    console.error('Error marking messages as read:', error);
                }
            },
            
            handleTyping() {
                this.isUserTyping = true;
                
                // Clear existing timeout
                if (this.typingTimeout) {
                    clearTimeout(this.typingTimeout);
                }
                
                // Set user as not typing after 2 seconds of inactivity
                this.typingTimeout = setTimeout(() => {
                    this.isUserTyping = false;
                }, 2000);
            },
            
            async loadMessages(forceScrollToBottom = false) {
                if (!this.chatMode || this.isLoadingMessages) return;
                
                // Skip loading if user is actively typing to prevent interruptions
                if (this.isUserTyping) return;
                
                this.isLoadingMessages = true;
                
                try {
                    const response = await fetch('{{ route("chatbot.messages") }}');
                    const data = await response.json();
                    
                    const serverMessages = data.messages || [];
                    
                    // Merge instead of replace: never wipe messages already on
                    // screen (e.g. an optimistic copy whose server response
                    // hasn't arrived yet, or a stale response that raced with
                    // the send). Only add messages we don't have, and skip
                    // server copies that match a pending temp message.
                    const currentIds = new Set(this.messages.map(m => String(m.id)));
                    const newMessages = serverMessages.filter(m => {
                        if (currentIds.has(String(m.id))) return false;
                        return !this.messages.some(t =>
                            t.id && String(t.id).startsWith('temp-') &&
                            t.sender_type === m.sender_type &&
                            t.message === m.message &&
                            Boolean(t.attachment_path) === Boolean(m.attachment_path)
                        );
                    });
                    if (newMessages.length > 0) {
                        this.messages = [...this.messages, ...newMessages]
                            .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    }
                    
                    // Update badge count after loading messages
                    if (window.updateBadgeCount) {
                        window.updateBadgeCount();
                    }
                    
                    this.$nextTick(() => {
                        const container = document.getElementById('chat-messages');
                        if (container) {
                            // Scroll to the latest message when the chat is
                            // first opened; afterwards only auto-scroll when
                            // the user is already near the bottom, so reading
                            // older history is not interrupted by polling.
                            setTimeout(() => {
                                if (forceScrollToBottom || this.isNearBottom(container)) {
                                    container.scrollTop = container.scrollHeight;
                                }
                            }, 100);
                        }
                    });
                } catch (error) {
                    console.error('Error loading messages:', error);
                } finally {
                    this.isLoadingMessages = false;
                }
            },
            
            async sendMessage() {
                const messageText = this.newMessage.trim();
                const attachmentFile = this.attachmentFile;
                if ((!messageText && !attachmentFile) || this.sending) return;

                this.newMessage = ''; // Clear input immediately
                this.clearAttachment();

                this.sending = true;
                try {
                    // When text and an attachment are combined, they are sent
                    // as two separate messages so the attachment gets its own
                    // bubble (text first, then the attachment).
                    if (messageText && attachmentFile) {
                        await this.sendSingleMessage(messageText, null);
                        await this.sendSingleMessage('', attachmentFile);
                    } else {
                        await this.sendSingleMessage(messageText, attachmentFile);
                    }
                } finally {
                    this.sending = false;
                }
            },

            async sendSingleMessage(messageText, attachmentFile) {
                const hasFile = !!attachmentFile;

                // Capture a fresh object URL for the optimistic bubble BEFORE
                // the composer preview is cleared, so the image stays visible.
                const isImageFile = hasFile && attachmentFile.type.indexOf('image/') === 0;
                const tempImageUrl = isImageFile ? URL.createObjectURL(attachmentFile) : null;

                // Add message to UI immediately (optimistic update)
                const tempMessage = {
                    id: 'temp-' + Date.now(),
                    message: messageText,
                    sender_type: 'student',
                    created_at: new Date().toISOString(),
                    user_id: {{ auth()->id() }},
                    attachment_path: hasFile ? 'pending' : null,
                    attachment_url: tempImageUrl,
                    is_image: isImageFile
                };
                this.messages.push(tempMessage);

                // Scroll to bottom immediately
                this.$nextTick(() => {
                    const container = document.getElementById('chat-messages');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                });

                try {
                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    formData.append('message', messageText);
                    if (hasFile) {
                        formData.append('attachment', attachmentFile);
                    }

                    const response = await fetch('{{ route("chatbot.send") }}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    let data = null;
                    try {
                        data = await response.json();
                    } catch (parseError) {
                        data = null;
                    }

                    if (response.ok && data && data.success) {
                        // Update temp message properties without replacing the object
                        const tempIndex = this.messages.findIndex(m => m.id === tempMessage.id);
                        if (tempIndex !== -1) {
                            // Update properties in place to avoid re-rendering
                            Object.assign(this.messages[tempIndex], {
                                id: data.message.id,
                                created_at: data.message.created_at,
                                user_id: data.message.user_id,
                                attachment_path: data.message.attachment_path || null,
                                attachment_url: data.message.attachment_url || null,
                                attachment_name: data.message.attachment_name || null,
                                is_image: data.message.is_image || false,
                                file_type: data.message.file_type || null
                            });
                        }
                        if (tempImageUrl) URL.revokeObjectURL(tempImageUrl);
                    } else {
                        // Remove temp message on error
                        this.messages = this.messages.filter(m => m.id !== tempMessage.id);
                        // Only restore the typed text when the text send itself
                        // failed; on a split send the attachment failing must not
                        // bring back text that was already delivered.
                        if (messageText) {
                            this.newMessage = messageText;
                        }
                        if (tempImageUrl) URL.revokeObjectURL(tempImageUrl);
                        this.showToast(this.serverErrorMessage(data, response.status));
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    // Remove temp message on error
                    this.messages = this.messages.filter(m => m.id !== tempMessage.id);
                    if (messageText) {
                        this.newMessage = messageText;
                    }
                    if (tempImageUrl) URL.revokeObjectURL(tempImageUrl);
                    this.showToast('Failed to send message. Please try again. Check your connection and try again.');
                }
            },

            serverErrorMessage(data, status) {
                if (status === 419) return 'Your session has expired. Please refresh the page and try again.';
                if (status === 413) return 'File is too large for the server. Maximum size is 20MB.';
                if (data && typeof data === 'object') {
                    if (data.error) return data.error;
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        if (Array.isArray(first) && first.length) return first[0];
                        if (typeof first === 'string') return first;
                    }
                    if (data.message) return data.message;
                }
                return 'Failed to send message. Please try again.' + (status ? ' (HTTP ' + status + ')' : '');
            },

            isNearBottom(container) {
                if (!container) return true;
                return container.scrollHeight - container.scrollTop - container.clientHeight < 120;
            },

            isPendingMessage(message) {
                return !!(message && message.id && String(message.id).startsWith('temp-'));
            },

            imageOnlyMessage(message) {
                return !!(message && message.attachment_path && !(message.message || '').trim()
                    && (message.is_image || this.isImagePath(message.attachment_path)));
            },

            bubbleStyle(message) {
                if (this.imageOnlyMessage(message)) return 'background: transparent; border: none;';
                return message.sender_type === 'student'
                    ? 'background-color: var(--primary-color) !important; color: #ffffff !important; border: 1px solid var(--primary-color) !important;'
                    : 'background-color: #ffffff; color: #000000; border: 1px solid #e5e7eb;';
            },

            shouldShowTime(message, index) {
                if (this.isPendingMessage(message)) return false;
                const next = this.messages[index + 1];
                if (!next) return true;
                if (next.sender_type !== message.sender_type) return true;
                if (this.isPendingMessage(next)) return true;
                return this.formatTime(next.created_at) !== this.formatTime(message.created_at);
            },

            showMessageMeta(message, index) {
                return this.isPendingMessage(message) || this.shouldShowTime(message, index);
            },

            setupJumpLatest() {
                const container = document.getElementById('chat-messages');
                if (!container) return;
                container.addEventListener('scroll', () => {
                    this.showJumpLatest = !this.isNearBottom(container);
                });
            },

            jumpToLatest() {
                const container = document.getElementById('chat-messages');
                if (container) {
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }
            },
            
            formatTime(timestamp) {
                const date = new Date(timestamp);
                return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            },

            pickAttachment(event) {
                const file = event.target.files.length > 0 ? event.target.files[0] : null;
                if (!file) {
                    this.attachmentFile = null;
                    return;
                }
                if (file.type && file.type.indexOf('video/') === 0) {
                    event.target.value = '';
                    this.showToast('Video files are not allowed.');
                    this.attachmentFile = null;
                    return;
                }
                if (file.size > 20 * 1024 * 1024) {
                    event.target.value = '';
                    this.showToast('File is too large. Maximum size is 20MB.');
                    this.attachmentFile = null;
                    return;
                }
                if (this.attachmentPreviewUrl) URL.revokeObjectURL(this.attachmentPreviewUrl);
                this.attachmentPreviewUrl = file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : null;
                this.attachmentFile = file;
            },

            clearAttachment() {
                if (this.attachmentPreviewUrl) {
                    URL.revokeObjectURL(this.attachmentPreviewUrl);
                    this.attachmentPreviewUrl = null;
                }
                this.attachmentFile = null;
                const input = document.getElementById('studentAttachmentInput');
                if (input) input.value = '';
            },

            showToast(message, type = 'error') {
                const toast = document.createElement('div');
                const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
                const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
                const textColor = type === 'success' ? 'text-green-600' : 'text-red-600';
                const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
                const title = type === 'success' ? 'Success!' : 'Error!';

                toast.className = `fixed top-6 right-6 z-[100] bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md`;
                toast.innerHTML = `
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-full ${bgColor} flex items-center justify-center">
                            <i class="ph ${icon} text-xl ${textColor}"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">${title}</p>
                        <p class="text-sm text-gray-600">${message}</p>
                    </div>
                    <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4500);
            },

            pathExtension(path) {
                const parts = String(path || '').split('.');
                return parts.length > 1 ? parts.pop().toLowerCase() : '';
            },

            isImagePath(path) {
                return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(this.pathExtension(path)) !== -1;
            },

            fileTypeLabel(path) {
                const ext = this.pathExtension(path).toUpperCase();
                const labels = {
                    PDF: 'PDF', DOC: 'DOC', DOCX: 'DOCX', XLS: 'XLS', XLSX: 'XLSX', CSV: 'CSV',
                    PPT: 'PPT', PPTX: 'PPTX', TXT: 'TXT', ZIP: 'ZIP', RAR: 'RAR'
                };
                return labels[ext] || (ext || 'FILE');
            },

            fileTypeColor(path) {
                const ext = this.pathExtension(path);
                const colors = {
                    pdf: '#e74c3c', doc: '#2b579a', docx: '#2b579a', xls: '#217346', xlsx: '#217346', csv: '#217346',
                    ppt: '#d24726', pptx: '#d24726', txt: '#6b7280', zip: '#f59e0b', rar: '#f59e0b'
                };
                return colors[ext] || '#9ca3af';
            },

            escapeHtml(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            },

            attachmentHtml(msg) {
                if (!msg || !msg.attachment_path) return '';
                const isTemp = msg.id && String(msg.id).startsWith('temp-');
                const url = msg.attachment_url || (isTemp ? '#' : ('/chatbot/attachment/' + msg.id));

                if (msg.is_image || this.isImagePath(msg.attachment_path)) {
                    return `<a href="#" data-lightbox="${url}" class="block mt-2 max-w-[180px] rounded-xl overflow-hidden">
                        <img src="${url}" alt="Attachment" class="w-full h-auto block bg-white">
                    </a>`;
                }

                const label = this.fileTypeLabel(msg.attachment_path || msg.file_type);
                const color = this.fileTypeColor(msg.attachment_path || msg.file_type);
                const name = this.escapeHtml(msg.attachment_name || 'Attachment');
                return `<a href="${isTemp ? '#' : url}" ${isTemp ? '' : 'download'} class="inline-flex items-center gap-2 mt-2 px-3 py-2 rounded-xl bg-black/5 no-underline max-w-[220px]">
                    <span style="background:${color};color:#fff;font-weight:800;font-size:10px;padding:5px 8px;border-radius:6px;letter-spacing:.5px;flex-shrink:0;">${label}</span>
                    <span style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</span>
                </a>`;
            },
        }
    }
    
    // Function to ask FAQ question directly in chat with enhanced animation
    function askFaqQuestion(question, answer) {
        const messagesDiv = document.getElementById('chat-messages');
        
        // Remove flex centering when FAQ is clicked
        messagesDiv.classList.remove('flex', 'items-center', 'justify-center');
        
        // Hide Alpine.js welcome message by finding and hiding it
        const welcomeDiv = messagesDiv.querySelector('[x-show="!chatMode"]');
        if (welcomeDiv) {
            welcomeDiv.style.display = 'none';
        }
        
        // Clear only FAQ messages, not Alpine.js elements
        const children = Array.from(messagesDiv.children);
        children.forEach(child => {
            // Only remove elements that don't have x-show attribute (FAQ messages)
            if (!child.hasAttribute('x-show') && !child.hasAttribute('x-if')) {
                child.remove();
            }
        });
        
        // Add user question with bounce animation
        const userMessageDiv = document.createElement('div');
        userMessageDiv.className = 'mb-4 text-right message-user';
        userMessageDiv.innerHTML = `
            <div style="background-color: var(--primary-color) !important; color: #ffffff !important; border: 1px solid var(--primary-color) !important;" class="inline-block px-4 py-2 rounded-lg max-w-md shadow-lg">
                ${question}
            </div>
        `;
        messagesDiv.appendChild(userMessageDiv);
        
        // On mobile the FAQ list sits above the chat panel, so scroll down
        // to the chat so the clicked question (and its answer) is in view.
        scrollToChatOnMobile();
        
        // Smooth scroll after user message
        setTimeout(() => {
            messagesDiv.scrollTo({
                top: messagesDiv.scrollHeight,
                behavior: 'smooth'
            });
        }, 100);
        
        // Show typing indicator
        setTimeout(() => {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'mb-4 typing-container';
            typingDiv.innerHTML = `
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;
            messagesDiv.appendChild(typingDiv);
            
            messagesDiv.scrollTo({
                top: messagesDiv.scrollHeight,
                behavior: 'smooth'
            });
            
            // Remove typing indicator and show answer
            setTimeout(() => {
                typingDiv.remove();
                
                const botMessageDiv = document.createElement('div');
                botMessageDiv.className = 'mb-4 message-bot';
                botMessageDiv.innerHTML = `
                    <div style="background-color: #ffffff; color: #000000; border: 1px solid #e5e7eb;" class="inline-block px-4 py-2 rounded-lg max-w-md shadow-md">
                        ${answer}
                    </div>
                `;
                messagesDiv.appendChild(botMessageDiv);
                
                // Make sure the chat stays in view once the answer appears
                scrollToChatOnMobile();
                
                // Final smooth scroll
                messagesDiv.scrollTo({
                    top: messagesDiv.scrollHeight,
                    behavior: 'smooth'
                });
            }, 1000);
        }, 500);
    }

    // Only auto-scroll down to the chat panel on mobile (below lg breakpoint).
    // On desktop the chat sits right beside the FAQ list, so no scroll needed.
    function scrollToChatOnMobile() {
        if (window.matchMedia('(min-width: 1024px)').matches) return;
        const panel = document.getElementById('student-chat-panel');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
</script>
@endsection
