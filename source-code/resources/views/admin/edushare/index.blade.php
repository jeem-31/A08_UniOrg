@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'EduShare - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">EduShare</h1>
            <p class="text-gray-600 mt-2">Share files and folders with students</p>
        </div>
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" style="background-color: var(--primary-color);" class="hover: text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
                <i style="color: var(--complementary-color);" class="ph ph-plus mr-2 "></i> New
                <i class="ph ph-caret-down ml-2 text-sm"></i>
            </button>
            <div x-show="open" @click.away="open = false" 
                 class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                 style="display: none;">
                <div class="py-1">
                    <button onclick="showModal('upload')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 flex items-center transition">
                        <i style="color: var(--primary-color);" class="ph ph-file-arrow-up mr-3 "></i>
                        <div>
                            <div class="font-medium">Upload File</div>
                            <div class="text-xs text-gray-500">Upload a new file</div>
                        </div>
                    </button>
                    <button onclick="showModal('createFolder')" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 flex items-center transition">
                        <i style="color: var(--complementary-color);" class="ph ph-folder-plus mr-3 "></i>
                        <div>
                            <div class="font-medium">New Folder</div>
                            <div class="text-xs text-gray-500">Create a new folder</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="edushareContent" data-current-folder-id="{{ $currentFolder ? $currentFolder->id : '' }}">
    <!-- Breadcrumbs -->
    <div id="edushareBreadcrumbs">
    @if($currentFolder || count($breadcrumbs) > 0)
    <div class="mb-6 flex items-center text-sm text-gray-600">
        <a href="{{ route('admin.edushare.index') }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-house mr-1"></i> Home
        </a>
        @foreach($breadcrumbs as $crumb)
            <i class="ph ph-caret-right mx-2 text-gray-400"></i>
            <a href="{{ route('admin.edushare.index', ['folder' => $crumb->id]) }}" data-edushare-navigation style="color: var(--primary-color);" class="hover:">
                {{ $crumb->name }}
            </a>
        @endforeach
    </div>
    @endif
    </div>

    <!-- Folders Grid -->
    @if($folders->count() > 0)
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Folders</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($folders as $folder)
            <div data-edushare-folder-id="{{ $folder->id }}" class="bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 relative group">
                <a href="{{ route('admin.edushare.index', ['folder' => $folder->id]) }}" data-edushare-navigation class="block">
                    <div class="flex items-center mb-3">
                        <i style="color: var(--complementary-color);" class="ph ph-folder  text-3xl mr-3"></i>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">{{ $folder->name }}</h3>
                        </div>
                    </div>
                </a>
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="relative dropdown-container-wrapper">
                        <button onclick="toggleDropdown(event, 'folder-{{ $folder->id }}')" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                            <i class="ph ph-dots-three-vertical"></i>
                        </button>
                        <div id="dropdown-folder-{{ $folder->id }}" class="dropdown-menu hidden absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <button onclick="openRenameModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i class="ph ph-pencil-simple mr-2 text-blue-600"></i> Rename
                            </button>
                            <button onclick="openShareModal('folder', {{ $folder->id }})" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i style="color: var(--primary-color);" class="ph ph-share-network mr-2 "></i> Share
                            </button>
                            <button onclick="openDeleteModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i class="ph ph-trash mr-2 text-red-600"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="ph ph-clock mr-1"></i> {{ $folder->created_at->diffForHumans() }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Files Grid -->
    @if($items->count() > 0)
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Files</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($items as $item)
            <div data-edushare-item-id="{{ $item->id }}" class="bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 relative group">
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="relative dropdown-container-wrapper">
                        <button onclick="toggleDropdown(event, 'item-{{ $item->id }}')" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                            <i class="ph ph-dots-three-vertical"></i>
                        </button>
                        <div id="dropdown-item-{{ $item->id }}" class="dropdown-menu hidden absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <button onclick="openRenameModal('item', {{ $item->id }}, '{{ addslashes($item->title) }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i class="ph ph-pencil-simple mr-2 text-blue-600"></i> Rename
                            </button>
                            <button onclick="openShareModal('item', {{ $item->id }})" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i style="color: var(--primary-color);" class="ph ph-share-network mr-2 "></i> Share
                            </button>
                            <button onclick="openDeleteModal('item', {{ $item->id }}, '{{ addslashes($item->title) }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center">
                                <i class="ph ph-trash mr-2 text-red-600"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex items-start mb-3">
                    <div class="bg-purple-100 p-3 rounded-lg mr-3 flex-shrink-0">
                        @php
                            $icon = 'ph-file';
                            $color = 'text-gray-600';
                            if(in_array($item->file_type, ['pdf'])) {
                                $icon = 'ph-file-pdf';
                                $color = 'text-red-600';
                            } elseif(in_array($item->file_type, ['doc', 'docx'])) {
                                $icon = 'ph-file-doc';
                                $color = 'text-blue-600';
                            } elseif(in_array($item->file_type, ['xls', 'xlsx'])) {
                                $icon = 'ph-file-xls';
                                $color = 'text-green-600';
                            } elseif(in_array($item->file_type, ['ppt', 'pptx'])) {
                                $icon = 'ph-file-ppt';
                                $color = 'text-orange-600';
                            } elseif(in_array($item->file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
                                $icon = 'ph-file-image';
                                $color = 'text-purple-600';
                            } elseif(in_array($item->file_type, ['zip', 'rar'])) {
                                $icon = 'ph-file-zip';
                                $color = 'text-yellow-600';
                            }
                        @endphp
                        <i class="ph {{ $icon }} {{ $color }} text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900 truncate">{{ $item->title }}</h3>
                        <p class="text-xs text-gray-400 mt-1">{{ strtoupper($item->file_type) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                    <span><i class="ph ph-clock mr-1"></i> {{ $item->created_at->diffForHumans() }}</span>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.edushare.download', $item) }}" style="background-color: var(--primary-color);" class="flex-1  hover: text-white px-3 py-2 rounded-lg text-center text-sm font-medium">
                        <i style="color: var(--complementary-color);" class="ph ph-download-simple mr-1 "></i> Download
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @elseif($folders->count() == 0)
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <i class="ph ph-folder-open text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">No files in this folder yet.</p>
        <button onclick="showModal('upload')" style="color: var(--primary-color);" class="mt-4  hover: font-semibold">
            <i class="ph ph-upload-simple mr-1"></i> Upload your first file
        </button>
    </div>
    @endif

    </div>

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('createFolder')" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Create New Folder</h2>
                    <button onclick="hideModal('createFolder')" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form onsubmit="createFolder(event)" data-no-loading>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Folder Name <span class="text-red-500">*</span></label>
                            <input type="text" id="folderName" required style="border-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:" placeholder="Enter folder name">
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('createFolder')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="createFolderBtn" style="background-color: var(--primary-color);" class="px-4 py-2 hover: text-white rounded-lg font-semibold transition disabled:opacity-75 disabled:cursor-not-allowed">
                                <span id="createFolderText"><i style="color: var(--complementary-color);" class="ph ph-folder-plus mr-2"></i>Create Folder</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('upload')" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Upload File</h2>
                    <button onclick="hideModal('upload')" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form onsubmit="uploadFile(event)" enctype="multipart/form-data" data-no-loading>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File <span class="text-red-500">*</span></label>
                            <div id="dropZone" style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover: transition cursor-pointer">
                                <input type="file" name="file" id="fileInput" required class="hidden" onchange="updateFileName(event)">
                                <label for="fileInput" class="cursor-pointer block">
                                    <i class="ph ph-cloud-arrow-up text-3xl text-gray-400 mb-1"></i>
                                    <p class="text-gray-600 text-sm font-medium">Click to upload or drag and drop</p>
                                    <p class="text-xs text-gray-500 mt-1">Maximum {{ config('edushare.max_file_kb') / 1024 }} MB per file</p>
                                    <p id="fileName" style="color: var(--primary-color);" class=" text-sm mt-1 font-medium hidden"></p>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="hideModal('upload')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" id="uploadFileBtn" style="background-color: var(--primary-color);" class="px-4 py-2 hover: text-white rounded-lg font-semibold transition disabled:opacity-75 disabled:cursor-not-allowed">
                                <span id="uploadFileText"><i style="color: var(--complementary-color);" class="ph ph-upload-simple mr-2"></i>Upload</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('share')" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Share with Students</h2>
                    <button onclick="hideModal('share')" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                
                <form onsubmit="shareItem(event)" data-no-loading>
                    <input type="hidden" id="shareItemType">
                    <input type="hidden" id="shareItemId">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Share Type</label>
                            <div id="shareTypeContainer" class="space-y-2">
                                <label class="share-type-option flex items-center p-3 border-2 rounded-lg cursor-pointer transition">
                                    <input type="radio" name="shareType" value="everyone" class="mr-3" onchange="toggleEmailInputs()">
                                    <div>
                                        <p class="font-medium">Everyone</p>
                                        <p class="text-xs text-gray-500">All registered students can access</p>
                                    </div>
                                </label>
                                <label class="share-type-option flex items-center p-3 border-2 rounded-lg cursor-pointer transition active">
                                    <input type="radio" name="shareType" value="specific" checked class="mr-3" onchange="toggleEmailInputs()">
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
                                <input type="email" id="shareEmailInput" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-purple-500" placeholder="Enter email and press Add">
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
                            <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2  hover: text-white rounded-lg font-semibold">
                                <i style="color: var(--complementary-color);" class="ph ph-share-network mr-2 "></i> Share
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('rename')" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
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
    <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="hideModal('delete')" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
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

    <!-- Toast Notification -->
    <div id="toast" class="hidden fixed top-6 right-6 z-50 bg-white rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md border-l-4">
        <div class="flex-shrink-0">
            <div id="toastIcon" class="h-10 w-10 rounded-full flex items-center justify-center">
                <i class="ph text-xl"></i>
            </div>
        </div>
        <div class="flex-1">
            <p id="toastTitle" class="text-sm font-semibold text-gray-900"></p>
            <p id="toastMessage" class="text-sm text-gray-600"></p>
        </div>
        <button onclick="hideToast()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
</div>

<style>
#edushareContent {
    position: relative;
    transition: opacity 0.15s ease;
}

#edushareContent.edushare-navigation-loading {
    opacity: 1;
    pointer-events: none;
}

.edushare-skeleton {
    animation: edushare-skeleton-pulse 1.4s ease-in-out infinite;
}

.edushare-skeleton-block {
    background: #e5e7eb;
    border-radius: 0.5rem;
}

.edushare-skeleton-folder {
    height: 7.5rem;
}

.edushare-skeleton-file {
    height: 10.5rem;
}

@keyframes edushare-skeleton-pulse {
    0%, 100% { opacity: 0.55; }
    50% { opacity: 1; }
}

.share-type-option {
    border-color: #d1d5db;
}
.share-type-option.active {
    border-color: #8B5CF6;
    background-color: #F3E8FF;
}
.dropdown-menu {
    display: none;
}
.dropdown-menu.show {
    display: block;
}
</style>

<script>
let currentFolderId = {{ $currentFolder ? $currentFolder->id : 'null' }};
const edushareRoutePath = '{{ parse_url(route('admin.edushare.index'), PHP_URL_PATH) }}';

function showModal(type) {
    document.getElementById(type + 'Modal').classList.remove('hidden');
}

function hideModal(type) {
    document.getElementById(type + 'Modal').classList.add('hidden');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = document.getElementById('toastIcon');
    const title = document.getElementById('toastTitle');
    const msg = document.getElementById('toastMessage');
    
    title.textContent = type === 'success' ? 'Success!' : 'Error!';
    msg.textContent = message;
    
    toast.classList.remove('hidden', 'border-green-500', 'border-red-500');
    icon.classList.remove('bg-green-100', 'bg-red-100');
    icon.querySelector('i').classList.remove('ph-check-circle', 'ph-warning-circle', 'text-green-600', 'text-red-600');
    
    if (type === 'success') {
        toast.classList.add('border-green-500');
        icon.classList.add('bg-green-100');
        icon.querySelector('i').classList.add('ph-check-circle', 'text-green-600');
    } else {
        toast.classList.add('border-red-500');
        icon.classList.add('bg-red-100');
        icon.querySelector('i').classList.add('ph-warning-circle', 'text-red-600');
    }
    
    setTimeout(() => hideToast(), 4000);
}

function hideToast() {
    document.getElementById('toast').classList.add('hidden');
}

function showPendingUploadSuccess() {
    const message = sessionStorage.getItem('adminEdushareUploadSuccess');
    if (!message) return;

    sessionStorage.removeItem('adminEdushareUploadSuccess');
    showToast(message, 'success');
}

document.addEventListener('DOMContentLoaded', showPendingUploadSuccess);

let edushareNavigationLoading = false;

function isEdushareUrl(url) {
    return new URL(url, window.location.origin).pathname === edushareRoutePath;
}

function setEdushareNavigationLoading(isLoading) {
    const content = document.getElementById('edushareContent');
    if (!content) return;

    content.classList.toggle('edushare-navigation-loading', isLoading);
    content.setAttribute('aria-busy', isLoading ? 'true' : 'false');
}

function showEdushareSkeleton() {
    const content = document.getElementById('edushareContent');
    if (!content) return;

    const folderSkeletons = Array.from({ length: 4 }, () => `
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200 edushare-skeleton">
            <div class="flex items-center mb-3">
                <div class="edushare-skeleton-block w-12 h-12 rounded-lg mr-3"></div>
                <div class="flex-1">
                    <div class="edushare-skeleton-block h-4 w-3/4 mb-2"></div>
                    <div class="edushare-skeleton-block h-3 w-1/2"></div>
                </div>
            </div>
            <div class="edushare-skeleton-block h-3 w-1/3"></div>
        </div>
    `).join('');

    const fileSkeletons = Array.from({ length: 6 }, () => `
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200 edushare-skeleton">
            <div class="flex items-start mb-4">
                <div class="edushare-skeleton-block w-12 h-12 rounded-lg mr-3"></div>
                <div class="flex-1">
                    <div class="edushare-skeleton-block h-4 w-4/5 mb-2"></div>
                    <div class="edushare-skeleton-block h-3 w-1/3"></div>
                </div>
            </div>
            <div class="edushare-skeleton-block h-3 w-1/2 mb-4"></div>
            <div class="edushare-skeleton-block h-9 w-full rounded-lg"></div>
        </div>
    `).join('');

    const currentBreadcrumbs = document.getElementById('edushareBreadcrumbs');
    const breadcrumbMarkup = currentBreadcrumbs ? currentBreadcrumbs.outerHTML : '';

    content.innerHTML = `${breadcrumbMarkup}
        <div class="mb-8">
            <div class="edushare-skeleton-block h-6 w-24 mb-4"></div>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">${folderSkeletons}</div>
        </div>
        <div>
            <div class="edushare-skeleton-block h-6 w-20 mb-4"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${fileSkeletons}</div>
        </div>
    `;
    setEdushareNavigationLoading(true);
}

async function navigateEdushare(url, updateHistory = true) {
    if (edushareNavigationLoading) return;

    const currentContent = document.getElementById('edushareContent');
    if (!currentContent) return;

    const targetUrl = new URL(url, window.location.origin);
    const previousUrl = window.location.href;
    const previousContent = currentContent.innerHTML;
    const previousFolderId = currentContent.dataset.currentFolderId || '';
    edushareNavigationLoading = true;

    // Render immediately so navigation feels instant while the next folder loads.
    showEdushareSkeleton();

    if (updateHistory) {
        window.history.pushState(
            { ...(window.history.state || {}), edushare: true },
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
        const nextContent = pageDocument.getElementById('edushareContent');

        if (!nextContent) throw new Error('Folder content was not found');

        currentContent.innerHTML = nextContent.innerHTML;
        currentContent.dataset.currentFolderId = nextContent.dataset.currentFolderId || '';
        currentFolderId = currentContent.dataset.currentFolderId || null;
        window.scrollTo({ top: 0, behavior: 'auto' });
    } catch (error) {
        if (updateHistory) {
            currentContent.innerHTML = previousContent;
            currentContent.dataset.currentFolderId = previousFolderId;
            currentFolderId = previousFolderId || null;
            window.history.replaceState(window.history.state, '', previousUrl);
            showToast('Failed to load folder', 'error');
        } else {
            // Restore the normal page if browser history navigation could not load.
            window.location.reload();
        }
    } finally {
        edushareNavigationLoading = false;
        setEdushareNavigationLoading(false);
    }
}

document.addEventListener('click', function(event) {
    const target = event.target instanceof Element ? event.target : null;
    const link = target ? target.closest('a[data-edushare-navigation]') : null;

    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();
    navigateEdushare(link.href);
});

window.addEventListener('popstate', function() {
    if (isEdushareUrl(window.location.href)) {
        navigateEdushare(window.location.href, false);
    } else {
        window.location.reload();
    }
});

window.history.replaceState(
    { ...(window.history.state || {}), edushare: true },
    '',
    window.location.href
);

let isCreatingFolder = false;

function setCreateFolderLoading(isLoading) {
    const button = document.getElementById('createFolderBtn');
    const buttonText = document.getElementById('createFolderText');

    if (!button || !buttonText) return;

    button.disabled = isLoading;
    button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    buttonText.innerHTML = isLoading
        ? '<i class="ph ph-spinner ph-spin mr-2" style="color: var(--complementary-color);"></i>Creating...'
        : '<i class="ph ph-folder-plus mr-2" style="color: var(--complementary-color);"></i>Create Folder';
}

function createFolder(event) {
    event.preventDefault();

    // Prevent duplicate requests from double-clicks or repeated Enter presses.
    if (isCreatingFolder) return;

    isCreatingFolder = true;
    setCreateFolderLoading(true);

    const formData = new FormData();
    formData.append('name', document.getElementById('folderName').value);
    if (currentFolderId) formData.append('parent_id', currentFolderId);

    fetch('{{ route('admin.edushare.folder.create') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideModal('createFolder');
        if (data.success) {
            showToast(data.message, 'success');
            window.clearAdminPageCache && window.clearAdminPageCache();
            window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
        } else {
            showToast(data.message || 'Failed to create folder', 'error');
        }
    })
    .catch(() => {
        hideModal('createFolder');
        showToast('Failed to create folder', 'error');
    })
    .finally(() => {
        isCreatingFolder = false;
        setCreateFolderLoading(false);
    });
}

function updateFileName(event) {
    const file = event.target.files[0];
    const fileNameDisplay = document.getElementById('fileName');
    if (file) {
        fileNameDisplay.textContent = '📎 ' + file.name;
        fileNameDisplay.classList.remove('hidden');
    } else {
        fileNameDisplay.classList.add('hidden');
    }
}

// Drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    
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
                updateFileName({ target: fileInput });
            }
        }, false);
    }
});

