@extends('layouts.app')

@section('title', 'EduShare')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="student-edushare-page min-h-screen">
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <!-- Header -->
    <header class="edushare-hero">
        <div class="edushare-hero__copy">
            <p class="edushare-eyebrow">Shared library</p>
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">EduShare</h1>
                <button id="newButton" onclick="showModal('upload')" class="edushare-new-btn edushare-new-btn--mobile">
                    <span class="edushare-new-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                    <span>New</span>
                </button>
            </div>
            <p class="text-sm sm:text-base mt-1 sm:mt-2">Share and access files</p>
        </div>
        <div class="edushare-hero__actions">
            <button id="newButtonDesktop" onclick="showModal('upload')" class="edushare-new-btn edushare-new-btn--desktop">
                <span class="edushare-new-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                <span>New</span>
            </button>
        </div>
    </header>

    <!-- Search -->
    <form method="GET" action="{{ route('edushare.index') }}" data-no-loading onsubmit="return false;" class="relative mb-5 sm:mb-7 sm:w-80">
        <input type="text" id="edushareSearchInput" name="search" value="{{ $search ?? '' }}" placeholder="Search files..." class="edushare-search w-full pl-10 pr-4 py-2.5 text-sm sm:text-base">
        <i class="ph ph-magnifying-glass absolute left-3.5 top-3 text-gray-400"></i>
    </form>

    <!-- Tabs -->
    <div class="mb-6 sm:mb-8">
        <div class="edushare-tabs bg-white rounded-lg shadow-sm border border-gray-200 p-2 relative">
            <nav class="flex space-x-1 sm:space-x-2 relative">
                <div id="tab-indicator" class="absolute h-full rounded-lg transition-all duration-300 ease-out" style="background: var(--primary-color); box-shadow: 0 4px 6px -1px rgba(var(--primary-color-rgb, 130, 56, 179), 0.3);"></div>
                <button onclick="switchTab('all')" id="tab-all" class="tab-button flex-1 py-2 sm:py-3 px-2 sm:px-4 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200 flex items-center justify-center relative z-10" type="button">
                    <i class="ph ph-globe mr-1 sm:mr-2" style="color: var(--complementary-color);"></i><span class="hidden sm:inline">All Files</span><span class="sm:hidden">All</span>
                </button>
                <button onclick="switchTab('my')" id="tab-my" class="tab-button flex-1 py-2 sm:py-3 px-2 sm:px-4 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200 flex items-center justify-center relative z-10" type="button">
                    <i class="ph ph-folder mr-1 sm:mr-2" style="color: var(--complementary-color);"></i><span class="hidden sm:inline">My Uploads</span><span class="sm:hidden">Mine</span>
                </button>
                <button onclick="switchTab('shared')" id="tab-shared" class="tab-button flex-1 py-2 sm:py-3 px-2 sm:px-4 rounded-lg font-medium text-xs sm:text-sm transition-all duration-200 flex items-center justify-center relative z-10" type="button">
                    <i class="ph ph-share-network mr-1 sm:mr-2" style="color: var(--complementary-color);"></i><span class="hidden sm:inline">Shared with Me</span><span class="sm:hidden">Shared</span>
                </button>
            </nav>
        </div>
    </div>

    <div id="studentEdushareContent" data-current-folder-id="{{ $currentFolder ? $currentFolder->id : '' }}" data-is-shared-folder="{{ $isSharedFolder ? 'true' : 'false' }}">
    <!-- All Files Tab -->
    <div id="content-all" class="tab-content" style="display: block;">
        @if($allFolders->count() > 0)
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Folders</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($allFolders as $folder)
                @php
                    // If folder is owned by user, go to "my" tab, otherwise go to "shared" tab
                    $targetTab = $folder->user_id === auth()->id() ? 'my' : 'shared';
                @endphp
                <a href="{{ route('edushare.index', ['folder' => $folder->id, 'tab' => $targetTab]) }}" data-edushare-navigation class="edushare-card bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 block" style="--edushare-index: {{ $loop->index }};">
                    <div class="flex items-center mb-3">
                        <i style="color: var(--complementary-color);" class="ph ph-folder  text-3xl mr-3"></i>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">{{ $folder->name }}</h3>
                            <p class="text-xs text-gray-500">{{ $folder->user->full_name }}</p>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        @if($allItems->count() > 0)
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Files</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                @foreach($allItems as $item)
                @include('student.edushare.partials.file-card', ['item' => $item, 'showOwner' => true, 'canDelete' => $item->user_id === auth()->id(), 'canRemove' => $item->user_id !== auth()->id()])
                @endforeach
            </div>
        </div>
        @endif
        @if($allFolders->count() == 0 && $allItems->count() == 0)
        <div class="edushare-empty bg-white rounded-lg shadow p-12 text-center">
            <i class="ph ph-folder-open text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">No files available yet.</p>
        </div>
        @endif
    </div>

    <!-- My Uploads Tab -->
    <div id="content-my" class="tab-content" style="display: none; overflow: visible;">
        @php
            // For My Uploads tab, only show folders/files that belong to the user
            // Ignore the current folder if it's a shared folder
            $myCurrentFolder = (!$isSharedFolder && $currentFolder) ? $currentFolder : null;
            $myBreadcrumbs = $myCurrentFolder ? $breadcrumbs : [];
        @endphp
        
        @if($myCurrentFolder || count($myBreadcrumbs) > 0)
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center text-sm text-gray-600">
                <a href="{{ route('edushare.index', ['tab' => 'my']) }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:"><i class="ph ph-folder mr-1"></i> My Uploads</a>
                @foreach($myBreadcrumbs as $crumb)
                <i class="ph ph-caret-right mx-2 text-gray-400"></i>
                <a href="{{ route('edushare.index', ['folder' => $crumb->id, 'tab' => 'my']) }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:">{{ $crumb->name }}</a>
                @endforeach
            </div>
            @if($myCurrentFolder)
            <a href="{{ $myCurrentFolder->parent_id ? route('edushare.index', ['folder' => $myCurrentFolder->parent_id, 'tab' => 'my']) : route('edushare.index', ['tab' => 'my']) }}" data-edushare-navigation class="edushare-back-btn inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm font-medium">
                <i class="ph ph-arrow-left mr-2"></i> Back
            </a>
            @endif
        </div>
        @endif

        @if($myFolders->count() > 0)
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">My Folders</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($myFolders as $folder)
                @include('student.edushare.partials.folder-card', ['folder' => $folder, 'canDelete' => true])
                @endforeach
            </div>
        </div>
        @endif
        
        @if($myItems->count() > 0)
        <div style="overflow: visible;" class="my-uploads-files" data-tab="my-uploads">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">My Files</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4" style="overflow: visible;">
                @foreach($myItems as $item)
                @include('student.edushare.partials.file-card', ['item' => $item, 'showOwner' => false, 'canDelete' => true, 'tabContext' => 'my'])
                @endforeach
            </div>
        </div>
        @endif
        
        @if($myFolders->count() == 0 && $myItems->count() == 0)
        <div class="edushare-empty bg-white rounded-lg shadow p-12 text-center">
            <i class="ph ph-folder-open text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">No uploads yet.</p>
            <button onclick="showModal('upload')" style="color: var(--primary-color);" class="mt-4  hover: font-semibold"><i class="ph ph-upload-simple mr-1"></i> Upload your first file</button>
        </div>
        @endif
    </div>

    <!-- Shared with Me Tab -->
    <div id="content-shared" class="tab-content" style="display: none;">
        @if($isSharedFolder)
            {{-- Viewing inside a shared folder --}}
            @if($currentFolder || count($breadcrumbs) > 0)
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-600">
                    <a href="{{ route('edushare.index', ['tab' => 'shared']) }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:"><i class="ph ph-share-network mr-1"></i> Shared with Me</a>
                    @foreach($breadcrumbs as $crumb)
                    <i class="ph ph-caret-right mx-2 text-gray-400"></i>
                    <a href="{{ route('edushare.index', ['folder' => $crumb->id, 'tab' => 'shared']) }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:">{{ $crumb->name }}</a>
                    @endforeach
                </div>
                @if($currentFolder)
                <a href="{{ $currentFolder->parent_id ? route('edushare.index', ['folder' => $currentFolder->parent_id, 'tab' => 'shared']) : route('edushare.index', ['tab' => 'shared']) }}" data-edushare-navigation class="edushare-back-btn inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm font-medium">
                    <i class="ph ph-arrow-left mr-2"></i> Back
                </a>
                @endif
            </div>
            @endif

            @if($sharedFolderContents->count() > 0)
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Folders</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                    @foreach($sharedFolderContents as $folder)
                    @include('student.edushare.partials.folder-card', ['folder' => $folder, 'canDelete' => false, 'canRemove' => true])
                    @endforeach
                </div>
            </div>
            @endif
            
            @if($sharedFileContents->count() > 0)
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Files</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    @foreach($sharedFileContents as $item)
                    @include('student.edushare.partials.file-card', ['item' => $item, 'showOwner' => true, 'canDelete' => false, 'canRemove' => true])
                    @endforeach
                </div>
            </div>
            @endif
            
            @if($sharedFolderContents->count() == 0 && $sharedFileContents->count() == 0)
            <div class="edushare-empty bg-white rounded-lg shadow p-12 text-center">
                <i class="ph ph-folder-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">This folder is empty.</p>
            </div>
            @endif
        @else
            {{-- Root shared items view --}}
            @if($sharedFolders->count() > 0)
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shared Folders</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                    @foreach($sharedFolders as $folder)
                    <div class="edushare-card bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 relative group" style="--edushare-index: {{ $loop->index }};">
                        <a href="{{ route('edushare.index', ['folder' => $folder->id, 'tab' => 'shared']) }}" data-edushare-navigation class="block">
                            <div class="flex items-center mb-3">
                                <i style="color: var(--complementary-color);" class="ph ph-folder  text-3xl mr-3"></i>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 truncate">{{ $folder->name }}</h3>
                                    <p class="text-xs text-gray-500">{{ $folder->user->full_name }}</p>
                                </div>
                            </div>
                        </a>
                        <button onclick="event.preventDefault(); openRemoveModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')" class="absolute top-2 right-2 text-gray-400 hover:text-red-600 p-1 transition opacity-0 group-hover:opacity-100" title="Remove">
                            <i class="ph ph-x"></i>
                        </button>
                        <div class="text-xs text-gray-500">
                            <i class="ph ph-clock mr-1"></i> {{ $folder->created_at->diffForHumans() }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @if($sharedItems->count() > 0)
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Shared Files</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    @foreach($sharedItems as $item)
                    @include('student.edushare.partials.file-card', ['item' => $item, 'showOwner' => true, 'canDelete' => false, 'canRemove' => true])
                    @endforeach
                </div>
            </div>
            @endif
            @if($sharedFolders->count() == 0 && $sharedItems->count() == 0)
            <div class="edushare-empty bg-white rounded-lg shadow p-12 text-center">
                <i class="ph ph-share-network text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No files shared with you yet.</p>
            </div>
            @endif
        @endif
    </div>
    </div>


    <!-- Upload Modal -->
    <div id="uploadModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('upload')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">New Upload</h2>
                    <button onclick="hideModal('upload')" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xl"></i></button>
                </div>
                <div class="mb-6 grid grid-cols-2 gap-4">
                    <button onclick="selectUploadType('file')" id="btn-file" class="upload-type-btn active p-4 border-2 rounded-lg text-center transition">
                        <i style="color: var(--primary-color);" class="ph ph-file text-3xl  mb-2"></i>
                        <p class="font-medium">Upload File</p>
                    </button>
                    <button onclick="selectUploadType('folder')" id="btn-folder" class="upload-type-btn p-4 border-2 border-gray-200 rounded-lg text-center transition hover:border-purple-300">
                        <i style="color: var(--complementary-color);" class="ph ph-folder text-3xl  mb-2"></i>
                        <p class="font-medium">New Folder</p>
                    </button>
                </div>
                <form id="fileForm" onsubmit="uploadFile(event)" data-no-loading>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Files <span class="text-red-500">*</span></label>
                            <div id="dropZoneStudent" style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover: transition cursor-pointer">
                                <input type="file" name="files[]" id="fileInputStudent" required class="hidden" multiple onchange="updateFileNameStudent(event)">
                                <label for="fileInputStudent" class="cursor-pointer block">
                                    <i class="ph ph-cloud-arrow-up text-4xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-700 font-medium mb-1">Click to upload or drag and drop</p>
                                    <p class="text-gray-500 text-sm">Up to {{ config('edushare.max_files') }} files, {{ config('edushare.max_file_kb') / 1024 }} MB per file and {{ config('edushare.max_total_kb') / 1024 }} MB total per upload</p>
                                </label>
                            </div>
                            <div id="fileListStudent" class="mt-3 space-y-2 max-h-64 overflow-y-auto hidden"></div>
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('upload')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="uploadFileBtn" class="px-4 py-2 text-white rounded-lg font-semibold" style="background-color: var(--primary-color);"><span id="uploadFileText"><i class="ph ph-upload-simple mr-2" style="color: var(--complementary-color);"></i>Upload</span></button>
                        </div>
                    </div>
                </form>
                <form id="folderForm" onsubmit="createFolder(event)" data-no-loading class="hidden">
                    <div class="space-y-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Folder Name <span class="text-red-500">*</span></label>
                        <input type="text" id="folderName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Enter folder name"></div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('upload')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="createFolderBtn" class="px-4 py-2 text-white rounded-lg font-semibold" style="background-color: var(--primary-color);"><span id="createFolderText"><i class="ph ph-folder-plus mr-2" style="color: var(--complementary-color);"></i>Create</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('rename')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Rename <span id="renameType">Item</span></h2>
                    <button onclick="hideModal('rename')" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form onsubmit="confirmRename(event)" data-no-loading>
                    <input type="hidden" id="renameItemType">
                    <input type="hidden" id="renameItemId">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Name <span class="text-red-500">*</span></label>
                            <input type="text" id="renameInput" required style="border-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:" placeholder="Enter new name">
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('rename')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="renameBtn" style="background-color: var(--primary-color);" class="px-4 py-2  hover: text-white rounded-lg font-semibold">
                                <i style="color: var(--complementary-color);" class="ph ph-check mr-2 "></i> Rename
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('delete')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-warning text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete <span id="deleteType">Item</span></h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete "<span id="deleteName" class="font-semibold"></span>"? This action cannot be undone.</p>
                    <div class="flex space-x-3">
                        <button onclick="hideModal('delete')" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button onclick="confirmDelete()" id="confirmDeleteBtn" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                            <i class="ph ph-trash mr-2"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Confirmation Modal -->
    <div id="removeModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('remove')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-warning text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Remove <span id="removeType">Item</span></h3>
                    <p class="text-gray-600 mb-6">Remove "<span id="removeName" class="font-semibold"></span>" from your shared items? You can access it again if it's shared with you later.</p>
                    <div class="flex space-x-3">
                        <button onclick="hideModal('remove')" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button onclick="confirmRemove()" id="confirmRemoveBtn" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                            <i class="ph ph-x mr-2"></i>Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View File Modal -->
    <div id="viewModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('view')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-4 border-b bg-white rounded-t-lg">
                    <div class="flex-1">
                        <h3 id="viewTitle" class="text-xl font-bold text-gray-900"></h3>
                        <p id="viewSubtitle" class="text-sm text-gray-600"></p>
                    </div>
                    <button onclick="hideModal('view')" class="text-gray-400 hover:text-gray-600 ml-4"><i class="ph ph-x text-2xl"></i></button>
                </div>
                <div id="viewContent" class="flex-1 overflow-hidden bg-gray-100"></div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="edushare-modal hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('share')" class="edushare-modal__backdrop fixed inset-0"></div>
            <div class="edushare-modal__surface relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Share with Others</h2>
                    <button onclick="hideModal('share')" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xl"></i></button>
                </div>
                
                <form onsubmit="shareItem(event)" data-no-loading>
                    <input type="hidden" id="shareItemType">
                    <input type="hidden" id="shareItemId">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Access Type</label>
                            <div id="shareTypeContainer" class="space-y-2">
                                <label class="share-type-option flex items-center p-3 border-2 rounded-lg cursor-pointer transition">
                                    <input type="radio" name="shareType" value="everyone" onchange="toggleEmailInputs()" class="mr-3">
                                    <div>
                                        <p class="font-medium">Everyone</p>
                                        <p class="text-xs text-gray-500">All registered students can access</p>
                                    </div>
                                </label>
                                <label class="share-type-option flex items-center p-3 border-2 rounded-lg cursor-pointer transition active">
                                    <input type="radio" name="shareType" value="specific" checked onchange="toggleEmailInputs()" class="mr-3">
                                    <div>
                                        <p class="font-medium">Specific Students</p>
                                        <p class="text-xs text-gray-500">Share with specific email addresses</p>
                                    </div>
                                </label>
                            </div>
                            <!-- Error message for share type -->
                            <div id="shareTypeError" class="hidden mt-2 text-sm text-red-600 flex items-start">
                                <i class="ph ph-warning-circle mt-0.5 mr-2"></i>
                                <span id="shareTypeErrorText"></span>
                            </div>
                        </div>
                        
                        <div id="emailInputsContainer">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Student Email <span class="text-red-500">*</span></label>
                            <div class="flex mb-2">
                                <input type="email" id="shareEmailInput" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-purple-500" placeholder="Enter email and press Add" required>
                                <button type="button" onclick="addEmailToList()" style="background-color: var(--primary-color);" class="px-4  text-white rounded-r-lg hover:">
                                    <i style="color: var(--complementary-color);" class="ph ph-plus "></i> Add
                                </button>
                            </div>
                            <!-- Error message for email input -->
                            <div id="emailInputError" class="hidden mb-2 text-sm text-red-600 flex items-start">
                                <i class="ph ph-warning-circle mt-0.5 mr-2"></i>
                                <span id="emailInputErrorText"></span>
                            </div>
                            <div id="emailChips" class="flex flex-wrap gap-2 mt-2"></div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('share')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="shareBtn" style="background-color: var(--primary-color);" class="px-4 py-2  hover: text-white rounded-lg font-semibold"><span id="shareText"><i class="ph ph-share-network mr-2 gold-icon"></i>Share</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="hidden fixed top-6 right-6 z-[9999] bg-white rounded-lg shadow-xl border-l-4 animate-slide-in">
        <div class="flex items-center gap-3 px-4 py-3 min-w-[300px] max-w-[400px]">
            <div class="flex-shrink-0">
                <div id="toastIcon" class="h-10 w-10 rounded-full flex items-center justify-center">
                    <i class="ph text-xl"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p id="toastTitle" class="text-sm font-semibold text-gray-900 mb-0.5"></p>
                <p id="toastMessage" class="text-sm text-gray-600 break-words"></p>
            </div>
            <button onclick="hideToast()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 ml-2">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
    </div>
</div>
</div>


<style>
.tab-content { overflow: visible !important; }
.dropdown-container { position: relative; z-index: 100; }
.dropdown-container-wrapper { position: relative !important; z-index: 100 !important; }
.dropdown-container-wrapper.dropdown-active { opacity: 1 !important; z-index: 1000 !important; }
#content-my .dropdown-menu.show,
#content-all .dropdown-menu.show,
#content-shared .dropdown-menu.show,
.dropdown-menu.show { 
    display: block !important; 
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
    position: absolute !important;
    right: 0 !important;
    top: 100% !important;
    z-index: 99999 !important;
    background: white !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important;
    border: 1px solid #e5e7eb !important;
    min-width: 150px !important;
}
.group:hover .opacity-0, 
.dropdown-menu.show ~ button, 
.dropdown-active,
.dropdown-container-wrapper.dropdown-active { 
    opacity: 1 !important; 
}
/* Keep dropdown container visible when active - GLOBAL RULE */
.dropdown-container-wrapper.dropdown-active,
.group:has(.dropdown-active) .dropdown-container-wrapper,
.my-uploads-files .dropdown-container-wrapper.dropdown-active,
[data-tab="my-uploads"] .dropdown-container-wrapper.dropdown-active {
    opacity: 1 !important;
    z-index: 10000 !important;
    visibility: visible !important;
}
@keyframes slide-in { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
.animate-slide-in { animation: slide-in 0.3s ease-out; }
.tab-button { color: #6B7280; background-color: transparent; transition: color 0.2s ease; }
.tab-button:hover:not(.active) { color: var(--complementary-color); }
.tab-button.active { color: white; }
/* Keep every EduShare tab icon on the complementary color in all states. */
.tab-button i,
.tab-button:hover i,
.tab-button.active i,
.tab-button.active:hover i { color: var(--complementary-color) !important; transition: color 0.2s ease; }
.upload-type-btn.active { border-color: var(--primary-color); background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.1); }
#tab-indicator { pointer-events: none; }
.dropdown-menu { display: none; position: absolute; right: 0; top: 100%; z-index: 9999; min-width: 150px; background: white; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
.dropdown-menu.show { display: block; }
.student-edushare-navigation-loading {
    pointer-events: none;
}

.student-edushare-skeleton {
    animation: student-edushare-skeleton-pulse 1.4s ease-in-out infinite;
}

.student-edushare-skeleton-block {
    background: #e5e7eb;
    border-radius: 0.5rem;
}

@keyframes student-edushare-skeleton-pulse {
    0%, 100% { opacity: 0.55; }
    50% { opacity: 1; }
}

.share-type-option { border-color: #d1d5db; }
.share-type-option.active { border-color: var(--primary-color); background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.1); }

/* Neumorphism */
.student-edushare-page {
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

html:has(.student-edushare-page),
body:has(.student-edushare-page),
main:has(.student-edushare-page) {
    background: var(--neu-bg) !important;
}

.student-edushare-page .text-gray-900, .student-edushare-page .text-gray-800,
.student-edushare-page .text-gray-700 { color: var(--neu-text) !important; }
.student-edushare-page .text-gray-600, .student-edushare-page .text-gray-500 { color: var(--neu-muted) !important; }
.student-edushare-page .text-gray-400, .student-edushare-page .text-gray-300 { color: var(--neu-faint) !important; }
.student-edushare-page .border-gray-200, .student-edushare-page .border-gray-300 { border-color: var(--neu-divider) !important; }

.student-edushare-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

.edushare-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
}

.edushare-hero__copy { max-width: 44rem; }
.edushare-eyebrow {
    margin: 0 0 .45rem;
    color: var(--primary-color, #7c3aed);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    line-height: 1.25;
    text-transform: uppercase;
}

.edushare-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
.edushare-hero__copy > p:last-child {
    max-width: 38rem;
    margin: .8rem 0 0;
    color: var(--neu-muted);
    font-size: clamp(.95rem, 2vw, 1.05rem);
    line-height: 1.55;
}

.edushare-search {
    border: 0;
    border-radius: 999px;
    background: var(--neu-bg);
    box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85);
    color: var(--neu-text);
    transition: box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease;
}
.edushare-search::placeholder { color: var(--neu-faint); }
.edushare-search:focus {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
    outline-offset: 2px;
    box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85);
}

.edushare-new-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    min-height: 2.9rem;
    padding: .42rem 1rem .42rem .5rem;
    border: 0;
    border-radius: 999px;
    background: var(--primary-color, #0071e3);
    box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
    color: #fff;
    font-size: .9rem;
    font-weight: 600;
    white-space: nowrap;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
}
.edushare-new-btn__icon {
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
.edushare-new-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.04);
    box-shadow: 7px 7px 16px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -7px -7px 16px rgba(255, 255, 255, .9);
}

.edushare-tabs {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
    animation: edushare-tabs-in .55s both cubic-bezier(.2, .8, .2, 1);
}
#tab-indicator {
    left: 4px;
    width: calc((100% - 12px) / 3);
    transform: none;
}
#tab-indicator.ind-my { left: calc(100% / 3 + 4px); }
#tab-indicator.ind-shared { left: calc(200% / 3 + 4px); }
@media (min-width: 640px) {
    #tab-indicator {
        left: 8px;
        width: calc((100% - 24px) / 3);
        transform: none;
    }
    #tab-indicator.ind-my { left: calc(100% / 3 + 8px); }
    #tab-indicator.ind-shared { left: calc(200% / 3 + 8px); }
}
.tab-button { border-radius: .9rem !important; }
.tab-button.active { font-weight: 600; }

