@extends('layouts.app')

@section('title', 'Documents')

@section('content')
<style>
    .student-documents-page { background: var(--neu-bg); color: var(--neu-text); }
    .student-documents-tab { transition: color .2s ease, border-color .2s ease, background .2s ease; }
    [x-cloak] { display: none !important; }
    /* Neumorphism */
    .student-documents-page {
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
        background: var(--neu-bg) !important;
        color: var(--neu-text);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
        -webkit-font-smoothing: antialiased;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    html:has(.student-documents-page),
    body:has(.student-documents-page),
    main:has(.student-documents-page) {
        background: var(--neu-bg) !important;
    }

    .student-documents-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

    .student-documents-kicker {
        color: var(--primary-color, #7c3aed) !important;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }
    .student-documents-page header h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
    .student-documents-page header p.text-gray-500 { color: var(--neu-muted) !important; }

    .documents-upload-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        min-height: 2.9rem;
        padding: .42rem 1rem .42rem .5rem;
        border: 0;
        border-radius: 999px;
        background: var(--primary-color, #7c3aed);
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
        color: #fff;
        font-size: .9rem;
        font-weight: 600;
        white-space: nowrap;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
    }
    .documents-upload-btn__icon {
        display: inline-flex;
        width: 2rem;
        height: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, .18);
        color: #fff;
        font-size: 1.05rem;
    }
    .documents-upload-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.04);
        box-shadow: 7px 7px 16px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -7px -7px 16px rgba(255, 255, 255, .9);
    }

    .documents-upload-btn--desktop { display: none; }
    @media (min-width: 640px) {
        .documents-upload-btn--desktop { display: inline-flex; }
        .documents-upload-btn--mobile { display: none; }
    }

    .documents-tip {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
        box-shadow: inset 4px 4px 8px rgba(var(--primary-color-rgb, 124, 58, 237), .12), inset -4px -4px 8px rgba(255, 255, 255, .7) !important;
        color: var(--neu-muted) !important;
    }
    .documents-tip .font-bold { color: var(--neu-text) !important; }

    .student-documents-panel {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        animation: documents-panel-in .55s backwards cubic-bezier(.2, .8, .2, 1);
    }
    .student-documents-panel h2 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }
    .student-documents-panel .border-b.border-gray-100, .student-documents-panel .border-b.border-gray-200 { border-color: var(--neu-divider) !important; }

    .student-documents-tab { font-weight: 600 !important; letter-spacing: -.01em; }

    .documents-filter-btn {
        border: 0 !important;
        border-radius: .9rem !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s cubic-bezier(.2, .8, .2, 1), background-color .25s ease !important;
    }
    .documents-filter-btn:hover { transform: translateY(-1px); box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }

    .documents-filter-dropdown {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        overflow: hidden;
    }
    .documents-filter-dropdown button { color: var(--neu-text) !important; }
    .documents-filter-dropdown button:hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .06) !important; }

    .student-neu-page .student-documents-link {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1) !important;
        animation: documents-card-in .65s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--doc-index, 0) * 45ms);
    }
    .student-neu-page .student-documents-link:hover {
        transform: translateY(-3px) !important;
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
    }
    .student-documents-link .bg-gray-50 { background: #DDE4EE !important; }
    .student-documents-link h4 { color: var(--neu-text) !important; letter-spacing: -.015em !important; }
    .student-documents-link .text-gray-400 { color: var(--neu-faint) !important; }
    .student-documents-link .text-gray-600 { color: var(--neu-muted) !important; }
    .student-documents-link .border-t, .student-documents-link .border-b { border-color: var(--neu-divider) !important; }
    .student-documents-link button[style*="background-color"] {
        border-radius: .9rem !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
    }
    .student-documents-link .bg-gray-600 { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(163, 177, 198, .45), -4px -4px 10px rgba(255, 255, 255, .85) !important; transition: transform .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important; }
    .student-documents-link .bg-gray-600:hover { transform: translateY(-2px); filter: brightness(1.05); }

    .student-documents-panel .rounded-full.bg-blue-100 { background: rgba(37, 99, 235, .12) !important; }
    .student-documents-panel .rounded-full.bg-green-100 { background: rgba(16, 185, 129, .12) !important; }
    .student-documents-panel .rounded-full.bg-red-100 { background: rgba(220, 38, 38, .12) !important; }
    .student-documents-panel .rounded-full.bg-purple-100 { background: rgba(var(--primary-color-rgb, 124, 58, 237), .12) !important; }
    .student-documents-panel .rounded-full.bg-gray-100 { background: rgba(163, 177, 198, .25) !important; }
    .student-documents-panel button[style*="background-color"] {
        border-radius: .9rem !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important;
    }
    .student-documents-panel button[style*="background-color"]:hover { transform: translateY(-2px); filter: brightness(1.04); }
    .student-documents-panel .text-gray-800 { color: var(--neu-text) !important; }

    .documents-modal {
        background: rgba(17, 17, 19, .46) !important;
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
    }
    .documents-modal__backdrop { background: transparent !important; }
    .documents-modal .relative.bg-white {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: #ffffff !important;
        box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important;
    }
    .documents-modal h2, .documents-modal h3 { color: var(--neu-text) !important; letter-spacing: -.03em !important; }
    .documents-modal .text-gray-400 { color: var(--neu-faint) !important; }
    .documents-modal .border-2 { border-color: var(--neu-divider) !important; }
    .documents-modal .rounded-xl { border-radius: 1rem !important; }
    .documents-modal .rounded-lg { border-radius: .8rem !important; }
    .documents-modal button.rounded-lg { border-radius: .9rem !important; }
    .documents-modal .border-dashed {
        border: 2px dashed rgba(var(--primary-color-rgb, 124, 58, 237), .4) !important;
        border-radius: 1rem !important;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .04) !important;
        box-shadow: inset 3px 3px 8px rgba(var(--primary-color-rgb, 124, 58, 237), .10), inset -3px -3px 8px rgba(255, 255, 255, .6) !important;
    }
    .documents-modal button[style*="background-color"] {
        border-radius: .9rem !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important;
    }
    .documents-modal button[style*="background-color"]:hover { transform: translateY(-2px); filter: brightness(1.04); }
    .documents-modal button.bg-red-600 { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(220, 38, 38, .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
    .documents-modal .bg-gray-100 { background: #E3E9F1 !important; }

    .documents-upload-btn:focus-visible, .documents-filter-btn:focus-visible,
    .documents-modal button:focus-visible {
        outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
        outline-offset: 3px;
    }

    @keyframes documents-panel-in { from { opacity: 0; transform: translateY(14px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes documents-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-documents-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-documents-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .student-documents-panel { border-radius: 1.25rem !important; }
        .student-documents-link { border-radius: 1.15rem !important; }
        .documents-modal .relative.bg-white { border-radius: 1.25rem !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        .student-documents-panel, .student-documents-link { animation: none !important; }
        .student-documents-link, .documents-upload-btn, .documents-filter-btn { transition-duration: .01ms !important; }
    }
</style>
<div class="student-documents-page student-neu-page min-h-screen py-4 sm:py-6">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8" x-data="{
    showUploadModal: {{ $errors->any() ? 'true' : 'false' }},
    activeTab: '{{ session("uploaded_document_type", "cor") }}',
    viewModal: false,
    viewFile: '',
    viewType: '',
    showDeleteModal: false,
    deleteDocument: null,
    showShareModal: false,
    showUnshareModal: false,
    shareDocument: null,
    corFilterYear: 'all',
    corFilterSemester: 'all'
}" x-init="
    @if(!session('uploaded_document_type'))
        activeTab = localStorage.getItem('documentsActiveTab') || 'cor';
    @endif
    $watch('activeTab', value => localStorage.setItem('documentsActiveTab', value));
">
        <!-- Focused page header -->
        <header class="mb-6 sm:mb-7">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="student-documents-kicker mb-2">Your student records</p>
                    <div class="flex items-center justify-between gap-3">
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">My documents</h1>
                        <button @click="showUploadModal = true" class="documents-upload-btn documents-upload-btn--mobile">
                            <span class="documents-upload-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                            <span>Upload document</span>
                        </button>
                    </div>
                </div>
                <div class="documents-hero__actions">
                    <button @click="showUploadModal = true" class="documents-upload-btn documents-upload-btn--desktop">
                        <span class="documents-upload-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                        <span>Upload document</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="documents-tip flex items-start gap-3 px-4 py-3.5 mb-6 rounded-xl border border-purple-100 bg-purple-50/60 text-sm text-gray-600">
            <i class="ph ph-info mt-0.5" style="color: var(--primary-color);"></i>
            <p><span class="font-bold text-gray-800">Quick tip:</span> Use clear, readable files under 5MB. Share a document with an admin only when it is needed for processing.</p>
        </div>

        <!-- Toast Notification -->
        @if(session('success'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-full"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-full"
             style="display: block;"
             class="fixed top-4 right-4 z-50 max-w-md">
            <div class="bg-white rounded-lg shadow-2xl border-l-4 border-green-500 p-4 flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="ph ph-check-circle text-green-500 text-xl"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900">Success!</p>
                    <p class="text-sm text-gray-600">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>
        @endif

        <!-- Upload Modal -->
        <div x-show="showUploadModal" x-cloak class="documents-modal fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-start sm:items-center justify-center min-h-screen p-3 sm:p-4">
                <div @click="showUploadModal = false" class="documents-modal__backdrop fixed inset-0"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-5 sm:p-8 my-4 sm:my-0 transform transition-all">
                    <button @click="showUploadModal = false" class="absolute top-3 sm:top-4 right-3 sm:right-4 text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl sm:text-2xl"></i>
                    </button>

                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 flex items-center pr-8">
                        <i style="color: var(--complementary-color);" class="ph ph-upload-simple mr-2 sm:mr-3"></i>Upload New Document
                    </h2>

                    <form action="{{ route('documents.upload') }}" method="POST" enctype="multipart/form-data" x-data="{ selectedType: '{{ old('document_type', '') }}', isDragging: false, currentYearLevel: '{{ auth()->user()->year_level }}' }">
                        @csrf
                        <input type="hidden" name="document_type" x-model="selectedType">

                        <div class="space-y-5 sm:space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3 sm:mb-4">Select Document Type</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                                    <div @click="selectedType = 'cor'"
                                         :style="selectedType === 'cor' ? 'border-color: var(--primary-color); background-color: rgba(var(--complementary-color-rgb), 0.1);' : ''"
                                         class="border-2 border-gray-300 rounded-xl p-4 cursor-pointer transition text-center"
                                         @mouseenter="if(selectedType !== 'cor') $el.style.borderColor = 'var(--primary-color)'"
                                         @mouseleave="if(selectedType !== 'cor') $el.style.borderColor = ''">
                                        <i class="ph ph-file-text text-4xl mb-2 transition text-blue-500"
                                           :style="selectedType === 'cor' ? 'color: var(--primary-color);' : ''"
                                           style="transition: all 0.2s;"></i>
                                        <h4 class="font-semibold text-sm transition text-gray-700"
                                            :style="selectedType === 'cor' ? 'color: var(--primary-color);' : ''"
                                            style="transition: all 0.2s;">COR</h4>
                                        <p class="text-xs text-gray-500 mt-1">Certificate of Registration</p>
                                    </div>

                                    <div @click="selectedType = 'parent_consent'"
                                         :style="selectedType === 'parent_consent' ? 'border-color: var(--primary-color); background-color: rgba(var(--complementary-color-rgb), 0.1);' : ''"
                                         class="border-2 border-gray-300 rounded-xl p-4 cursor-pointer transition text-center"
                                         @mouseenter="if(selectedType !== 'parent_consent') $el.style.borderColor = 'var(--primary-color)'"
                                         @mouseleave="if(selectedType !== 'parent_consent') $el.style.borderColor = ''">
                                        <i class="ph ph-signature text-4xl mb-2 transition text-green-500"
                                           :style="selectedType === 'parent_consent' ? 'color: var(--primary-color);' : ''"
                                           style="transition: all 0.2s;"></i>
                                        <h4 class="font-semibold text-sm transition text-gray-700"
                                            :style="selectedType === 'parent_consent' ? 'color: var(--primary-color);' : ''"
                                            style="transition: all 0.2s;">Parent Consent</h4>
                                        <p class="text-xs text-gray-500 mt-1">Valid for 1 year</p>
                                    </div>

                                    <div @click="selectedType = 'medical_certificate'"
                                         :style="selectedType === 'medical_certificate' ? 'border-color: var(--primary-color); background-color: rgba(var(--complementary-color-rgb), 0.1);' : ''"
                                         class="border-2 border-gray-300 rounded-xl p-4 cursor-pointer transition text-center"
                                         @mouseenter="if(selectedType !== 'medical_certificate') $el.style.borderColor = 'var(--primary-color)'"
                                         @mouseleave="if(selectedType !== 'medical_certificate') $el.style.borderColor = ''">
                                        <i class="ph ph-file-plus text-4xl mb-2 transition text-red-500"
                                           :style="selectedType === 'medical_certificate' ? 'color: var(--primary-color);' : ''"
                                           style="transition: all 0.2s;"></i>
                                        <h4 class="font-semibold text-sm transition text-gray-700"
                                            :style="selectedType === 'medical_certificate' ? 'color: var(--primary-color);' : ''"
                                            style="transition: all 0.2s;">Medical Certificate</h4>
                                        <p class="text-xs text-gray-500 mt-1">Valid for 6 months</p>
                                    </div>
                                </div>
                                @error('document_type')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Year Level and Semester (Only for COR) -->
                            <div x-show="selectedType === 'cor'" x-cloak style="display: none;">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Year Level</label>
                                        <select name="year_level" class="w-full px-4 py-3 text-sm border-2 border-gray-300 rounded-lg focus:ring-2 transition" style="--tw-ring-color: var(--primary-color); border-color: var(--primary-color);">
                                            <option value="">Select Year</option>
                                            @php
                                                $userYearLevel = auth()->user()->year_level;
                                                $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
                                                $currentYearIndex = array_search($userYearLevel, $yearLevels);
                                            @endphp
                                            @foreach($yearLevels as $index => $year)
                                                @if($index <= $currentYearIndex)
                                                    <option value="{{ $year }}">{{ $year }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        @error('year_level')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Semester</label>
                                        <select name="semester" class="w-full px-4 py-3 text-sm border-2 border-gray-300 rounded-lg focus:ring-2 transition" style="--tw-ring-color: var(--primary-color); border-color: var(--primary-color);">
                                            <option value="">Select Semester</option>
                                            <option value="1st Semester">1st Semester</option>
                                            <option value="2nd Semester">2nd Semester</option>
                                        </select>
                                        @error('semester')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Issue Date (Only for Parent Consent and Medical Certificate) -->
                            <div x-show="selectedType === 'parent_consent' || selectedType === 'medical_certificate'" x-cloak style="display: none;">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Issue Date</label>
                                <input type="date" name="issue_date" max="{{ date('Y-m-d') }}" class="w-full px-4 py-3 text-sm border-2 border-gray-300 rounded-lg focus:ring-2 transition" style="--tw-ring-color: var(--primary-color); border-color: var(--primary-color);">
                                @error('issue_date')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Upload File (PDF, JPG, PNG - Max 5MB)</label>
                                <div class="relative"
                                     @dragover.prevent="isDragging = true"
                                     @dragleave.prevent="isDragging = false"
                                     @drop.prevent="isDragging = false; handleDrop($event)">
                                    <input type="file" name="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" required class="hidden">
                                    <label for="fileInput"
                                           :style="isDragging ? 'background-color: rgba(var(--complementary-color-rgb), 0.1);' : ''"
                                           class="flex flex-col items-center justify-center w-full px-6 py-8 text-sm border-2 border-dashed rounded-lg cursor-pointer transition"
                                           style="border-color: var(--primary-color);"
                                           @mouseenter="$el.style.backgroundColor = 'rgba(var(--complementary-color-rgb), 0.05)'"
                                           @mouseleave="if(!isDragging) $el.style.backgroundColor = ''">
                                        <i class="ph ph-cloud-arrow-up text-4xl mb-3" style="color: var(--primary-color);"></i>
                                        <span class="text-gray-600 font-semibold" id="fileName">Click to choose file</span>
                                        <span class="text-gray-400 text-xs mt-1">or drag and drop here</span>
                                    </label>
                                </div>
                                @error('file')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" @click="showUploadModal = false" class="px-6 py-2 border-2 border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-semibold">
                                Cancel
                            </button>
                            <button type="submit" style="background-color: var(--primary-color);" class="hover:opacity-90 text-white px-6 py-2 rounded-lg font-semibold transition flex items-center">
                                <i style="color: var(--complementary-color);" class="ph ph-upload-simple mr-2"></i>Upload Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- My Documents List -->
        <section class="student-documents-panel overflow-hidden">
            <div class="px-4 sm:px-6 py-5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight text-gray-900 mt-1">Uploaded documents</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">Preview, organize, or share a file when you need to.</p>
                </div>

                <!-- COR Filter Dropdowns -->
                <div x-show="activeTab === 'cor'" class="flex items-center space-x-3" style="display: none;">
                    <!-- Year Level Filter -->
                    <div class="relative" x-data="{ showYearDropdown: false }" @click.away="showYearDropdown = false">
                        <button @click="showYearDropdown = !showYearDropdown" class="documents-filter-btn bg-gray-50 hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg font-semibold text-xs transition flex items-center border border-gray-200 whitespace-nowrap">
                            <i style="color: var(--primary-color);" class="ph ph-graduation-cap mr-2"></i>
                            <span x-text="corFilterYear === 'all' ? 'All Years' : corFilterYear"></span>
                            <i class="ph ph-caret-down ml-2 text-xs"></i>
                        </button>
                        <div x-show="showYearDropdown" x-cloak class="documents-filter-dropdown absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border-2 border-gray-200 z-50" style="display: none;">
                            <button @click="corFilterYear = 'all'; corFilterSemester = 'all'; showYearDropdown = false" class="w-full text-left px-4 py-2 hover:bg-purple-50 transition text-sm font-semibold" :class="corFilterYear === 'all' ? 'bg-purple-100' : 'text-gray-700'" :style="corFilterYear === 'all' ? 'color: var(--primary-color);' : ''">
                                <i class="ph ph-list mr-2"></i>All Years
                            </button>
                            @php
                                $userYearLevel = auth()->user()->year_level;
                                $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
                                $currentYearIndex = array_search($userYearLevel, $yearLevels);
                            @endphp
                            @foreach($yearLevels as $index => $year)
                                @if($index <= $currentYearIndex)
                                <button @click="corFilterYear = '{{ $year }}'; showYearDropdown = false" class="w-full text-left px-4 py-2 hover:bg-purple-50 transition text-sm font-semibold" :class="corFilterYear === '{{ $year }}' ? 'bg-purple-100' : 'text-gray-700'" :style="corFilterYear === '{{ $year }}' ? 'color: var(--primary-color);' : ''">
                                    <i class="ph ph-graduation-cap mr-2"></i>{{ $year }}
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <!-- Semester Filter (Only show when year is selected) -->
                    <div x-show="corFilterYear !== 'all'" class="relative" x-data="{ showSemesterDropdown: false }" @click.away="showSemesterDropdown = false" style="display: none;">
                        <button @click="showSemesterDropdown = !showSemesterDropdown" class="documents-filter-btn bg-gray-50 hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg font-semibold text-xs transition flex items-center border border-gray-200 whitespace-nowrap">
                            <i style="color: var(--primary-color);" class="ph ph-calendar-blank mr-2"></i>
                            <span x-text="corFilterSemester === 'all' ? 'All Semesters' : corFilterSemester"></span>
                            <i class="ph ph-caret-down ml-2 text-xs"></i>
                        </button>
                        <div x-show="showSemesterDropdown" x-cloak class="documents-filter-dropdown absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border-2 border-gray-200 z-50" style="display: none;">
                            <button @click="corFilterSemester = 'all'; showSemesterDropdown = false" class="w-full text-left px-4 py-2 hover:bg-purple-50 transition text-sm font-semibold" :class="corFilterSemester === 'all' ? 'bg-purple-100' : 'text-gray-700'" :style="corFilterSemester === 'all' ? 'color: var(--primary-color);' : ''">
                                <i class="ph ph-list mr-2"></i>All Semesters
                            </button>
                            <button @click="corFilterSemester = '1st Semester'; showSemesterDropdown = false" class="w-full text-left px-4 py-2 hover:bg-purple-50 transition text-sm font-semibold" :class="corFilterSemester === '1st Semester' ? 'bg-purple-100' : 'text-gray-700'" :style="corFilterSemester === '1st Semester' ? 'color: var(--primary-color);' : ''">
                                <i class="ph ph-calendar-blank mr-2"></i>1st Semester
                            </button>
                            <button @click="corFilterSemester = '2nd Semester'; showSemesterDropdown = false" class="w-full text-left px-4 py-2 hover:bg-purple-50 transition text-sm font-semibold" :class="corFilterSemester === '2nd Semester' ? 'bg-purple-100' : 'text-gray-700'" :style="corFilterSemester === '2nd Semester' ? 'color: var(--primary-color);' : ''">
                                <i class="ph ph-calendar-blank mr-2"></i>2nd Semester
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 overflow-x-auto">
                <div class="flex px-4 sm:px-6 min-w-max gap-1">
                    <button @click="activeTab = 'cor'"
                            :class="activeTab === 'cor' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="student-documents-tab py-4 px-4 sm:px-5 border-b-2 font-semibold text-sm transition">
                        <i class="ph ph-file-text mr-1"></i>COR
                    </button>
                    <button @click="activeTab = 'parent_consent'"
                            :class="activeTab === 'parent_consent' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="student-documents-tab py-4 px-4 sm:px-5 border-b-2 font-semibold text-sm transition">
                        <i class="ph ph-signature mr-1"></i>Parent Consent
                    </button>
                    <button @click="activeTab = 'medical_certificate'"
                            :class="activeTab === 'medical_certificate' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="student-documents-tab py-4 px-4 sm:px-5 border-b-2 font-semibold text-sm transition">
                        <i class="ph ph-file-plus mr-1"></i>Medical Certificate
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-6 relative">
                @php
                    $groupedDocuments = $documents->groupBy('document_type');
                    $categories = [
                        'cor' => ['name' => 'Certificate of Registration', 'icon' => 'ph-file-text', 'color' => 'blue'],
                        'parent_consent' => ['name' => 'Parent Consent', 'icon' => 'ph-signature', 'color' => 'green'],
                        'medical_certificate' => ['name' => 'Medical Certificate', 'icon' => 'ph-file-plus', 'color' => 'red']
                    ];
                @endphp

                @foreach($categories as $type => $category)
                    <div x-show="activeTab === '{{ $type }}'"
                         style="display: none;"
                         class="mb-8">
                        @if($groupedDocuments->has($type))
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" x-data="{
                                get visibleCount() {
                                    if ('{{ $type }}' !== 'cor') return {{ $groupedDocuments[$type]->count() }};
                                    let count = 0;
                                    @foreach($groupedDocuments[$type] as $document)
                                        if ((this.corFilterYear === 'all' || this.corFilterYear === '{{ $document->year_level }}') &&
                                            (this.corFilterSemester === 'all' || this.corFilterSemester === '{{ $document->semester }}')) {
                                            count++;
                                        }
                                    @endforeach
                                    return count;
                                }
                            }">
                                @foreach($groupedDocuments[$type] as $document)
                                <div x-show="'{{ $type }}' !== 'cor' || ((corFilterYear === 'all' || corFilterYear === '{{ $document->year_level }}') && (corFilterSemester === 'all' || corFilterSemester === '{{ $document->semester }}'))"
                                     data-document-id="{{ $document->id }}"
                                     class="student-documents-link bg-white border border-gray-200 rounded-2xl overflow-hidden"
                                     style="--doc-index: {{ $loop->index }}; transition: all 0.3s;"
                                     @mouseenter="$el.style.borderColor = 'var(--primary-color)'"
                                     @mouseleave="$el.style.borderColor = ''">
                                    <!-- Document Preview/Thumbnail -->
                                    <div class="relative bg-gray-50 h-48 sm:h-56 overflow-hidden cursor-pointer" @click="viewFile = '{{ storage_url($document->file_path) }}'; viewType = '{{ pathinfo($document->file_path, PATHINFO_EXTENSION) }}'; viewModal = true">
                                        @php
                                            $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
                                        @endphp
                                        @if(in_array($extension, ['jpg', 'jpeg', 'png']))
                                            <img src="{{ storage_url($document->file_path) }}" alt="{{ $category['name'] }}" class="w-full h-full object-cover">
                                        @else
                                            <!-- PDF Preview Thumbnail -->
                                            <div class="absolute inset-0 bg-gray-50" style="overflow: hidden; pointer-events: none;">
                                                <iframe src="{{ storage_url($document->file_path) }}#page=1&toolbar=0&navpanes=0&scrollbar=0&view=Fit"
                                                        class="w-full h-full"
                                                        style="border: none; transform: scale(1.15); transform-origin: center top; pointer-events: none;"
                                                        scrolling="no"></iframe>
                                            </div>
                                            <!-- PDF Overlay for click -->
                                            <div class="absolute inset-0 bg-transparent z-10" style="pointer-events: auto;"></div>
                                        @endif

                                        <!-- Status Badge -->
                                        @php
                                            $isShared = \App\Models\SharedDocument::where('student_document_id', $document->id)
                                                ->where('student_id', auth()->id())
                                                ->exists();
                                        @endphp
                                        <div class="shared-badge-container absolute top-2 right-2">
                                            @if($isShared)
                                            <span class="px-3 py-1 text-xs font-bold rounded-full text-white shadow-lg" style="background-color: var(--primary-color);">
                                                <i class="ph ph-check-circle mr-1"></i>Shared
                                            </span>
                                            @endif
                                        </div>

                                        <!-- Expiry Badge (if applicable) -->
                                        @if($document->expiry_date && $document->isExpired())
                                        <div class="absolute top-2 left-2">
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-500 text-white shadow-lg">
                                                <i class="ph ph-warning mr-1"></i>Expired
                                            </span>
                                        </div>
                                        @endif
                                    </div>

                                    <!-- Document Info -->
                                    <div class="p-4">
                                        <div class="flex items-start justify-between gap-3 mb-3"><h4 class="text-sm font-black text-gray-900">{{ $category['name'] }}</h4><span class="text-[10px] uppercase font-bold tracking-wide text-gray-400">{{ strtoupper($extension) }}</span></div>
                                        <div class="space-y-1 mb-4">
                                            @if($type === 'cor' && $document->year_level && $document->semester)
                                            <p class="text-xs text-gray-600 flex items-center">
                                                <i class="ph ph-graduation-cap mr-2 text-blue-500"></i>
                                                <span class="font-semibold">{{ $document->year_level }} - {{ $document->semester }}</span>
                                            </p>
                                            @endif
                                            @if($type !== 'cor' && $document->issue_date)
                                            <p class="text-xs text-gray-600 flex items-center">
                                                <i class="ph ph-calendar mr-2" style="color: var(--primary-color);"></i>
                                                <span class="font-semibold">Issued:</span>&nbsp;{{ $document->issue_date->format('M d, Y') }}
                                            </p>
                                            @endif
                                            @if($document->expiry_date)
                                            <p class="text-xs {{ $document->isExpired() ? 'text-red-600' : 'text-gray-600' }} flex items-center">
                                                <i class="ph ph-clock mr-2 {{ $document->isExpired() ? 'text-red-500' : '' }}" :style="!{{ $document->isExpired() ? 'true' : 'false' }} ? 'color: var(--primary-color);' : ''"></i>
                                                <span class="font-semibold">{{ $document->isExpired() ? 'Expired:' : 'Expires:' }}</span>&nbsp;{{ $document->expiry_date->format('M d, Y') }}
                                            </p>
                                            @endif
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex items-center gap-2 share-button-container">
                                            @if($isShared)
                                                <button @click.stop="shareDocument = {{ json_encode($document) }}; showUnshareModal = true" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition">
                                                    <i style="color: var(--complementary-color);" class="ph ph-prohibit mr-1"></i>Revoke Access
                                                </button>
                                            @else
                                                <button @click.stop="shareDocument = {{ json_encode($document) }}; showShareModal = true" style="background-color: var(--primary-color);" class="flex-1 hover:opacity-90 text-white px-3 py-2 rounded-lg text-xs font-semibold transition">
                                                    <i style="color: var(--complementary-color);" class="ph ph-share mr-1"></i>Share with Admin
                                                </button>
                                            @endif
                                            <button @click.stop="deleteDocument = {{ json_encode($document) }}; showDeleteModal = true" class="text-red-600 hover:text-red-800 transition" title="Delete">
                                                <i class="ph ph-trash text-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endforeach

                                <!-- Filtered Empty State for COR -->
                                <div x-show="'{{ $type }}' === 'cor' && visibleCount === 0" class="col-span-full text-center py-16">
                                    <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-blue-100 mb-4">
                                        <i class="ph ph-funnel text-blue-500 text-5xl"></i>
                                    </div>
                                    <p class="text-xl font-bold text-gray-800 mb-2">No COR Found</p>
                                    <p class="text-sm text-gray-500 mb-4">
                                        No COR documents found for
                                        <span class="font-semibold" x-text="corFilterYear !== 'all' ? corFilterYear : 'selected year'"></span>
                                        <span x-show="corFilterSemester !== 'all'"> - <span class="font-semibold" x-text="corFilterSemester"></span></span>
                                    </p>
                                    <button @click="corFilterYear = 'all'; corFilterSemester = 'all'" style="background-color: var(--primary-color);" class="inline-flex items-center px-6 py-3 hover:opacity-90 text-white rounded-lg font-semibold transition transform hover:scale-105">
                                        <i style="color: var(--complementary-color);" class="ph ph-x-circle mr-2"></i>Clear Filter
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- Empty State for this category -->
                            <div class="text-center py-16">
                                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-{{ $category['color'] }}-100 mb-4">
                                    <i class="ph {{ $category['icon'] }} text-{{ $category['color'] }}-500 text-5xl"></i>
                                </div>
                                <p class="text-xl font-bold text-gray-800 mb-2">No {{ $category['name'] }} Yet</p>
                                <p class="text-sm text-gray-500 mb-4">You haven't uploaded any {{ strtolower($category['name']) }} documents</p>
                                <button @click="showUploadModal = true" style="background-color: var(--primary-color);" class="inline-flex items-center px-6 py-3 hover:opacity-90 text-white rounded-lg font-semibold transition transform hover:scale-105">
                                    <i style="color: var(--complementary-color);" class="ph ph-plus-circle mr-2"></i>Upload {{ $category['name'] }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        </section>
            <!-- View Document Modal -->
            <div x-show="viewModal" x-cloak class="documents-modal fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div @click="viewModal = false" class="documents-modal__backdrop fixed inset-0"></div>
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 transform transition-all">
                        <button @click="viewModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                            <i class="ph ph-x text-2xl"></i>
                        </button>

                        <h2 class="text-xl font-bold text-gray-800 mb-4">Document Preview</h2>

                        <div class="bg-gray-100 rounded-lg p-4" style="height: 600px;">
                            <template x-if="viewType === 'pdf'">
                                <iframe :src="viewFile + '#view=FitH'" class="w-full h-full rounded" style="border: none;"></iframe>
                            </template>
                            <template x-if="viewType !== 'pdf'">
                                <img :src="viewFile" class="w-full h-full object-contain rounded">
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div x-show="showDeleteModal" x-cloak class="documents-modal fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div @click="showDeleteModal = false" class="documents-modal__backdrop fixed inset-0"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                                <i class="ph ph-warning text-3xl text-red-600"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Document</h3>
                            <p class="text-gray-600 mb-6">
                                Are you sure you want to delete this document? This action cannot be undone.
                            </p>
                            <div class="flex space-x-3">
                                <button @click="showDeleteModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                                    <i class="ph ph-x mr-2"></i>Cancel
                                </button>
                                <form :action="`{{ url('documents') }}/${deleteDocument?.id}`" method="POST" class="flex-1" x-data="{ isDeleting: false }" @submit="isDeleting = true">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :disabled="isDeleting" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                                        <i class="ph mr-2" :class="isDeleting ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                        <span x-show="!isDeleting">Delete</span>
                                        <span x-show="isDeleting">Deleting...</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Share Confirmation Modal -->
            <div x-show="showShareModal" x-cloak class="documents-modal fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div @click="showShareModal = false" class="documents-modal__backdrop fixed inset-0"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                                <i style="color: var(--complementary-color);" class="ph ph-share text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Share with Admin</h3>
                            <p class="text-gray-600 mb-6">
                                Are you sure you want to share this document with the admin? They will be able to view this document.
                            </p>
                            <div class="flex space-x-3" x-data="{ isSharing: false }">
                                <button @click="showShareModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                                    <i class="ph ph-x mr-2"></i>Cancel
                                </button>
                                <button @click="
                                    isSharing = true;
                                    fetch(`{{ url('documents') }}/${shareDocument?.id}/share`, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        const docElement = document.querySelector(`[data-document-id=\'${shareDocument?.id}\']`);
                                        if (docElement) {
                                            const badgeContainer = docElement.querySelector('.shared-badge-container');
                                            if (badgeContainer) {
                                                badgeContainer.innerHTML = `<span class=\'px-3 py-1 text-xs font-bold rounded-full text-white shadow-lg\' style=\'background-color: var(--primary-color);\'><i class=\'ph ph-check-circle mr-1\'></i>Shared</span>`;
                                            }
                                            const buttonContainer = docElement.querySelector('.share-button-container');
                                            if (buttonContainer) {
                                                const btn = buttonContainer.querySelector('button:first-child');
                                                if (btn) {
                                                    btn.className = 'flex-1 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition';
                                                    btn.innerHTML = '<i style=\'color: var(--complementary-color);\' class=\'ph ph-prohibit mr-1\'></i>Revoke Access';
                                                    btn.setAttribute('@click.stop', 'shareDocument = ' + JSON.stringify(shareDocument) + '; showUnshareModal = true');
                                                }
                                            }
                                        }
                                        showShareModal = false;
                                        isSharing = false;
                                        window.showToast('success', 'Document shared with admin successfully!');
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        isSharing = false;
                                        window.showToast('error', 'Failed to share document.');
                                    });
                                " style="background-color: var(--primary-color);" class="flex-1 px-4 py-2 hover:opacity-90 text-white rounded-lg font-semibold transition" :disabled="isSharing" :class="{ 'opacity-50 cursor-not-allowed': isSharing }">
                                    <i style="color: var(--complementary-color);" class="ph mr-2" :class="isSharing ? 'ph-spinner ph-spin' : 'ph-share'"></i>
                                    <span x-show="!isSharing">Share</span>
                                    <span x-show="isSharing">Sharing...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unshare Confirmation Modal -->
            <div x-show="showUnshareModal" x-cloak class="documents-modal fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div @click="showUnshareModal = false" class="documents-modal__backdrop fixed inset-0"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                                <i class="ph ph-prohibit text-3xl text-gray-600"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Revoke Access</h3>
                            <p class="text-gray-600 mb-6">
                                Are you sure you want to revoke admin access to this document? They will no longer be able to view it.
                            </p>
                            <div class="flex space-x-3" x-data="{ isUnsharing: false }">
                                <button @click="showUnshareModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                                    <i class="ph ph-x mr-2"></i>Cancel
                                </button>
                                <button @click="
                                    isUnsharing = true;
                                    fetch(`{{ url('documents') }}/${shareDocument?.id}/unshare`, {
                                        method: 'DELETE',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        const docElement = document.querySelector(`[data-document-id=\'${shareDocument?.id}\']`);
                                        if (docElement) {
                                            const badgeContainer = docElement.querySelector('.shared-badge-container');
                                            if (badgeContainer) {
                                                badgeContainer.innerHTML = '';
                                            }
                                            const buttonContainer = docElement.querySelector('.share-button-container');
                                            if (buttonContainer) {
                                                const btn = buttonContainer.querySelector('button:first-child');
                                                if (btn) {
                                                    btn.className = 'flex-1 hover:opacity-90 text-white px-3 py-2 rounded-lg text-xs font-semibold transition';
                                                    btn.style.backgroundColor = 'var(--primary-color)';
                                                    btn.innerHTML = '<i style=\'color: var(--complementary-color);\' class=\'ph ph-share mr-1\'></i>Share with Admin';
                                                    btn.setAttribute('@click.stop', 'shareDocument = ' + JSON.stringify(shareDocument) + '; showShareModal = true');
                                                }
                                            }
                                        }
                                        showUnshareModal = false;
                                        isUnsharing = false;
                                        window.showToast('success', 'Admin access revoked successfully!');
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        isUnsharing = false;
                                        window.showToast('error', 'Failed to revoke access.');
                                    });
                                " class="flex-1 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition" :disabled="isUnsharing" :class="{ 'opacity-50 cursor-not-allowed': isUnsharing }">
                                    <i class="ph mr-2" :class="isUnsharing ? 'ph-spinner ph-spin' : 'ph-prohibit'"></i>
                                    <span x-show="!isUnsharing">Revoke</span>
                                    <span x-show="isUnsharing">Revoking...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    </div>
</div>

<script>
var fileInput = document.getElementById('fileInput');
var fileNameDisplay = document.getElementById('fileName');

fileInput.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Click to choose file';
    fileNameDisplay.textContent = fileName;
});

// Drag and drop handler
function handleDrop(event) {
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        fileNameDisplay.textContent = files[0].name;
    }
}

// Make handleDrop available globally
window.handleDrop = handleDrop;

// Toast notification helper
window.showToast = function(type, message) {
    const toast = document.createElement('div');
    const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
    const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
    const textColor = type === 'success' ? 'text-green-600' : 'text-red-600';
    const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
    const title = type === 'success' ? 'Success!' : 'Error!';

    toast.className = `fixed top-6 right-6 z-50 bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md transition-all duration-300 ease-out`;
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';

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

    // Trigger animation
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);

    // Auto remove
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};

// WebSocket Real-Time Updates for Document Sharing
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 Student Documents - DOMContentLoaded fired');

    @if(auth()->check())

    console.log('📄 Student Documents - Echo object:', window.Echo);

    if (!window.Echo) {
        console.error('❌ Echo is not defined! WebSocket will not work.');
        return;
    }

    const channelName = 'documents';
    console.log('📄 Student Documents - Subscribing to channel:', channelName);

    // Listen for document share/unshare events
    const channel = window.Echo.channel(channelName);

    console.log('📄 Student Documents - Channel object:', channel);

    channel.listen('.document-shared', (e) => {
            console.log('✅ Student - document-shared event received!', e);

            // Check if this is the current user's document
            if (e.student && e.student.id === {{ auth()->id() }}) {
                console.log('✅ This is my document that was shared, document_id:', e.document_id);

                // Find the document card and update it
                const docElement = document.querySelector(`[data-document-id="${e.document_id}"]`);
                if (docElement) {
                    console.log('✅ Found document element, updating UI');

                    // Update badge
                    const badgeContainer = docElement.querySelector('.shared-badge-container');
                    if (badgeContainer) {
                        badgeContainer.innerHTML = `
                            <span class="px-3 py-1 text-xs font-bold rounded-full text-white shadow-lg" style="background-color: var(--primary-color);">
                                <i class="ph ph-check-circle mr-1"></i>Shared
                            </span>
                        `;
                    }

                    // Update button
                    const buttonContainer = docElement.querySelector('.share-button-container');
                    if (buttonContainer) {
                        const btn = buttonContainer.querySelector('button:first-child');
                        if (btn && btn.textContent.includes('Share with Admin')) {
                            btn.className = 'flex-1 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition';
                            btn.innerHTML = '<i style="color: var(--complementary-color);" class="ph ph-prohibit mr-1"></i>Revoke Access';
                        }
                    }
                } else {
                    console.log('❌ Document element not found for ID:', e.document_id);
                }
            }
        })
        .listen('.document-unshared', (e) => {
            console.log('✅ Student - document-unshared event received!', e);

            // Check if this is the current user's document
            if (e.student_id === {{ auth()->id() }}) {
                console.log('✅ This is my document that was unshared');

                // We need to find the document by looking at all document cards
                // since we don't have the specific document_id in unshare event
                const allDocElements = document.querySelectorAll('[data-document-id]');
                allDocElements.forEach(docElement => {
                    const buttonContainer = docElement.querySelector('.share-button-container');
                    if (buttonContainer) {
                        const btn = buttonContainer.querySelector('button:first-child');
                        // Check if this button is currently showing "Revoke Access"
                        if (btn && btn.textContent.includes('Revoke Access')) {
                            // This might be the document that was unshared
                            // Update it to show "Share with Admin"
                            const badgeContainer = docElement.querySelector('.shared-badge-container');
                            if (badgeContainer && badgeContainer.innerHTML.trim() !== '') {
                                // This document has a shared badge, so it's likely the one
                                badgeContainer.innerHTML = '';
                                btn.className = 'flex-1 hover:opacity-90 text-white px-3 py-2 rounded-lg text-xs font-semibold transition';
                                btn.style.backgroundColor = 'var(--primary-color)';
                                btn.innerHTML = '<i style="color: var(--complementary-color);" class="ph ph-share mr-1"></i>Share with Admin';
                                console.log('✅ Updated document UI to unshared state');
                            }
                        }
                    }
                });
            }
        });

    console.log('📄 Student Documents - Listeners attached successfully');

    @else
    console.log('⚠️ User not authenticated - WebSocket not initialized');
    @endif
});
</script>
@endsection