let isUploadingFile = false;

async function readAdminEdushareJson(response) {
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

function setUploadFileLoading(isLoading) {
    const button = document.getElementById('uploadFileBtn');
    const buttonText = document.getElementById('uploadFileText');

    if (!button || !buttonText) return;

    button.disabled = isLoading;
    button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    buttonText.innerHTML = isLoading
        ? '<i class="ph ph-spinner ph-spin mr-2" style="color: var(--complementary-color);"></i>Uploading...'
        : '<i class="ph ph-upload-simple mr-2" style="color: var(--complementary-color);"></i>Upload';
}

function uploadFile(event) {
    event.preventDefault();

    // Prevent duplicate uploads from double-clicks or repeated Enter presses.
    if (isUploadingFile) return;

    const selectedFile = event.target.querySelector('input[type="file"]').files[0];
    if (selectedFile && selectedFile.size > {{ config('edushare.max_file_kb') }} * 1024) {
        showToast('The file must be 20 MB or smaller.', 'error');
        return;
    }

    isUploadingFile = true;
    setUploadFileLoading(true);

    const formData = new FormData(event.target);
    if (currentFolderId) formData.append('folder_id', currentFolderId);

    fetch('{{ route('admin.edushare.upload') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(readAdminEdushareJson)
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Failed to upload file');

        hideModal('upload');
        showToast(data.message, 'success');
        window.clearAdminPageCache && window.clearAdminPageCache();
        window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
    })
    .catch(error => {
        showToast(error.message || 'Failed to upload file', 'error');
    })
    .finally(() => {
        isUploadingFile = false;
        setUploadFileLoading(false);
    });
}