.edushare-card {
    border: 0 !important;
    border-radius: 1.25rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
    transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1);
    animation: edushare-card-in .65s both cubic-bezier(.2, .8, .2, 1);
    animation-delay: calc(var(--edushare-index, 0) * 45ms);
}
.edushare-card:hover {
    transform: translateY(-3px);
    box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
}
#studentEdushareContent h2 { color: var(--neu-text); letter-spacing: -.01em; }
#studentEdushareContent .bg-white.rounded-lg.shadow.border {
    border: 0 !important;
    border-radius: 1.25rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
}
#studentEdushareContent .flex.space-x-2 > button {
    border-radius: .8rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease, filter .28s ease;
}
#studentEdushareContent .flex.space-x-2 > button:hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important; transform: translateY(-1px); }
#studentEdushareContent .flex.space-x-2 > a {
    border-radius: .8rem !important;
    box-shadow: 5px 5px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .3), -5px -5px 10px rgba(255, 255, 255, .85) !important;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s ease, filter .28s ease;
}
#studentEdushareContent .flex.space-x-2 > a:hover { transform: translateY(-1px); filter: brightness(1.03); }
.edushare-card .bg-purple-100 {
    background: rgba(var(--primary-color-rgb, 124, 58, 237), .10) !important;
    border-radius: .85rem !important;
    box-shadow: inset 3px 3px 6px rgba(var(--primary-color-rgb, 124, 58, 237), .12), inset -3px -3px 6px rgba(255, 255, 255, .7) !important;
}
.edushare-card h3 { color: var(--neu-text) !important; letter-spacing: -.01em !important; }

