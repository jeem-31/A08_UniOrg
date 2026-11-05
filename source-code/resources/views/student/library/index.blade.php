@extends('layouts.app')

@section('title', 'e-Library')

@section('content')
<style>
    .library-page { color: #111827; }
    .library-surface { background: #fff; border: 1px solid #e5e7eb; border-radius: .875rem; box-shadow: 0 6px 18px rgba(15, 23, 42, .04); }
    .library-resource-card { border: 1px solid #e5e7eb; border-radius: .875rem; background: #fff; transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
    .library-resource-card:hover { transform: translateY(-2px); border-color: rgba(var(--primary-color-rgb), .38); box-shadow: 0 10px 22px rgba(15, 23, 42, .07); }
    .library-filter { border: 1px solid #e5e7eb; color: #64748b; transition: .2s ease; }
    .library-filter:hover, .library-filter.is-active { border-color: var(--primary-color); color: var(--primary-color); background: rgba(var(--primary-color-rgb), .06); }
    .library-widget-active { border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), .12); }
    /* Neumorphism */
    .student-library-page {
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

    html:has(.student-library-page),
    body:has(.student-library-page),
    main:has(.student-library-page) {
        background: var(--neu-bg) !important;
    }

    .student-library-page .text-gray-900, .student-library-page .text-gray-800,
    .student-library-page .text-gray-700 { color: var(--neu-text) !important; }
    .student-library-page .text-gray-600, .student-library-page .text-gray-500 { color: var(--neu-muted) !important; }
    .student-library-page .text-gray-400, .student-library-page .text-gray-300 { color: var(--neu-faint) !important; }
    .student-library-page .border-gray-200, .student-library-page .border-gray-300 { border-color: var(--neu-divider) !important; }

    .student-library-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

    .library-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
    }

    .library-hero__copy { max-width: 44rem; }
    .library-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #7c3aed);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .library-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
    .library-hero__copy > p:last-child {
        max-width: 38rem;
        margin: .8rem 0 0;
        color: var(--neu-muted);
        font-size: clamp(.95rem, 2vw, 1.05rem);
        line-height: 1.55;
    }

    .student-library-page .library-count {
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        padding: .42rem 1rem .42rem .5rem;
        border: 0;
        border-radius: 999px;
        background: var(--neu-bg);
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        color: var(--neu-muted);
        font-size: .8rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .student-library-page .library-count__icon {
        display: inline-flex;
        width: 2rem;
        height: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .10);
        color: var(--primary-color, #7c3aed);
        font-size: 1.05rem;
        box-shadow: inset 2px 2px 4px rgba(var(--primary-color-rgb, 124, 58, 237), .12), inset -2px -2px 4px rgba(255, 255, 255, .7) !important;
    }
    .library-count > span:last-child { display: flex; flex-direction: column; gap: .15rem; }
    .library-count > span:last-child span { color: var(--neu-text); font-size: 1.05rem; font-weight: 700; letter-spacing: -.02em; }

    .library-surface {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        transition: box-shadow .28s ease, transform .28s ease;
        animation: library-card-in .6s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--widget-index, 0) * 60ms);
    }
    .library-surface:hover {
        transform: translateY(-2px);
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
    }
    .library-widget-active {
        border-color: var(--primary-color) !important;
        box-shadow: inset 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .18), inset -4px -4px 10px rgba(255, 255, 255, .75) !important;
    }
    .library-surface .flex.h-9.w-9 { border-radius: .78rem !important; box-shadow: inset 2px 2px 4px rgba(163, 177, 198, .35), inset -2px -2px 4px rgba(255, 255, 255, .75); }
    .library-surface p.text-lg { color: var(--neu-text) !important; letter-spacing: -.02em; }
    .library-surface p.text-xs { color: var(--neu-muted) !important; }

    .student-library-page input.library-search {
        border: 0;
        border-radius: 999px;
        background: var(--neu-bg);
        color: var(--neu-text);
        box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
        transition: box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease;
    }
    .library-search::placeholder { color: var(--neu-faint); }
    .student-library-page input.library-search:focus {
        outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
        outline-offset: 2px;
        box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
    }

    .library-resource-card {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        transition: transform .32s cubic-bezier(.2, .8, .2, 1), box-shadow .32s cubic-bezier(.2, .8, .2, 1);
        animation: library-card-in .65s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--resource-index, 0) * 45ms);
    }
    .library-resource-card:hover {
        transform: translateY(-3px);
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
    }
    .library-resource-card .rounded-lg.p-2\.5 {
        border-radius: .78rem !important;
        box-shadow: inset 2px 2px 4px rgba(163, 177, 198, .35), inset -2px -2px 4px rgba(255, 255, 255, .75) !important;
    }
    .library-resource-card h3 { color: var(--neu-text) !important; letter-spacing: -.015em !important; }
    .library-resource-card .text-slate-400 { color: var(--neu-muted) !important; }
    .library-resource-card p.mb-4 { color: var(--neu-muted) !important; }

    .library-resource-card .bg-gray-100 {
        border-radius: .8rem !important;
        background: #DDE4EE !important;
        color: var(--neu-text) !important;
        box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease, box-shadow .28s ease;
    }
    .library-resource-card .bg-gray-100:hover {
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        transform: translateY(-1px);
    }
    .library-resource-card a[style*="background-color"] {
        border-radius: .8rem !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s ease, filter .28s ease;
    }
    .library-resource-card a[style*="background-color"]:hover { transform: translateY(-1px); filter: brightness(1.03); }
    .student-library-page .library-resource-card a.bg-blue-600 {
        background: var(--primary-color, #0071e3) !important;
        border-radius: .8rem !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
    }
    .student-library-page .library-resource-card a.bg-blue-600:hover { background: var(--primary-color, #0071e3) !important; filter: brightness(1.05); transform: translateY(-1px); }

    .student-library-page .library-empty.bg-white.rounded-lg {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8) !important;
    }
    .student-library-page .library-empty > i { color: rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important; }
    .library-empty p { color: var(--neu-muted) !important; }

    .library-modal {
        background: rgba(17, 17, 19, .46) !important;
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
        animation: library-backdrop-in .28s ease-out both;
    }
    .library-modal__backdrop { background: transparent !important; }
    .student-library-page .library-modal__surface.bg-white.rounded-lg {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: #ffffff !important;
        box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important;
        animation: library-modal-in .42s cubic-bezier(.2, .8, .2, 1) both;
    }
    .library-modal__surface h3 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }
    .library-modal__surface > .border-b { border-color: var(--neu-divider) !important; }
    .library-modal__surface .bg-gray-100 { background-color: #E3E9F1 !important; }
    .library-modal .bg-white.rounded-lg.shadow-lg {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: inset 4px 4px 10px var(--neu-dark-shadow), inset -4px -4px 10px var(--neu-light-shadow) !important;
    }
    .library-modal a[style*="background-color"] { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
    .library-modal a[style*="background-color"]:hover { transform: translateY(-2px); filter: brightness(1.03); }
    .library-modal button:focus-visible, .library-modal a:focus-visible,
    .library-search:focus-visible, .library-surface:focus-visible {
        outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
        outline-offset: 3px;
    }

    @keyframes library-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes library-backdrop-in { from { opacity: 0; } to { opacity: 1; } }
    @keyframes library-modal-in { from { opacity: 0; transform: translateY(18px) scale(.975); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-library-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-library-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .library-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
        .library-surface { border-radius: 1.15rem !important; }
        .library-resource-card { border-radius: 1.15rem !important; }
        .student-library-page .library-modal__surface.bg-white.rounded-lg { border-radius: 1.15rem !important; }

        .library-widget {
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .3rem;
            padding: .7rem .25rem !important;
            text-align: center;
        }
        .library-widget .flex.h-9.w-9 {
            width: 2.1rem !important;
            height: 2.1rem !important;
            border-radius: .65rem !important;
        }
        .library-widget .flex.h-9.w-9 i { font-size: 1.05rem; }
        .library-count { padding: .35rem .8rem .35rem .4rem; font-size: .72rem; }
        .library-count__icon { width: 1.7rem !important; height: 1.7rem !important; font-size: .9rem !important; }
        .library-count > span:last-child span { font-size: .95rem !important; }
        .library-widget p.text-xs {
            font-size: .56rem !important;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .library-widget p.text-lg {
            font-size: .95rem !important;
            line-height: 1.1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .library-surface, .library-resource-card, .library-modal, .library-modal__surface { animation: none !important; }
        .library-resource-card, .library-search, .library-modal a, .library-modal .bg-gray-100 { transition-duration: .01ms !important; }
    }
</style>

<div class="student-library-page student-neu-page min-h-screen">
<div class="library-page max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-5 sm:py-7" x-data="librarySearch()">
    <header class="library-hero">
        <div class="library-hero__copy">
            <p class="library-eyebrow"><i class="ph ph-book-open mr-1.5" style="color: var(--primary-color);"></i>Student resources</p>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">e-Library</h1>
            <p>Find the materials you need for class, projects, and review.</p>
        </div>
    </header>

    <div class="mb-5 grid grid-cols-4 gap-2 sm:gap-3">
        <button @click="setType('all')" :class="activeType === 'all' ? 'library-widget-active' : ''" style="--widget-index: 0;" class="library-widget library-surface flex items-center gap-3 px-4 py-3 text-left transition hover:-translate-y-0.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg" style="background: rgba(var(--primary-color-rgb), .09); color: var(--primary-color);"><i class="ph ph-squares-four"></i></span><div><p class="text-xs text-slate-500">All resources</p><p class="text-lg font-bold text-slate-900" x-text="allResources.length"></p></div></button>
        <button @click="setType('pdf')" :class="activeType === 'pdf' ? 'library-widget-active' : ''" style="--widget-index: 1;" class="library-widget library-surface flex items-center gap-3 px-4 py-3 text-left transition hover:-translate-y-0.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600"><i class="ph ph-file-pdf"></i></span><div><p class="text-xs text-slate-500">PDF guides</p><p class="text-lg font-bold text-slate-900" x-text="countByType('pdf')"></p></div></button>
        <button @click="setType('link')" :class="activeType === 'link' ? 'library-widget-active' : ''" style="--widget-index: 2;" class="library-widget library-surface flex items-center gap-3 px-4 py-3 text-left transition hover:-translate-y-0.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><i class="ph ph-link"></i></span><div><p class="text-xs text-slate-500">Web resources</p><p class="text-lg font-bold text-slate-900" x-text="countByType('link')"></p></div></button>
        <button @click="setType('presentation')" :class="activeType === 'presentation' ? 'library-widget-active' : ''" style="--widget-index: 3;" class="library-widget library-surface flex items-center gap-3 px-4 py-3 text-left transition hover:-translate-y-0.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600"><i class="ph ph-file-ppt"></i></span><div><p class="text-xs text-slate-500">Presentations</p><p class="text-lg font-bold text-slate-900" x-text="countByType('presentation')"></p></div></button>
    </div>

    <div class="mb-6 flex items-center gap-3">
        <div class="library-surface flex-1 min-w-0 p-4 sm:p-5">
            <div class="relative min-w-0">
                <input type="text" x-model="searchQuery" @input="filterResources()" placeholder="Search resources..."
                    class="library-search w-full rounded-lg border px-4 py-2.5 pr-10 text-sm focus:outline-none">
                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                    <i style="color: var(--complementary-color);" class="ph ph-magnifying-glass"></i>
                </div>
            </div>
        </div>
        <div class="library-count shrink-0 sm:self-center"><span class="library-count__icon"><i class="ph ph-squares-four" aria-hidden="true"></i></span><span><span class="block text-lg leading-none text-slate-900" x-text="allResources.length"></span>resources available</span></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <template x-for="(resource, i) in filteredResources" :key="resource.id">
            <div class="library-resource-card flex flex-col p-4 sm:p-5" :style="{ '--resource-index': i }">
                <div class="flex items-center mb-4">
                    <div class="mr-3 flex-shrink-0 rounded-lg p-2.5"
                        :class="{
                            'bg-red-100': resource.resource_type === 'pdf',
                            'bg-blue-100': resource.resource_type === 'link',
                            'bg-orange-100': resource.resource_type === 'presentation'
                        }">
                        <template x-if="resource.resource_type === 'pdf'">
                            <i class="ph ph-file-pdf text-red-600 text-2xl sm:text-3xl"></i>
                        </template>
                        <template x-if="resource.resource_type === 'link'">
                            <i class="ph ph-link text-blue-600 text-2xl sm:text-3xl"></i>
                        </template>
                        <template x-if="resource.resource_type === 'presentation'">
                            <i class="ph ph-file-ppt text-orange-600 text-2xl sm:text-3xl"></i>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="break-words text-sm font-bold text-slate-900 sm:text-base" x-text="resource.title"></h3>
                        <span class="mt-1 inline-block text-[10px] font-bold uppercase tracking-wide text-slate-400" x-text="resource.resource_type === 'link' ? 'Web resource' : resource.resource_type"></span>
                    </div>
                </div>
                <p class="mb-4 break-words text-xs leading-5 text-slate-500 sm:text-sm" x-text="resource.description_short"></p>

                <div class="mt-auto">
                    <template x-if="resource.resource_type === 'link'">
                        <a :href="resource.url" target="_blank" class="block w-full text-center px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm sm:text-base">
                            <i class="ph ph-arrow-square-out mr-2" style="color: var(--complementary-color);"></i>Open Link
                        </a>
                    </template>
                    <template x-if="resource.resource_type !== 'link'">
                        <div class="flex gap-2">
                            <button @click="viewResource(resource)" class="flex-1 text-center px-3 sm:px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm sm:text-base">
                                <i class="ph ph-eye mr-1 sm:mr-2" style="color: var(--complementary-color);"></i>View
                            </button>
                            <a :href="'/library/' + resource.id + '/download'" style="background-color: var(--primary-color);" class="flex-1 text-center px-3 sm:px-4 py-2 text-white rounded-lg hover:opacity-90 text-sm sm:text-base">
                                <i class="ph ph-download-simple mr-1 sm:mr-2" style="color: var(--complementary-color);"></i>Download
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="filteredResources.length === 0">
            <div class="library-empty sm:col-span-2 lg:col-span-3 bg-white rounded-lg shadow p-8 sm:p-12 text-center">
                <i class="ph ph-book-open text-gray-300 text-5xl sm:text-6xl mb-4"></i>
                <p class="text-gray-500 text-base sm:text-lg">
                    <span x-show="searchQuery">No resources found matching your search</span>
                    <span x-show="!searchQuery">No resources available</span>
                </p>
            </div>
        </template>
    </div>

    <!-- View File Modal -->
    <div x-show="showModal" x-cloak class="library-modal fixed inset-0 z-50 overflow-hidden" style="display: none;" @keydown.escape.window="showModal = false">
        <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
            <div @click="showModal = false" class="library-modal__backdrop fixed inset-0"></div>
            <div class="library-modal__surface relative bg-white rounded-lg shadow-xl w-full max-w-6xl h-[95vh] sm:h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-3 sm:p-4 border-b bg-white rounded-t-lg">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base sm:text-xl font-bold text-gray-900 truncate" x-text="currentResource?.title"></h3>
                    </div>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 ml-3 sm:ml-4 flex-shrink-0">
                        <i class="ph ph-x text-xl sm:text-2xl"></i>
                    </button>
                </div>
                <div class="flex-1 overflow-hidden bg-gray-100">
                    <template x-if="currentResource?.resource_type === 'pdf'">
                        <iframe :src="getFileUrl(currentResource.file_path)" class="w-full h-full border-0"></iframe>
                    </template>
                    <template x-if="currentResource?.resource_type === 'presentation'">
                        <div class="w-full h-full flex items-center justify-center p-8">
                            <div class="text-center bg-white rounded-lg shadow-lg p-8 max-w-lg">
                                <div class="mb-6">
                                    <i class="ph ph-file-ppt text-orange-600 text-7xl mb-4"></i>
                                </div>
                                <h4 class="text-2xl font-bold text-gray-900 mb-3" x-text="currentResource?.title"></h4>
                                <p class="text-gray-600 mb-2">PowerPoint Presentation</p>
                                <div class="space-y-3">
                                    <a :href="'/library/' + currentResource?.id + '/download'" style="background-color: var(--primary-color);" class="block w-full px-6 py-3 text-white rounded-lg hover:opacity-90 transition">
                                        <i class="ph ph-download-simple mr-2" style="color: var(--complementary-color);"></i>Download to View
                                    </a>
                                </div>
                                <p class="text-xs text-gray-500 mt-4">
                                    <i class="ph ph-info mr-1"></i>
                                    PowerPoint files need to be downloaded to view properly
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function librarySearch() {
    return {
        allResources: @json($resources),
        filteredResources: [],
        searchQuery: '',
        activeType: 'all',
        showModal: false,
        currentResource: null,

        init() {
            this.filteredResources = this.allResources;

            // Real-time updates using Pusher/Echo
            if (window.Echo) {
                window.Echo.channel('library')
                    .listen('.library-resource-created', (e) => {
                        console.log('Resource created:', e);
                        // Only add if published
                        if (e.is_published) {
                            this.allResources.unshift(e);
                            this.filterResources();
                        }
                    })
                    .listen('.library-resource-updated', (e) => {
                        console.log('Resource updated:', e);
                        const index = this.allResources.findIndex(r => r.id == e.id);
                        if (index !== -1) {
                            if (e.is_published) {
                                // Update existing
                                this.allResources[index] = { ...this.allResources[index], ...e };
                            } else {
                                // Remove if unpublished
                                this.allResources.splice(index, 1);
                            }
                            this.filterResources();
                        } else if (e.is_published) {
                            // Add if it was previously unpublished or new
                            this.allResources.unshift(e);
                            this.filterResources();
                        }
                    })
                    .listen('.library-resource-deleted', (e) => {
                        console.log('Resource deleted:', e);
                        if (!e.id) return;
                        const index = this.allResources.findIndex(r => r.id == e.id);
                        if (index !== -1) {
                            this.allResources.splice(index, 1);
                            this.filterResources();
                        }
                    });
            }
        },

        filterResources() {
            const query = this.searchQuery.toLowerCase();

            this.filteredResources = this.allResources.filter(resource => {
                // Check search query (title or description)
                const matchesSearch = !query || 
                    resource.title.toLowerCase().includes(query) || 
                    resource.description.toLowerCase().includes(query);

                const matchesType = this.activeType === 'all' || resource.resource_type === this.activeType;

                return matchesSearch && matchesType;
            });
        },

        setType(type) {
            this.activeType = type;
            this.filterResources();
        },

        countByType(type) {
            return this.allResources.filter(resource => resource.resource_type === type).length;
        },

        viewResource(resource) {
            this.currentResource = resource;
            this.showModal = true;
        },

        getFileUrl(filePath) {
            if (!filePath) return '';
            // Use the global storageUrl function from layouts/app.blade.php
            return window.storageUrl(filePath);
        }
    }
}
</script>
@endsection