function openShareModal(type, id) {
    document.getElementById('shareItemType').value = type;
    document.getElementById('shareItemId').value = id;
    
    // Reset form
    shareEmails = [];
    renderEmailChips();
    document.getElementById('shareEmailInput').value = '';
    hideShareError();
    
    // Set default to "Specific Students"
    document.querySelector('input[name="shareType"][value="specific"]').checked = true;
    toggleEmailInputs();
    
    // Load existing shares
    fetch(`{{ route('edushare.shares.get') }}?type=${type}&id=${id}`, {
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.share_type === 'everyone') {
                document.querySelector('input[name="shareType"][value="everyone"]').checked = true;
                shareEmails = [];
            } else if (data.share_type === 'specific' && data.emails) {
                document.querySelector('input[name="shareType"][value="specific"]').checked = true;
                shareEmails = data.emails;
            }
            renderEmailChips();
            toggleEmailInputs();
        }
    })
    .catch(err => {
        console.error('Error loading shares:', err);
    });
    
    showModal('share');
}

let shareEmails = [];

function toggleEmailInputs() {
    const shareType = document.querySelector('input[name="shareType"]:checked').value;
    const container = document.getElementById('emailInputsContainer');
    const input = document.getElementById('shareEmailInput');
    
    // Update radio button styling
    document.querySelectorAll('.share-type-option').forEach(label => {
        const radio = label.querySelector('input[type="radio"]');
        if (radio.checked) {
            label.classList.add('active');
            label.style.borderColor = '#8B5CF6';
            label.style.backgroundColor = '#F3E8FF';
        } else {
            label.classList.remove('active');
            label.style.borderColor = '#d1d5db';
            label.style.backgroundColor = 'transparent';
        }
    });
    
    if (shareType === 'everyone') {
        container.classList.add('hidden');
        if (input) input.required = false;
    } else {
        container.classList.remove('hidden');
        if (input) input.required = shareEmails.length === 0;
    }
}