.edushare-back-btn {
    border-radius: 999px !important;
    border: 0 !important;
    background: var(--neu-bg) !important;
    box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
    font-weight: 600;
    transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s cubic-bezier(.2, .8, .2, 1), background-color .25s ease;
}
.edushare-back-btn:hover { transform: translateY(-2px); box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }

.edushare-empty {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8) !important;
}
.edushare-empty > i { color: rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important; }
.edushare-empty p { color: var(--neu-muted) !important; }

.edushare-modal:not(.hidden) { animation: edushare-backdrop-in .28s ease-out both; }
.edushare-modal {
    background: rgba(17, 17, 19, .46) !important;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
}
.edushare-modal__backdrop { background: transparent !important; }
.edushare-modal:not(.hidden) .edushare-modal__surface { animation: edushare-modal-in .42s cubic-bezier(.2, .8, .2, 1) both; }
.edushare-modal__surface {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: #ffffff !important;
    box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important;
}
#viewModal .edushare-modal__surface { border-radius: 1.25rem !important; }
.edushare-modal__surface h2, .edushare-modal__surface h3 { color: var(--neu-text) !important; letter-spacing: -.03em !important; }
.edushare-modal__surface > .border-b { border-color: var(--neu-divider) !important; background: transparent !important; }
.edushare-modal__surface .bg-gray-100 { background-color: #E3E9F1 !important; }
.edushare-modal button { transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease; }
.edushare-modal button[style*="background-color"] { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
.edushare-modal button[style*="background-color"]:hover { transform: translateY(-2px); filter: brightness(1.04); }
.edushare-modal button.bg-red-600 { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(220, 38, 38, .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
.edushare-modal button.bg-red-600:hover { transform: translateY(-2px); filter: brightness(1.04); }
.edushare-modal button.border, .edushare-modal button.border-2 {
    border: 0 !important;
    border-radius: .9rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-muted) !important;
    box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
}
.edushare-modal button.border:hover, .edushare-modal button.border-2:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }
.edushare-modal input[type="text"], .edushare-modal input[type="email"] {
    border: 0 !important;
    border-radius: .8rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
}
.edushare-modal input[type="text"]:focus, .edushare-modal input[type="email"]:focus {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
    outline-offset: 2px;
}
.edushare-modal .border-dashed {
    border: 2px dashed rgba(var(--primary-color-rgb, 124, 58, 237), .4) !important;
    border-radius: 1rem !important;
    background: rgba(var(--primary-color-rgb, 124, 58, 237), .04) !important;
    box-shadow: inset 3px 3px 8px rgba(var(--primary-color-rgb, 124, 58, 237), .10), inset -3px -3px 8px rgba(255, 255, 255, .6) !important;
}
.edushare-modal .upload-type-btn { border-radius: 1rem !important; }
.edushare-modal .upload-type-btn.active {
    border-color: var(--primary-color) !important;
    background: rgba(var(--primary-color-rgb, 124, 58, 237), .09) !important;
    box-shadow: inset 3px 3px 7px rgba(var(--primary-color-rgb, 124, 58, 237), .15), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
}
.edushare-modal .share-type-option {
    border: 0 !important;
    border-radius: 1rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 5px 5px 10px var(--neu-dark-shadow), -5px -5px 10px var(--neu-light-shadow) !important;
}
.edushare-modal .share-type-option.active {
    border-color: var(--primary-color) !important;
    background: rgba(var(--primary-color-rgb, 124, 58, 237), .09) !important;
    box-shadow: inset 3px 3px 7px rgba(var(--primary-color-rgb, 124, 58, 237), .15), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
}
.edushare-modal .bg-red-100 { background: rgba(220, 38, 38, .12) !important; box-shadow: 4px 4px 8px rgba(220, 38, 38, .18), -4px -4px 8px rgba(255, 255, 255, .9) !important; }

.edushare-new-btn:focus-visible, .edushare-search:focus-visible,
.edushare-back-btn:focus-visible, .edushare-modal button:focus-visible {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
    outline-offset: 3px;
}

.edushare-new-btn--desktop { display: none; }
@media (min-width: 640px) {
    .edushare-new-btn--desktop { display: inline-flex; }
    .edushare-new-btn--mobile { display: none; }
}

@keyframes edushare-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes edushare-tabs-in { from { opacity: 0; transform: translateY(10px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes edushare-backdrop-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes edushare-modal-in { from { opacity: 0; transform: translateY(18px) scale(.975); } to { opacity: 1; transform: translateY(0) scale(1); } }

@media (max-width: 640px) {
    .student-edushare-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
    .student-edushare-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
    .edushare-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
    .edushare-hero__copy { width: 100%; }
    .edushare-search { width: 100% !important; }
    .edushare-tabs { border-radius: 1.25rem !important; }
    .edushare-modal__surface { border-radius: 1.25rem !important; }
}

@media (prefers-reduced-motion: reduce) {
    .edushare-tabs, .edushare-card, .edushare-modal:not(.hidden),
    .edushare-modal:not(.hidden) .edushare-modal__surface { animation: none !important; }
    .edushare-card, .edushare-new-btn, .edushare-search, .edushare-back-btn,
    #studentEdushareContent .flex.space-x-2 > button,
    #studentEdushareContent .flex.space-x-2 > a { transition-duration: .01ms !important; }
}
</style>

<script>
// Wrap everything in an IIFE to avoid global variable conflicts
(function() {
    'use strict';
    
    // Local variables to avoid conflicts - all contained within IIFE
    const edushareConfig = {
        currentFolderId: {{ $currentFolder ? $currentFolder->id : 'null' }},
        isSharedFolder: {{ $isSharedFolder ? 'true' : 'false' }},
        currentTab: 'all',
        deleteItemId: null,
        deleteItemType: null,
        removeItemId: null,
        removeItemType: null,
        selectedFiles: [],
        shareEmails: [],
        confirmPopup: null,
        existingShareEmails: [],
        draggedItem: null
    };
    
    // Make essential functions globally accessible
    window.EduShareTabs = window.EduShareTabs || {};
    
    window.EduShareTabs.switchTab = function(tab) {
        console.log('switchTab called with:', tab);
        
        try {
            // Switch content without page reload (AJAX-style)
            switchTabContent(tab);
            updateTabIndicator(tab);
            updateNewButtonVisibility(tab);
            
            // Update config
            edushareConfig.currentTab = tab;
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            
            // Remove folder parameter when switching tabs (go to root of that tab)
            if (url.searchParams.has('folder')) {
                url.searchParams.delete('folder');
            }
            
            window.history.pushState({}, '', url);
            
            console.log('Successfully switched to tab:', tab);
        } catch (error) {
            console.error('Error switching tab:', error);
        }
    };
    
    // Make switchTab globally accessible for onclick handlers
    window.switchTab = window.EduShareTabs.switchTab;

    // Update the visible tab after an AJAX folder response without changing the URL again.
    window.EduShareTabs.setVisibleTab = function(tab) {
        switchTabContent(tab);
        updateTabIndicator(tab);
        updateNewButtonVisibility(tab);
        edushareConfig.currentTab = tab;
    };

    function switchTabContent(tab) {
        console.log('switchTabContent called with:', tab);
        
        try {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => { 
                el.style.display = 'none'; 
                console.log('Hiding tab content:', el.id);
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(el => { 
                el.classList.remove('active'); 
                console.log('Removing active from:', el.id);
            });
            
            // Show selected tab content
            const contentEl = document.getElementById('content-' + tab);
            if (contentEl) {
                contentEl.style.display = 'block';
                console.log('Showing tab content:', contentEl.id);
            } else {
                console.error('Content element not found:', 'content-' + tab);
            }
            
            // Add active class to selected tab button
            const tabEl = document.getElementById('tab-' + tab);
            if (tabEl) {
                tabEl.classList.add('active');
                console.log('Adding active to:', tabEl.id);
            } else {
                console.error('Tab element not found:', 'tab-' + tab);
            }
        } catch (error) {
            console.error('Error in switchTabContent:', error);
        }
    }
    
    function updateNewButtonVisibility(tab) {
        const newButtons = document.querySelectorAll('.edushare-new-btn');
        const content = document.getElementById('studentEdushareContent');
        const isSharedFolder = content
            ? content.dataset.isSharedFolder === 'true'
            : edushareConfig.isSharedFolder;
        // Show "New" button in "All Files" and "My Uploads" tabs, but NOT in "Shared with Me" or when in a shared folder
        const show = (tab === 'all' || tab === 'my') && !isSharedFolder;
        newButtons.forEach(btn => {
            // Clearing the inline display lets the CSS breakpoint rules decide
            // which of the two buttons (mobile/desktop) is visible.
            btn.style.display = show ? '' : 'none';
        });
    }
    
    function updateTabIndicator(tab) {
        const indicator = document.getElementById('tab-indicator');
        const activeTab = document.getElementById('tab-' + tab);
        if (!activeTab || !indicator) {
            console.log('Tab indicator elements not found:', { tab, activeTab, indicator });
            return;
        }

        try {
            // Tabs are equal width (flex-1), so the pill is moved with plain
            // CSS classes (addition/division only - no calc() multiplication,
            // which some browsers fail to parse). No pixel measurements that
            // can fail while the page is being swapped in by SPA navigation.
            const tabs = Array.from(activeTab.parentElement.children)
                .filter(el => el.classList.contains('tab-button'));
            const index = tabs.indexOf(activeTab);
            indicator.classList.toggle('ind-all', index === 0);
            indicator.classList.toggle('ind-my', index === 1);
            indicator.classList.toggle('ind-shared', index === 2);
            console.log('Tab indicator updated for tab:', tab, 'index:', index);
        } catch (error) {
            console.error('Error updating tab indicator:', error);
        }
    }
    
    function initializeTabIndicator() {
        const indicator = document.getElementById('tab-indicator');
        if (!indicator) return;
        
        // Determine which tab to show based on URL parameter or default to 'all'
        const urlParams = new URLSearchParams(window.location.search);
        let currentTab = urlParams.get('tab') || 'all';
        
        // If we're in a folder and it's a shared folder, show 'shared' tab
        if (edushareConfig.currentFolderId && edushareConfig.isSharedFolder) {
            currentTab = 'shared';
        } else if (edushareConfig.currentFolderId && !edushareConfig.isSharedFolder) {
            currentTab = 'my';
        }
        
        console.log('Initializing with tab:', currentTab);
        
        // The pill is visible by default (CSS positions it at tab 1), so
        // there is nothing to reveal - just pick the correct tab.
        switchTabContent(currentTab);
        updateTabIndicator(currentTab);
        
        // Store current tab
        edushareConfig.currentTab = currentTab;
        
        updateNewButtonVisibility(currentTab);
        
        // Add click handlers for dropdown management
        document.addEventListener('click', function(e) { 
            if (!e.target.closest('.dropdown-container')) { 
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show')); 
            } 
        });
        
        // Test button clicks
        console.log('Testing button clicks...');
        const buttons = document.querySelectorAll('.tab-button');
        buttons.forEach((btn, index) => {
            console.log(`Button ${index}:`, btn.id, btn.onclick);
            
            // Add event listener as backup
            btn.addEventListener('click', function(e) {
                console.log('Button clicked via event listener:', this.id);
                const tabName = this.id.replace('tab-', '');
                window.EduShareTabs.switchTab(tabName);
            });
        });
    }
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeTabIndicator();
    });
    
    
    
    // Handle resize events
    window.addEventListener('resize', () => {
        const currentActiveTab = edushareConfig.currentTab || 'all';
        setTimeout(() => updateTabIndicator(currentActiveTab), 50);
    });
    
    // Also handle when the page becomes visible (tab switching in browser)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            const currentActiveTab = edushareConfig.currentTab || 'all';
            setTimeout(() => updateTabIndicator(currentActiveTab), 100);
        }
    });

})(); // End IIFE

// Student Edushare folder navigation uses AJAX and browser history.
var studentEdushareRoutePath = '{{ parse_url(route('edushare.index'), PHP_URL_PATH) }}';

// Client-side cache so previously visited folders render instantly
// (folder clicks, back button, breadcrumbs, browser back/forward).
var studentEdushareContentCache = new Map();
var studentEdushareNavToken = 0;

function setStudentEdushareLoading(isLoading) {
    const content = document.getElementById('studentEdushareContent');
    if (!content) return;

    content.classList.toggle('student-edushare-navigation-loading', isLoading);
    content.setAttribute('aria-busy', isLoading ? 'true' : 'false');
}

function getVisibleStudentBreadcrumb(content) {
    const visibleTab = Array.from(content.querySelectorAll('.tab-content'))
        .find(tab => window.getComputedStyle(tab).display !== 'none');
    const breadcrumb = visibleTab ? visibleTab.querySelector('.mb-6.flex.items-center.justify-between') : null;

    return breadcrumb ? breadcrumb.outerHTML : '';
}

function showStudentEdushareSkeleton() {
    const content = document.getElementById('studentEdushareContent');
    if (!content) return;

    const breadcrumbMarkup = getVisibleStudentBreadcrumb(content);
    const folderSkeletons = Array.from({ length: 4 }, () => `
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200 student-edushare-skeleton">
            <div class="flex items-center mb-3">
                <div class="student-edushare-skeleton-block w-10 h-10 rounded-lg mr-3"></div>
                <div class="flex-1">
                    <div class="student-edushare-skeleton-block h-4 w-3/4 mb-2"></div>
                    <div class="student-edushare-skeleton-block h-3 w-1/2"></div>
                </div>
            </div>
            <div class="student-edushare-skeleton-block h-3 w-1/3"></div>
        </div>
    `).join('');
    const fileSkeletons = Array.from({ length: 6 }, () => `
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200 student-edushare-skeleton">
            <div class="flex items-start mb-4">
                <div class="student-edushare-skeleton-block w-10 h-10 rounded-lg mr-3"></div>
                <div class="flex-1">
                    <div class="student-edushare-skeleton-block h-4 w-4/5 mb-2"></div>
                    <div class="student-edushare-skeleton-block h-3 w-1/3"></div>
                </div>
            </div>
            <div class="student-edushare-skeleton-block h-3 w-1/2 mb-4"></div>
            <div class="student-edushare-skeleton-block h-9 w-full rounded-lg"></div>
        </div>
    `).join('');

    content.innerHTML = `${breadcrumbMarkup}
        <div class="mb-8">
            <div class="student-edushare-skeleton-block h-6 w-28 mb-4"></div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">${folderSkeletons}</div>
        </div>
        <div>
            <div class="student-edushare-skeleton-block h-6 w-20 mb-4"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">${fileSkeletons}</div>
        </div>`;
    setStudentEdushareLoading(true);
}

async function navigateStudentEdushare(url, updateHistory = true, showSkeleton = true) {
    const currentContent = document.getElementById('studentEdushareContent');
    if (!currentContent) return;

    const targetUrl = new URL(url, window.location.origin);
    const previousUrl = window.location.href;
    const previousContent = currentContent.innerHTML;
    const previousFolderId = currentContent.dataset.currentFolderId || '';
    const previousSharedState = currentContent.dataset.isSharedFolder || 'false';
    const activeTab = window.EduShareTabs.getCurrentTab() || 'all';
    const navToken = ++studentEdushareNavToken;

    const cacheKey = targetUrl.href;
    const cached = studentEdushareContentCache.get(cacheKey);

    const applyContent = (html, folderId, isShared) => {
        currentContent.innerHTML = html;
        currentContent.dataset.currentFolderId = folderId || '';
        currentContent.dataset.isSharedFolder = isShared || 'false';
        const requestedTab = targetUrl.searchParams.get('tab') || activeTab;
        const nextTab = folderId
            ? (isShared === 'true' ? 'shared' : 'my')
            : requestedTab;
        window.EduShareTabs.setVisibleTab(nextTab);
    };

    // Render from cache instantly (no skeleton), then quietly revalidate in the background.
    if (cached) {
        applyContent(cached.html, cached.folderId, cached.isShared);
        if (showSkeleton) window.scrollTo({ top: 0, behavior: 'auto' });
    } else if (showSkeleton) {
        // Folder navigation needs feedback, but CRUD refreshes should stay quiet
        // so a newly changed list appears in place without a loading skeleton.
        showStudentEdushareSkeleton();
    }

    if (updateHistory) {
        window.history.pushState(
            { ...(window.history.state || {}), studentEdushare: true },
            '',
            targetUrl.href
        );
    }

    try {
        const response = await fetch(targetUrl.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        });
        if (!response.ok) throw new Error('Failed to load folder');

        const pageHtml = await response.text();
        const pageDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
        const nextContent = pageDocument.getElementById('studentEdushareContent');
        if (!nextContent) throw new Error('Student folder content was not found');

        const nextHtml = nextContent.innerHTML;
        const nextFolderId = nextContent.dataset.currentFolderId || '';
        const nextSharedState = nextContent.dataset.isSharedFolder || 'false';
        studentEdushareContentCache.set(cacheKey, { html: nextHtml, folderId: nextFolderId, isShared: nextSharedState });

        // Ignore responses from older navigations (e.g. rapid folder clicks / back).
        if (navToken !== studentEdushareNavToken) return;

        applyContent(nextHtml, nextFolderId, nextSharedState);
        if (showSkeleton) window.scrollTo({ top: 0, behavior: 'auto' });
    } catch (error) {
        if (navToken !== studentEdushareNavToken) return;

        // On a cache hit the content is already on screen - keep it.
        if (cached) {
            showToast('Could not refresh folder contents', 'error');
        } else if (updateHistory) {
            currentContent.innerHTML = previousContent;
            currentContent.dataset.currentFolderId = previousFolderId;
            currentContent.dataset.isSharedFolder = previousSharedState;
            window.history.replaceState(window.history.state, '', previousUrl);
            showToast('Failed to load folder', 'error');
        } else {
            currentContent.innerHTML = previousContent;
            currentContent.dataset.currentFolderId = previousFolderId;
            currentContent.dataset.isSharedFolder = previousSharedState;
            showToast('Failed to refresh folder contents', 'error');
        }
    } finally {
        if (showSkeleton) setStudentEdushareLoading(false);
    }
}