function addEmailToList() {
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
}

function removeEmailFromList(email) {
    shareEmails = shareEmails.filter(e => e !== email);
    document.getElementById('shareEmailInput').required = shareEmails.length === 0;
    renderEmailChips();
}

function renderEmailChips() {
    const container = document.getElementById('emailChips');
    container.innerHTML = shareEmails.map(email => `
        <div style="color: var(--primary-color);" class="inline-flex items-center bg-purple-100  px-3 py-1 rounded-full text-sm">
            <span>${email}</span>
            <button type="button" onclick="removeEmailFromList('${email}')" style="color: var(--primary-color);" class="ml-2  hover:">
                <i class="ph ph-x"></i>
            </button>
        </div>
    `).join('');
}

function showShareError(message, isEmailError = true) {
    // Clear previous errors
    hideShareError();
    
    const shareType = document.querySelector('input[name="shareType"]:checked').value;
    
    if (isEmailError && shareType === 'specific') {
        // Show error below email inputs
        const errorDiv = document.getElementById('emailInputError');
        const errorText = document.getElementById('emailInputErrorText');
        const emailInputs = document.querySelectorAll('#emailList input[type="email"]');
        
        errorText.textContent = message;
        errorDiv.classList.remove('hidden');
        
        // Make all email inputs red
        emailInputs.forEach(input => {
            input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            input.classList.remove('border-gray-300', 'focus:ring-purple-500', 'focus:border-purple-500');
        });
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
        });
    }
}