function refreshStudentEdushareContent() {
    // Any mutation (upload, create folder, rename, delete, move, ...) changes the
    // list, so drop the cache to force a fresh fetch on the next navigation.
    studentEdushareContentCache.clear();
    return navigateStudentEdushare(window.location.href, false, false);
}

document.addEventListener('click', function(event) {
    const target = event.target instanceof Element ? event.target : null;
    const link = target ? target.closest('a[data-edushare-navigation]') : null;
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();
    navigateStudentEdushare(link.href);
});

window.addEventListener('popstate', function() {
    if (new URL(window.location.href).pathname === studentEdushareRoutePath) {
        navigateStudentEdushare(window.location.href, false);
    }
});

window.history.replaceState(
    { ...(window.history.state || {}), studentEdushare: true },
    '',
    window.location.href
);

// Seed the cache with the server-rendered initial content so even the
// first back navigation renders instantly.
(function() {
    const seedContent = document.getElementById('studentEdushareContent');
    if (!seedContent) return;
    studentEdushareContentCache.set(window.location.href, {
        html: seedContent.innerHTML,
        folderId: seedContent.dataset.currentFolderId || '',
        isShared: seedContent.dataset.isSharedFolder || 'false'
    });
})();

// Live search: filter results instantly as the user types (debounced),
// reusing the AJAX navigation so the page does not reload.
(function initStudentEdushareLiveSearch() {
    const searchInput = document.getElementById('edushareSearchInput');
    if (!searchInput) return;

    let searchTimer = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            const tab = (window.EduShareTabs && window.EduShareTabs.getCurrentTab()) || 'all';
            const url = new URL(studentEdushareRoutePath, window.location.origin);
            url.searchParams.set('tab', tab);
            const query = searchInput.value.trim();
            if (query) {
                url.searchParams.set('search', query);
            }
            navigateStudentEdushare(url.href, false, false);
        }, 300);
    });
})();