function hideShareError() {
    // Hide email error
    const emailErrorDiv = document.getElementById('emailInputError');
    const emailInputs = document.querySelectorAll('#emailList input[type="email"]');
    
    if (emailErrorDiv) emailErrorDiv.classList.add('hidden');
    emailInputs.forEach(input => {
        input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
        input.classList.add('border-gray-300', 'focus:ring-purple-500', 'focus:border-purple-500');
    });
    
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

function shareItem(event) {
    event.preventDefault();
    hideShareError();
    
    const shareType = document.querySelector('input[name="shareType"]:checked').value;
    
    if (shareType === 'specific' && shareEmails.length === 0) {
        showShareError('Please add at least one email address', true);
        return;
    }
    
    const formData = new FormData();
    const type = document.getElementById('shareItemType').value;
    const id = document.getElementById('shareItemId').value;
    
    if (type === 'item') formData.append('item_id', id);
    else formData.append('folder_id', id);
    formData.append('share_type', shareType);
    
    if (shareType === 'specific') {
        shareEmails.forEach((email, index) => {
            formData.append(`emails[${index}]`, email);
        });
    }

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Sharing...';

    fetch('{{ route('admin.edushare.share') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            hideModal('share');
        } else {
            // Show error in modal, don't close it
            let msg = data.message;
            if (data.invalid_emails) msg += ': ' + data.invalid_emails.join(', ');
            if (data.already_shared) msg += ': ' + data.already_shared.join(', ');
            
            // Determine if it's an email error or share type error
            const isEmailError = shareType === 'specific';
            showShareError(msg, isEmailError);
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(err => {
        console.error('Share error:', err);
        // Show error in modal, don't close it
        const errorMsg = err.message || (err.errors ? Object.values(err.errors).flat().join(', ') : 'Failed to share');
        showShareError(errorMsg);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

let deleteItemType = '';
let deleteItemId = null;
let renameItemType = '';
let renameItemId = null;

function toggleDropdown(event, id) {
    event.stopPropagation();
    event.preventDefault();
    
    const dropdown = document.getElementById('dropdown-' + id);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    
    // Close all other dropdowns
    allDropdowns.forEach(d => {
        if (d !== dropdown) {
            d.classList.add('hidden');
            d.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('hidden');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown-container-wrapper')) {
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.classList.add('hidden');
            dropdown.classList.remove('show');
        });
    }
});

function openRenameModal(type, id, currentName) {
    renameItemType = type;
    renameItemId = id;
    document.getElementById('renameType').textContent = type === 'folder' ? 'Folder' : 'File';
    document.getElementById('renameInput').value = currentName;
    document.getElementById('renameItemType').value = type;
    document.getElementById('renameItemId').value = id;
    showModal('rename');
}

function confirmRename(event) {
    event.preventDefault();
    const newName = document.getElementById('renameInput').value;
    const btn = document.getElementById('renameBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Renaming...';
    
    const url = renameItemType === 'folder' 
        ? `/admin/edushare/folder/${renameItemId}/rename`
        : `/admin/edushare/item/${renameItemId}/rename`;
    
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
        if (data.success) {
            showToast(data.message, 'success');
            hideModal('rename');
            window.clearAdminPageCache && window.clearAdminPageCache();
            window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
        } else {
            showToast(data.message || 'Failed to rename', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(() => {
        showToast('Failed to rename', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function openDeleteModal(type, id, name) {
    deleteItemType = type;
    deleteItemId = id;
    document.getElementById('deleteType').textContent = type === 'folder' ? 'Folder' : 'File';
    document.getElementById('deleteName').textContent = name;
    showModal('delete');
}

let isDeletingEdushareItem = false;

function removeDeletedEdushareCard(type, id) {
    const attribute = type === 'folder' ? 'data-edushare-folder-id' : 'data-edushare-item-id';
    const card = document.querySelector(`[${attribute}="${id}"]`);
    if (card) card.remove();
}

function confirmDelete() {
    if (isDeletingEdushareItem) return;

    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    let deleteSucceeded = false;
    isDeletingEdushareItem = true;
    window.adminEdushareDeleteInProgress = true;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Deleting...';
    
    const url = deleteItemType === 'folder' 
        ? `/admin/edushare/folder/${deleteItemId}`
        : `/admin/edushare/item/${deleteItemId}`;
    
    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(readAdminEdushareJson)
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Failed to delete');

        deleteSucceeded = true;
        removeDeletedEdushareCard(deleteItemType, deleteItemId);
        showToast(data.message, 'success');
        hideModal('delete');

        // Ignore the echo event generated by this same delete request.
        setTimeout(() => {
            window.adminEdushareDeleteInProgress = false;
        }, 3000);
    })
    .catch(error => {
        showToast(error.message || 'Failed to delete', 'error');
        window.adminEdushareDeleteInProgress = false;
    })
    .finally(() => {
        isDeletingEdushareItem = false;
        btn.disabled = false;
        btn.setAttribute('aria-busy', 'false');
        btn.innerHTML = originalText;
        if (!deleteSucceeded) window.adminEdushareDeleteInProgress = false;
    });
}

document.querySelectorAll('input[name="shareType"]').forEach(radio => {
    radio.addEventListener('change', toggleEmailInputs);
});
</script>
@endsection