// Global functions that need to be accessible from HTML onclick handlers
function navigateToFolder(event, folderId, isOwnFolder) {
    event.preventDefault();
    
    // Determine which tab to use based on current tab and folder ownership
    let targetTab;
    if (!isOwnFolder) {
        // If folder is shared, always go to 'shared' tab
        targetTab = 'shared';
    } else {
        // If folder is owned by user, use current JavaScript tab (not URL tab)
        targetTab = window.EduShareTabs.getCurrentTab() === 'shared' ? 'my' : window.EduShareTabs.getCurrentTab();
    }
    
    // Navigate through the AJAX/history handler instead of reloading the whole page.
    const targetUrl = new URL('{{ route('edushare.index') }}', window.location.origin);
    targetUrl.searchParams.set('folder', folderId);
    targetUrl.searchParams.set('tab', targetTab);
    navigateStudentEdushare(targetUrl.href);
}

function showModal(type) { 
    // Prevent upload modal from opening when in shared folder or in "Shared with Me" tab
    if (type === 'upload' && (window.EduShareTabs.isSharedFolder() || window.EduShareTabs.getCurrentTab() === 'shared')) {
        showToast('Cannot upload to shared folders', 'error');
        return;
    }
    
    // Reset file selection when opening upload modal
    if (type === 'upload') {
        window.EduShareTabs.resetFileSelection();
    }
    
    document.getElementById(type + 'Modal').classList.remove('hidden'); 
}

function hideModal(type) { 
    document.getElementById(type + 'Modal').classList.add('hidden'); 
    
    // Reset file selection when closing upload modal
    if (type === 'upload') {
        window.EduShareTabs.resetFileSelection();
    }
}

function selectUploadType(type) {
    document.querySelectorAll('.upload-type-btn').forEach(btn => { btn.classList.remove('active', 'border-purple-500', 'bg-purple-50'); btn.classList.add('border-gray-200'); });
    if (type === 'file') { document.getElementById('btn-file').classList.add('active'); document.getElementById('fileForm').classList.remove('hidden'); document.getElementById('folderForm').classList.add('hidden'); }
    else { document.getElementById('btn-folder').classList.add('active'); document.getElementById('folderForm').classList.remove('hidden'); document.getElementById('fileForm').classList.add('hidden'); }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast'), icon = document.getElementById('toastIcon'), title = document.getElementById('toastTitle'), msg = document.getElementById('toastMessage');
    title.textContent = type === 'success' ? 'Success!' : 'Error!';
    msg.textContent = message;
    toast.classList.remove('hidden', 'border-green-500', 'border-red-500');
    icon.classList.remove('bg-green-100', 'bg-red-100');
    icon.querySelector('i').classList.remove('ph-check-circle', 'ph-warning-circle', 'text-green-600', 'text-red-600');
    if (type === 'success') { toast.classList.add('border-green-500'); icon.classList.add('bg-green-100'); icon.querySelector('i').classList.add('ph-check-circle', 'text-green-600'); }
    else { toast.classList.add('border-red-500'); icon.classList.add('bg-red-100'); icon.querySelector('i').classList.add('ph-warning-circle', 'text-red-600'); }
    setTimeout(() => hideToast(), 4000);
}
function hideToast() { document.getElementById('toast').classList.add('hidden'); }

function showPendingUploadSuccess() {
    const message = sessionStorage.getItem('studentEdushareUploadSuccess');
    if (!message) return;

    sessionStorage.removeItem('studentEdushareUploadSuccess');
    showToast(message, 'success');
}

document.addEventListener('DOMContentLoaded', showPendingUploadSuccess);

// Expose helper functions for global access
window.EduShareTabs.getCurrentTab = function() {
    return document.querySelector('.tab-button.active')?.id?.replace('tab-', '') || 'all';
};

window.EduShareTabs.isSharedFolder = function() {
    return document.getElementById('studentEdushareContent')?.dataset.isSharedFolder === 'true';
};

window.EduShareTabs.getCurrentFolderId = function() {
    const folderId = document.getElementById('studentEdushareContent')?.dataset.currentFolderId;
    return folderId || null;
};

window.EduShareTabs.resetFileSelection = function() {
    const fileInput = document.getElementById('fileInputStudent');
    if (fileInput) {
        fileInput.value = '';
        document.getElementById('fileListStudent').classList.add('hidden');
    }
};

function updateFileNameStudent(event) {
    window.EduShareTabs.updateFileNameStudent(event);
}

function removeFile(index) {
    window.EduShareTabs.removeFile(index);
}

function uploadFile(event) {
    window.EduShareTabs.uploadFile(event);
}

function createFolder(event) {
    window.EduShareTabs.createFolder(event);
}

function openRenameModal(type, id, currentName) {
    window.EduShareTabs.openRenameModal(type, id, currentName);
}

function confirmRename(event) {
    window.EduShareTabs.confirmRename(event);
}

function openDeleteModal(type, id, name) {
    window.EduShareTabs.openDeleteModal(type, id, name);
}

function confirmDelete() {
    window.EduShareTabs.confirmDelete();
}

function openRemoveModal(type, id, name) {
    window.EduShareTabs.openRemoveModal(type, id, name);
}

function confirmRemove() {
    window.EduShareTabs.confirmRemove();
}

function viewFile(id, title, filePath, fileType) {
    window.EduShareTabs.viewFile(id, title, filePath, fileType);
}

function toggleDropdown(id, event) {
    window.EduShareTabs.toggleDropdown(id, event);
}

function toggleEmailInputs() {
    window.EduShareTabs.toggleEmailInputs();
}

function addEmailToList() {
    window.EduShareTabs.addEmailToList();
}

function showUnshareConfirm(email, event) {
    window.EduShareTabs.showUnshareConfirm(email, event);
}

function closeUnshareConfirm() {
    window.EduShareTabs.closeUnshareConfirm();
}

function confirmRemoveEmail(email) {
    window.EduShareTabs.confirmRemoveEmail(email);
}

function openShareModal(type, id) {
    window.EduShareTabs.openShareModal(type, id);
}

function shareItem(event) {
    window.EduShareTabs.shareItem(event);
}

function handleDragStart(event) {
    window.EduShareTabs.handleDragStart(event);
}

function handleDragEnd(event) {
    window.EduShareTabs.handleDragEnd(event);
}

function handleDragOver(event) {
    return window.EduShareTabs.handleDragOver(event);
}

function handleDragLeave(event) {
    window.EduShareTabs.handleDragLeave(event);
}

function handleDrop(event, targetFolderId) {
    return window.EduShareTabs.handleDrop(event, targetFolderId);
}

// Initialize everything inside a new IIFE to contain all implementation details
(function() {
    'use strict';
    
    // Local variables for implementation
    let selectedFiles = [];
    const uploadMaxFiles = {{ config('edushare.max_files') }};
    const uploadMaxFileBytes = {{ config('edushare.max_file_kb') }} * 1024;
    const uploadMaxTotalBytes = {{ config('edushare.max_total_kb') }} * 1024;

    function uploadSelectionError(files) {
        if (files.length > uploadMaxFiles) return `You can upload up to ${uploadMaxFiles} files at a time.`;
        if (files.some(file => file.size > uploadMaxFileBytes)) return 'Each file must be 20 MB or smaller.';
        if (files.reduce((total, file) => total + file.size, 0) > uploadMaxTotalBytes) return 'The combined upload size must be 20 MB or smaller.';
        return null;
    }
    let shareEmails = [];
    let confirmPopup = null;
    let existingShareEmails = [];
    let draggedItem = null;
    let deleteItemId = null;
    let deleteItemType = null;
    let removeItemId = null;
    let removeItemType = null;

    // Expose functions through EduShareTabs
    window.EduShareTabs.updateFileNameStudent = function(event) {
        const newFiles = Array.from(event.target.files);
        const fileListDisplay = document.getElementById('fileListStudent');
        
        const nextFiles = [...selectedFiles];
        newFiles.forEach(file => {
            // Check if file already exists (by name and size)
            const exists = nextFiles.some(f => f.name === file.name && f.size === file.size);
            if (!exists) {
                nextFiles.push(file);
            }
        });

        const selectionError = uploadSelectionError(nextFiles);
        if (selectionError) {
            const previousSelection = new DataTransfer();
            selectedFiles.forEach(file => previousSelection.items.add(file));
            event.target.files = previousSelection.files;
            showToast(selectionError, 'error');
            return;
        }
        selectedFiles = nextFiles;
        
        // Update the file input with all selected files
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        event.target.files = dt.files;
        
        // Display all files
        if (selectedFiles.length > 0) {
            let fileNames = '';
            selectedFiles.forEach((file, i) => {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileExt = file.name.split('.').pop().toUpperCase();
                fileNames += `
                    <div class="flex items-center justify-between p-3 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i style="color: var(--primary-color);" class="ph ph-file "></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">${file.name}</p>
                                <p class="text-xs text-gray-500">${fileExt} • ${fileSize} MB</p>
                            </div>
                        </div>
                        <button type="button" onclick="removeFile(${i})" class="ml-2 text-red-500 hover:text-red-700">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                `;
            });
            fileListDisplay.innerHTML = fileNames;
            fileListDisplay.classList.remove('hidden');
        } else {
            fileListDisplay.classList.add('hidden');
        }
    };

    window.EduShareTabs.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        
        const fileInput = document.getElementById('fileInputStudent');
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
        
        window.EduShareTabs.updateFileNameStudent({ target: fileInput });
    };

    // Drag and drop functionality for student
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('dropZoneStudent');
        const fileInput = document.getElementById('fileInputStudent');
        
        if (dropZone && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.add('border-purple-500', 'bg-purple-50');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.remove('border-purple-500', 'bg-purple-50');
                }, false);
            });
            
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    window.EduShareTabs.updateFileNameStudent({ target: fileInput });
                }
            }, false);
        }
    });

    async function readStudentEdushareJson(response) {
        const rawBody = await response.text();
        let data;

        try {
            data = rawBody ? JSON.parse(rawBody) : {};
        } catch (error) {
            throw new Error('The server returned an invalid response. Please try again.');
        }

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : data.message;
            throw new Error(validationMessage || `Request failed (${response.status})`);
        }

        return data;
    }

    let isStudentUploadingFile = false;

    window.EduShareTabs.uploadFile = function(event) {
        event.preventDefault();

        // Prevent duplicate uploads from double-clicks or repeated Enter presses.
        if (isStudentUploadingFile) return;

        const selectionError = uploadSelectionError(Array.from(document.getElementById('fileInputStudent').files));
        if (selectionError) {
            showToast(selectionError, 'error');
            return;
        }

        const btn = document.getElementById('uploadFileBtn');
        const text = document.getElementById('uploadFileText');
        if (!btn || !text) return;

        isStudentUploadingFile = true;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('opacity-75', 'cursor-not-allowed');
        text.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Uploading...';

        const formData = new FormData(event.target);
        const currentFolderId = window.EduShareTabs.getCurrentFolderId();
        if (currentFolderId) formData.append('folder_id', currentFolderId);

        fetch('{{ route('edushare.store') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(readStudentEdushareJson)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to upload');

            hideModal('upload');
            showToast(data.message);
            return refreshStudentEdushareContent();
        })
        .catch(error => {
            showToast(error.message || 'Failed to upload', 'error');
        })
        .finally(() => {
            isStudentUploadingFile = false;
            btn.disabled = false;
            btn.setAttribute('aria-busy', 'false');
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            text.innerHTML = '<i style="color: var(--complementary-color);" class="ph ph-upload-simple mr-2"></i>Upload';
        });
    };

    window.EduShareTabs.createFolder = function(event) {
        event.preventDefault();
        const btn = document.getElementById('createFolderBtn'), text = document.getElementById('createFolderText');
        btn.disabled = true; btn.classList.add('opacity-75', 'cursor-not-allowed');
        text.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Creating...';
        const formData = new FormData();
        formData.append('name', document.getElementById('folderName').value);
        const currentFolderId = window.EduShareTabs.getCurrentFolderId();
        if (currentFolderId) formData.append('parent_id', currentFolderId);
        fetch('{{ route('edushare.folder.create') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(readStudentEdushareJson)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to create folder');

            hideModal('upload');
            showToast(data.message);
            return refreshStudentEdushareContent();
        })
        .catch(error => {
            showToast(error.message || 'Failed to create folder', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            text.innerHTML = '<i style="color: var(--complementary-color);" class="ph ph-folder-plus mr-2 "></i>Create';
        });
    };

    window.EduShareTabs.openRenameModal = function(type, id, currentName) {
        // Close any open dropdowns first
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
        document.querySelectorAll('.dropdown-container-wrapper.dropdown-active').forEach(wrapper => {
            wrapper.classList.remove('dropdown-active');
        });
        
        document.getElementById('renameType').textContent = type === 'folder' ? 'Folder' : 'File';
        document.getElementById('renameInput').value = currentName;
        document.getElementById('renameItemType').value = type;
        document.getElementById('renameItemId').value = id;
        showModal('rename');
    };

    window.EduShareTabs.confirmRename = function(event) {
        event.preventDefault();
        const type = document.getElementById('renameItemType').value;
        const id = document.getElementById('renameItemId').value;
        const newName = document.getElementById('renameInput').value;
        const btn = document.getElementById('renameBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Renaming...';
        
        const url = type === 'folder' 
            ? `/edushare/folder/${id}/rename`
            : `/edushare/item/${id}/rename`;
        
        fetch(url, {
            method: 'POST',
            headers: { 
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: newName })
        })
        .then(response => response.json())
        .then(data => {
            hideModal('rename');
            if (data.success) {
                showToast(data.message);
                return refreshStudentEdushareContent();
            } else {
                showToast(data.message || 'Failed to rename', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(() => {
            hideModal('rename');
            showToast('Failed to rename', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    };

    window.EduShareTabs.openDeleteModal = function(type, id, name) {
        // Close any open dropdowns first
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
        document.querySelectorAll('.dropdown-container-wrapper.dropdown-active').forEach(wrapper => {
            wrapper.classList.remove('dropdown-active');
        });
        
        deleteItemType = type; 
        deleteItemId = id;
        document.getElementById('deleteType').textContent = type === 'folder' ? 'Folder' : 'File';
        document.getElementById('deleteName').textContent = name;
        showModal('delete');
    };

    let isStudentDeletingItem = false;

    window.EduShareTabs.confirmDelete = function() {
        if (isStudentDeletingItem) return;

        const btn = document.getElementById('confirmDeleteBtn');
        const originalText = btn.innerHTML;
        isStudentDeletingItem = true;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2" style="color: var(--complementary-color);"></i>Deleting...';

        const url = deleteItemType === 'folder'
            ? `/edushare/folder/${deleteItemId}`
            : `/edushare/item/${deleteItemId}`;

        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(readStudentEdushareJson)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to delete');

            hideModal('delete');
            showToast(data.message);
            return refreshStudentEdushareContent();
        })
        .catch(error => {
            showToast(error.message || 'Failed to delete', 'error');
        })
        .finally(() => {
            isStudentDeletingItem = false;
            btn.disabled = false;
            btn.setAttribute('aria-busy', 'false');
            btn.innerHTML = originalText;
        });
    };

    window.EduShareTabs.openRemoveModal = function(type, id, name) {
        removeItemType = type; 
        removeItemId = id;
        document.getElementById('removeType').textContent = type === 'folder' ? 'Folder' : 'File';
        document.getElementById('removeName').textContent = name;
        showModal('remove');
    };

    window.EduShareTabs.confirmRemove = function() {
        const btn = document.getElementById('confirmRemoveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2" style="color: var(--complementary-color);"></i>Removing...';
        const url = `/edushare/remove-share`;
        const formData = new FormData();
        if (removeItemType === 'folder') formData.append('folder_id', removeItemId);
        else formData.append('item_id', removeItemId);
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(readStudentEdushareJson)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to remove');

            hideModal('remove');
            showToast(data.message);
            return refreshStudentEdushareContent();
        })
        .catch(error => {
            showToast(error.message || 'Failed to remove', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-x mr-2"></i>Remove';
        });
    };

    window.EduShareTabs.viewFile = function(id, title, filePath, fileType) {
        document.getElementById('viewTitle').textContent = title;
        document.getElementById('viewSubtitle').textContent = fileType.toUpperCase() + ' File';
        const content = document.getElementById('viewContent');
        const ext = fileType.toLowerCase();
        const url = `/edushare/${id}/view`; // Use Laravel route to proxy the file
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) { 
            content.innerHTML = `<div class="w-full h-full flex items-center justify-center p-4"><img src="${url}" class="max-w-full max-h-full object-contain rounded-lg shadow-lg" alt="${title}"></div>`; 
        }
        else if (ext === 'pdf') { 
            content.innerHTML = `<iframe src="${url}" class="w-full h-full border-0"></iframe>`; 
        }
        else if (['mp4','mov','webm','avi','mkv','m4v','ogv'].includes(ext)) {
            content.innerHTML = `<div class="w-full h-full flex items-center justify-center p-4 bg-black"><video src="${url}" controls autoplay class="max-w-full max-h-full rounded-lg"></video></div>`;
        }
        else if (['ppt','pptx','doc','docx','xls','xlsx'].includes(ext)) {
            const icons = {ppt:'ph-file-ppt text-orange-600',pptx:'ph-file-ppt text-orange-600',doc:'ph-file-doc text-blue-600',docx:'ph-file-doc text-blue-600',xls:'ph-file-xls text-green-600',xlsx:'ph-file-xls text-green-600'};
            content.innerHTML = `<div class="w-full h-full flex items-center justify-center p-8"><div class="text-center bg-white rounded-lg shadow-lg p-8 max-w-lg"><div class="mb-6"><i class="ph ${icons[ext]} text-7xl mb-4"></i></div><h4 class="text-2xl font-bold text-gray-900 mb-3">${title}</h4><p class="text-gray-600 mb-2">${fileType.toUpperCase()} File</p><div class="space-y-3 mt-6"><a href="/edushare/${id}/download" style="background-color: var(--primary-color);" class="block w-full px-6 py-3  text-white rounded-lg hover: transition"><i class="ph ph-download-simple mr-2 gold-icon"></i>Download to View</a></div><p class="text-xs text-gray-500 mt-4"><i class="ph ph-info mr-1"></i>This file needs to be downloaded to view properly</p></div></div>`;
        }
        else { 
            content.innerHTML = `<div class="w-full h-full flex items-center justify-center p-8"><div class="text-center bg-white rounded-lg shadow-lg p-8 max-w-lg"><i class="ph ph-file text-6xl text-gray-300 mb-4"></i><p class="text-gray-500 text-lg mb-2">Preview not available</p><p class="text-sm text-gray-400 mb-6">This file type cannot be previewed in the browser</p><a href="/edushare/${id}/download" style="background-color: var(--primary-color);" class="inline-block px-6 py-3  text-white rounded-lg hover: transition"><i class="ph ph-download-simple mr-2 gold-icon"></i>Download File</a></div></div>`;
        }
        showModal('view');
    };

    window.EduShareTabs.toggleDropdown = function(id, event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        // Close all other dropdowns and remove active class from containers
        document.querySelectorAll('.dropdown-menu').forEach(m => { 
            if (m.id !== 'dropdown-' + id) {
                m.classList.remove('show');
                const container = m.closest('.dropdown-container-wrapper');
                if (container) container.classList.remove('dropdown-active');
            }
        });
        
        // Toggle this dropdown
        const dropdown = document.getElementById('dropdown-' + id);
        if (dropdown) {
            const isShowing = dropdown.classList.toggle('show');
            const container = dropdown.closest('.dropdown-container-wrapper');
            if (container) {
                if (isShowing) {
                    container.classList.add('dropdown-active');
                    // Remove opacity classes that interfere
                    container.classList.remove('opacity-0');
                    container.style.opacity = '1';
                } else {
                    container.classList.remove('dropdown-active');
                    // Restore opacity classes
                    container.classList.add('opacity-0');
                    container.style.opacity = '';
                }
            }
            
            // Debug info
            const computedStyle = window.getComputedStyle(dropdown);
            console.log('Dropdown toggled:', id, isShowing);
            console.log('Display:', computedStyle.display);
            console.log('Visibility:', computedStyle.visibility);
            console.log('Opacity:', computedStyle.opacity);
            console.log('Z-index:', computedStyle.zIndex);
            console.log('Position:', computedStyle.position);
            console.log('Container:', container);
            console.log('Parent tab:', dropdown.closest('.tab-content')?.id);
        } else {
            console.error('Dropdown not found:', 'dropdown-' + id);
        }
    };

    window.EduShareTabs.toggleEmailInputs = function() {
        const shareType = document.querySelector('input[name="shareType"]:checked').value;
        const container = document.getElementById('emailInputsContainer');
        const input = document.getElementById('shareEmailInput');
        
        // Update radio button styles
        document.querySelectorAll('.share-type-option').forEach(label => {
            const radio = label.querySelector('input[type="radio"]');
            if (radio.checked) {
                label.classList.add('active', 'border-purple-500', 'bg-purple-50');
                label.classList.remove('border-gray-300');
            } else {
                label.classList.remove('active', 'border-purple-500', 'bg-purple-50');
                label.classList.add('border-gray-300');
            }
        });
        
        if (shareType === 'everyone') {
            container.classList.add('hidden');
            input.required = false;
        } else {
            container.classList.remove('hidden');
            input.required = shareEmails.length === 0;
        }
    };

    window.EduShareTabs.addEmailToList = function() {
        const input = document.getElementById('shareEmailInput');
        const email = input.value.trim();
        
        if (!email) return;
        
        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showToast('Please enter a valid email address', 'error');
            return;
        }
        
        // Check if email already added
        if (shareEmails.includes(email)) {
            showToast('Email already added', 'error');
            return;
        }
        
        shareEmails.push(email);
        input.value = '';
        input.required = false;
        renderEmailChips();
    };

    window.EduShareTabs.showUnshareConfirm = function(email, event) {
        event.stopPropagation();
        
        // If email is not in database yet (newly added), just remove it directly
        if (!existingShareEmails.includes(email)) {
            shareEmails = shareEmails.filter(e => e !== email);
            document.getElementById('shareEmailInput').required = shareEmails.length === 0;
            renderEmailChips();
            return;
        }
        
        // Remove any existing popup
        if (confirmPopup) {
            confirmPopup.remove();
        }
        
        // Get button position
        const button = event.currentTarget;
        const rect = button.getBoundingClientRect();
        
        // Create popup
        confirmPopup = document.createElement('div');
        confirmPopup.className = 'fixed z-[9999] bg-white rounded-lg shadow-2xl border-2 border-purple-200 p-4';
        confirmPopup.style.left = `${rect.right + 10}px`;
        confirmPopup.style.top = `${rect.top - 10}px`;
        confirmPopup.style.width = '220px';
        confirmPopup.style.animation = 'popIn 0.2s ease-out';
        
        confirmPopup.innerHTML = `
            <style>
                @keyframes popIn {
                    from { transform: scale(0.8) translateX(-20px); opacity: 0; }
                    to { transform: scale(1) translateX(0); opacity: 1; }
                }
            </style>
            <p class="text-sm font-semibold text-gray-800 mb-3">Unshare with this user?</p>
            <div class="flex gap-2">
                <button onclick="confirmRemoveEmail('${email}')" style="background-color: var(--primary-color);" class="flex-1  hover: text-white px-3 py-1.5 rounded text-sm font-semibold transition">
                    Yes
                </button>
                <button onclick="closeUnshareConfirm()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-sm font-semibold transition">
                    No
                </button>
            </div>
        `;
        
        document.body.appendChild(confirmPopup);
        
        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', window.EduShareTabs.closeUnshareConfirm);
        }, 100);
    };

    window.EduShareTabs.closeUnshareConfirm = function() {
        if (confirmPopup) {
            confirmPopup.style.animation = 'popOut 0.15s ease-in';
            setTimeout(() => {
                if (confirmPopup) {
                    confirmPopup.remove();
                    confirmPopup = null;
                }
            }, 150);
        }
        document.removeEventListener('click', window.EduShareTabs.closeUnshareConfirm);
    };

    window.EduShareTabs.confirmRemoveEmail = async function(email) {
        const type = document.getElementById('shareItemType').value;
        const id = document.getElementById('shareItemId').value;
        
        // If we have an item/folder ID, this is an existing share - call API to unshare
        if (id && type) {
            try {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                formData.append('email', email);
                if (type === 'item') {
                    formData.append('item_id', id);
                } else {
                    formData.append('folder_id', id);
                }
                
                const response = await fetch('{{ route("edushare.unshare.email") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Failed to unshare', 'error');
                }
            } catch (error) {
                console.error('Error unsharing:', error);
                showToast('Failed to unshare', 'error');
            }
        }
        
        // Remove from local array
        shareEmails = shareEmails.filter(e => e !== email);
        document.getElementById('shareEmailInput').required = shareEmails.length === 0;
        renderEmailChips();
        window.EduShareTabs.closeUnshareConfirm();
    };

    function renderEmailChips() {
        const container = document.getElementById('emailChips');
        container.innerHTML = shareEmails.map(email => `
            <div style="color: var(--primary-color);" class="inline-flex items-center bg-purple-100  px-3 py-1 rounded-full text-sm relative">
                <span>${email}</span>
                <button type="button" onclick="showUnshareConfirm('${email}', event)" style="color: var(--primary-color);" class="ml-2  hover: transition">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        `).join('');
    }

    window.EduShareTabs.openShareModal = function(type, id) {
        // Close all dropdowns first
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.dropdown-container-wrapper').forEach(c => {
            c.classList.remove('dropdown-active');
            c.classList.add('opacity-0');
            c.style.opacity = '';
        });
        
        document.getElementById('shareItemType').value = type;
        document.getElementById('shareItemId').value = id;
        
        // Reset shareEmails to prevent duplicates
        shareEmails = [];
        existingShareEmails = [];
        
        // Load existing shares
        fetch(`{{ route('edushare.shares.get') }}?type=${type}&id=${id}`, {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Use Set to remove duplicates, then convert back to array
                shareEmails = [...new Set(data.emails || [])];
                existingShareEmails = [...shareEmails]; // Track existing emails from database
                
                if (data.share_type === 'everyone') {
                    document.querySelector('input[name="shareType"][value="everyone"]').checked = true;
                } else {
                    document.querySelector('input[name="shareType"][value="specific"]').checked = true;
                }
                
                document.getElementById('shareEmailInput').value = '';
                document.getElementById('shareEmailInput').required = shareEmails.length === 0;
                renderEmailChips();
                window.EduShareTabs.toggleEmailInputs();
            }
        })
        .catch(err => {
            console.error('Error loading shares:', err);
            // Reset form on error
            shareEmails = [];
            existingShareEmails = [];
            document.querySelector('input[name="shareType"][value="specific"]').checked = true;
            document.getElementById('shareEmailInput').value = '';
            document.getElementById('shareEmailInput').required = true;
            renderEmailChips();
            window.EduShareTabs.toggleEmailInputs();
        });
        
        showModal('share');
    };

    // Allow Enter key to add email
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('shareEmailInput');
        if (input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.EduShareTabs.addEmailToList();
                }
            });
        }
    });

    function showShareError(message, isEmailError = true) {
        // Clear previous errors
        hideShareError();
        
        if (isEmailError) {
            // Show error below email input
            const errorDiv = document.getElementById('emailInputError');
            const errorText = document.getElementById('emailInputErrorText');
            const emailInput = document.getElementById('shareEmailInput');
            
            errorText.textContent = message;
            errorDiv.classList.remove('hidden');
            
            // Make email input red only
            emailInput.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            emailInput.classList.remove('border-gray-300', 'focus:ring-purple-500', 'focus:border-purple-500');
        } else {
            // Show error below share type
            const errorDiv = document.getElementById('shareTypeError');
            const errorText = document.getElementById('shareTypeErrorText');
            const shareTypeContainer = document.getElementById('shareTypeContainer');
            
            errorText.textContent = message;
            errorDiv.classList.remove('hidden');
            
            // Make share type options red
            shareTypeContainer.querySelectorAll('label').forEach(label => {
                label.classList.add('border-red-500');
                label.classList.remove('border-gray-300');
            });
        }
    }

    function hideShareError() {
        // Hide email error
        const emailErrorDiv = document.getElementById('emailInputError');
        const emailInput = document.getElementById('shareEmailInput');
        
        if (emailErrorDiv) emailErrorDiv.classList.add('hidden');
        if (emailInput) {
            emailInput.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            emailInput.classList.add('border-gray-300', 'focus:ring-purple-500', 'focus:border-purple-500');
        }
        
        // Hide share type error
        const shareTypeErrorDiv = document.getElementById('shareTypeError');
        const shareTypeContainer = document.getElementById('shareTypeContainer');
        
        if (shareTypeErrorDiv) shareTypeErrorDiv.classList.add('hidden');
        if (shareTypeContainer) {
            shareTypeContainer.querySelectorAll('label').forEach(label => {
                label.classList.remove('border-red-500');
            });
        }
    }

    window.EduShareTabs.shareItem = function(event) {
        event.preventDefault();
        hideShareError();
        
        const shareType = document.querySelector('input[name="shareType"]:checked').value;
        
        if (shareType === 'specific' && shareEmails.length === 0) {
            showShareError('Please add at least one email address', true);
            return;
        }
        
        const btn = document.getElementById('shareBtn'), text = document.getElementById('shareText');
        btn.disabled = true; 
        text.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Sharing...';
        
        const formData = new FormData();
        const type = document.getElementById('shareItemType').value;
        const id = document.getElementById('shareItemId').value;
        
        if (type === 'item') formData.append('item_id', id); else formData.append('folder_id', id);
        formData.append('share_type', shareType);
        
        if (shareType === 'specific') {
            // Only send NEW emails (not already in database)
            const newEmails = shareEmails.filter(email => !existingShareEmails.includes(email));
            
            if (newEmails.length === 0 && existingShareEmails.length > 0) {
                // No new emails to share
                showToast('Already shared with these users', 'error');
                btn.disabled = false;
                text.innerHTML = '<i class="ph ph-share-network mr-2 gold-icon"></i>Share';
                return;
            }
            
            newEmails.forEach((email, index) => {
                formData.append(`emails[${index}]`, email);
            });
        }
        
        fetch('{{ route('edushare.share') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: formData })
        .then(r => {
            if (!r.ok) {
                return r.json().then(err => { throw err; });
            }
            return r.json();
        })
        .then(data => { 
            if (data.success) {
                // Update existingShareEmails to include newly shared emails
                if (shareType === 'specific') {
                    existingShareEmails = [...shareEmails]; // All current emails are now existing
                }
                hideModal('share');
                showToast(data.message);
            } else {
                // Show error in modal, don't close it
                let msg = data.message;
                if (data.invalid_emails) msg += ': ' + data.invalid_emails.join(', ');
                if (data.already_shared) msg += ': ' + data.already_shared.join(', ');
                
                // Determine if it's an email error or share type error
                const isEmailError = shareType === 'specific';
                showShareError(msg, isEmailError);
            }
            btn.disabled = false; 
            text.innerHTML = '<i class="ph ph-share-network mr-2 gold-icon"></i>Share';
        })
        .catch(err => { 
            console.error('Share error:', err);
            // Show error in modal, don't close it
            const errorMsg = err.message || (err.errors ? Object.values(err.errors).flat().join(', ') : 'Failed to share');
            showShareError(errorMsg);
            btn.disabled = false; 
            text.innerHTML = '<i class="ph ph-share-network mr-2 gold-icon"></i>Share';
        });
    };

    // Drag and Drop functionality
    window.EduShareTabs.handleDragStart = function(event) {
        draggedItem = {
            type: event.target.dataset.type,
            id: event.target.dataset.id
        };
        
        // Create custom drag image
        const dragImage = document.createElement('div');
        dragImage.style.cssText = `
            position: absolute;
            top: -1000px;
            background: white;
            border: 2px solid #8B5CF6;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        `;
        
        // Get the actual icon from the card (PDF, Excel, Word, Folder, etc.)
        const iconElement = event.target.querySelector('i[class*="ph-file"], i[class*="ph-folder"]');
        const iconClasses = iconElement ? iconElement.className : 'ph ph-file text-purple-600';
        
        dragImage.innerHTML = `
            <div style="background: #F3E8FF; padding: 12px; border-radius: 8px;">
                <i class="${iconClasses}" style="font-size: 24px;"></i>
            </div>
            <div style="font-weight: 600; color: #1F2937;">
                ${event.target.dataset.type === 'folder' ? 'Moving folder...' : 'Moving file...'}
            </div>
        `;
        
        document.body.appendChild(dragImage);
        event.dataTransfer.setDragImage(dragImage, 100, 30);
        
        // Remove drag image after a short delay
        setTimeout(() => {
            document.body.removeChild(dragImage);
        }, 0);
        
        event.target.style.opacity = '0.5';
        event.dataTransfer.effectAllowed = 'move';
    };

    window.EduShareTabs.handleDragEnd = function(event) {
        event.target.style.opacity = '1';
        // Remove all drag-over classes
        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.classList.remove('border-purple-500', 'bg-purple-50');
        });
    };

    window.EduShareTabs.handleDragOver = function(event) {
        if (event.preventDefault) {
            event.preventDefault();
        }
        event.dataTransfer.dropEffect = 'move';
        
        // Add visual feedback
        const dropZone = event.currentTarget;
        if (dropZone.classList.contains('drop-zone')) {
            dropZone.classList.add('border-purple-500', 'bg-purple-50');
        }
        
        return false;
    };

    window.EduShareTabs.handleDragLeave = function(event) {
        event.currentTarget.classList.remove('border-purple-500', 'bg-purple-50');
    };

    window.EduShareTabs.handleDrop = function(event, targetFolderId) {
        if (event.stopPropagation) {
            event.stopPropagation();
        }
        
        event.currentTarget.classList.remove('border-purple-500', 'bg-purple-50');
        
        if (!draggedItem) return false;
        
        // Don't allow dropping folder into itself
        if (draggedItem.type === 'folder' && draggedItem.id == targetFolderId) {
            showToast('Cannot move folder into itself', 'error');
            return false;
        }
        
        // Move the item
        moveItemToFolder(draggedItem.type, draggedItem.id, targetFolderId);
        
        return false;
    };

    function moveItemToFolder(type, itemId, targetFolderId) {
        const url = type === 'folder' 
            ? `/edushare/folder/${itemId}/move`
            : `/edushare/item/${itemId}/move`;
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ target_folder_id: targetFolderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                return refreshStudentEdushareContent();
            } else {
                showToast(data.message || 'Failed to move', 'error');
            }
        })
        .catch(() => {
            showToast('Failed to move item', 'error');
        });
    }

})(); // End second IIFE
</script>
@endsection
