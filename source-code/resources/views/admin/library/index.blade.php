@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Manage e-Library - Admin')

@section('content')
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8" x-data="libraryAdmin()">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage e-Library</h1>
            <p class="text-gray-600 mt-2">Upload and manage academic resources</p>
        </div>
        <button @click="showCreateModal = true" style="background-color: var(--primary-color);" class="hover:opacity-90 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
            <i style="color: var(--complementary-color);" class="ph ph-plus mr-2"></i> New Resource
        </button>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Downloads</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="resource in resources" :key="resource.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg mr-3 flex-shrink-0" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                    <template x-if="resource.resource_type === 'pdf'">
                                        <i class="ph ph-file-pdf text-red-600 text-xl"></i>
                                    </template>
                                    <template x-if="resource.resource_type === 'link'">
                                        <i class="ph ph-link text-blue-600 text-xl"></i>
                                    </template>
                                    <template x-if="resource.resource_type === 'presentation'">
                                        <i class="ph ph-file-ppt text-orange-600 text-xl"></i>
                                    </template>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900" x-text="resource.title"></div>
                                    <div class="text-sm text-gray-500" x-text="truncate(resource.description, 50)"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="{
                                    'bg-red-100 text-red-800': resource.resource_type === 'pdf',
                                    'bg-blue-100 text-blue-800': resource.resource_type === 'link',
                                    'bg-orange-100 text-orange-800': resource.resource_type === 'presentation'
                                }" x-text="resource.resource_type.charAt(0).toUpperCase() + resource.resource_type.slice(1)">
                            </span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <i style="color: var(--primary-color);" class="ph ph-download-simple  mr-1"></i><span x-text="resource.download_count"></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button @click="editResource = resource; showEditModal = true" style="color: var(--primary-color);" class="hover:text-purple-900 mr-3">
                                <i class="ph ph-pencil-simple"></i> Edit
                            </button>
                            <button @click="deleteResource = resource; showDeleteModal = true" class="text-red-600 hover:text-red-900">
                                <i class="ph ph-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                </template>
                <template x-if="resources.length === 0">
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                            No resources yet. Create your first resource!
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $resources->links() }}
    </div>

    <!-- Create Resource Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showCreateModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Add New Resource</h2>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form action="{{ route('admin.library.store') }}" method="POST" enctype="multipart/form-data" data-admin-inplace-form x-data="{ isSubmitting: false, hasFileError: false }" @submit="
                    // Check if there's a file error in the upload component
                    const uploadComponent = $el.querySelector('[x-data*=handleFile]')?._x_dataStack?.[0];
                    if (uploadComponent?.fileError) {
                        $event.preventDefault();
                        alert('Please fix the file error before submitting.');
                        return;
                    }
                    if (isSubmitting) { 
                        $event.preventDefault(); 
                    } else { 
                        isSubmitting = true; 
                    }
                ">
                    @csrf
                    
                    <!-- Resource Type Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Resource Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="resource_type" value="pdf" x-model="resourceType" checked class="sr-only peer">
                                <div class="p-4 border-2 border-gray-200 rounded-lg peer-checked:border-red-500 peer-checked:bg-red-50 hover:border-red-300 transition-all text-center">
                                    <i class="ph ph-file-pdf text-3xl text-red-600 mb-2"></i>
                                    <p class="font-medium">PDF</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="resource_type" value="link" x-model="resourceType" class="sr-only peer">
                                <div class="p-4 border-2 border-gray-200 rounded-lg peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300 transition-all text-center">
                                    <i class="ph ph-link text-3xl text-blue-600 mb-2"></i>
                                    <p class="font-medium">Link</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="resource_type" value="presentation" x-model="resourceType" class="sr-only peer">
                                <div class="p-4 border-2 border-gray-200 rounded-lg peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:border-orange-300 transition-all text-center">
                                    <i class="ph ph-file-ppt text-3xl text-orange-600 mb-2"></i>
                                    <p class="font-medium">Presentation</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" placeholder="Enter resource title">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" placeholder="Enter resource description..."></textarea>
                        </div>



                        <!-- File Upload (for PDF and Presentation) -->
                        <div x-show="resourceType !== 'link'" x-data="{ 
                            fileName: '', 
                            fileSize: '',
                            isDragging: false,
                            fileError: '',
                            isValidFile: false,
                            handleFile(file) {
                                if (!file) return;
                                
                                // Reset states
                                this.fileError = '';
                                this.isValidFile = false;
                                
                                // Get file extension
                                const fileExt = file.name.split('.').pop().toLowerCase();
                                
                                // Read resource type from the parent libraryAdmin() component
                                let adminData = null;
                                for (let el = this.$el; el; el = el.parentElement) {
                                    const d = el._x_dataStack && el._x_dataStack[0];
                                    if (d && d.resourceType !== undefined) { adminData = d; break; }
                                }
                                const resourceType = adminData ? (adminData.editResource?.resource_type ?? adminData.resourceType) : null;
                                
                                // Validate based on resource type
                                if (resourceType === 'pdf') {
                                    if (fileExt !== 'pdf') {
                                        this.fileError = 'Wrong file type. PDF only.';
                                        this.fileName = file.name;
                                        this.fileSize = '';
                                        return;
                                    }
                                } else if (resourceType === 'presentation') {
                                    if (!['ppt', 'pptx'].includes(fileExt)) {
                                        this.fileError = 'Wrong file type. PPT/PPTX only.';
                                        this.fileName = file.name;
                                        this.fileSize = '';
                                        return;
                                    }
                                }
                                
                                // Check file size (20MB max)
                                const maxSize = 20 * 1024 * 1024; // 20MB in bytes
                                if (file.size > maxSize) {
                                    this.fileError = 'File too large. Max 20MB.';
                                    this.fileName = file.name;
                                    this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                                    return;
                                }
                                
                                // File is valid
                                this.isValidFile = true;
                                this.fileName = file.name;
                                this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Upload File <span class="text-red-500">*</span>
                            </label>
                            
                            <!-- Drag and Drop Area -->
                            <div @dragover.prevent="isDragging = true" 
                                 @dragleave.prevent="isDragging = false" 
                                 @drop.prevent="isDragging = false; handleFile($event.dataTransfer.files[0]); $refs.fileInput.files = $event.dataTransfer.files"
                                 :class="{
                                     'bg-opacity-10': isDragging,
                                     'border-red-300 bg-red-50': fileError,
                                     'border-green-300 bg-green-50': isValidFile,
                                     'border-gray-300': !isDragging && !fileError && !isValidFile
                                 }"
                                 :style="isDragging ? 'border-color: var(--primary-color); background-color: var(--primary-color); background-opacity: 0.1;' : ''"
                                 class="relative border-2 border-dashed rounded-lg p-3 transition-all duration-200"
                                 style="border-color: var(--primary-color);"
                                 @mouseenter="$el.style.borderColor = 'var(--primary-color)'"
                                 @mouseleave="if (!isDragging && !fileError && !isValidFile) $el.style.borderColor = 'var(--primary-color)'">
                                
                                <input type="file" 
                                       name="file" 
                                       x-ref="fileInput"
                                       :required="resourceType !== 'link'" 
                                       :accept="resourceType === 'pdf' ? '.pdf' : '.ppt,.pptx'"
                                       @change="handleFile($event.target.files[0])"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                
                                <div class="flex items-center gap-3 pointer-events-none">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 h-10 w-10" :class="{
                                        'text-green-500': isValidFile,
                                        'text-red-500': fileError,
                                        'text-gray-400': !fileName
                                    }">
                                        <template x-if="!fileName">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                        </template>
                                        <template x-if="isValidFile">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </template>
                                        <template x-if="fileError">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </template>
                                    </div>
                                    
                                    <!-- Text -->
                                    <div class="flex-1 min-w-0 text-left">
                                        <template x-if="!fileName">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700">
                                                    <span style="color: var(--primary-color);" class=" font-semibold">Click</span> or drag file
                                                </p>
                                                <p class="text-xs text-gray-500" x-show="resourceType === 'pdf'">PDF (max 20MB)</p>
                                                <p class="text-xs text-gray-500" x-show="resourceType === 'presentation'">PPT/PPTX (max 20MB)</p>
                                            </div>
                                        </template>
                                        
                                        <template x-if="fileName && !fileError">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="fileName"></p>
                                                <p class="text-xs text-gray-500" x-text="fileSize"></p>
                                            </div>
                                        </template>
                                        
                                        <template x-if="fileError">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="fileName"></p>
                                                <p class="text-xs text-red-600 font-medium" x-text="fileError"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- URL Input (for Link) -->
                        <div x-show="resourceType === 'link'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                URL <span class="text-red-500">*</span>
                            </label>
                            <input type="url" name="url" :required="resourceType === 'link'" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" placeholder="https://example.com">
                        </div>

                        <input type="hidden" name="is_published" value="1">

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2 hover:opacity-90 text-white rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                <i style="color: var(--complementary-color);" class="ph ph-floppy-disk mr-2"></i>
                                <span x-show="!isSubmitting">Add Resource</span>
                                <span x-show="isSubmitting">Adding...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
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
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" 
         style="display: none;">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                <i class="ph ph-check-circle text-xl text-green-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Success!</p>
            <p class="text-sm text-gray-600">{{ session('success') }}</p>
        </div>
        <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
    @endif

    <!-- Error Toast -->
    @if($errors->any())
    <div x-data="{ show: true }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-x-8"
         x-transition:enter-end="opacity-100 transform translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-x-0"
         x-transition:leave-end="opacity-0 transform translate-x-8"
         x-init="setTimeout(() => show = false, 6000)" 
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 max-w-md" 
         style="display: none;">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="ph ph-warning-circle text-xl text-red-600"></i>
                </div>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Error!</p>
                <ul class="text-sm text-gray-600 mt-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
    </div>
    @endif


    <!-- Edit Resource Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showEditModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Edit Resource</h2>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form :action="`{{ url('admin/library') }}/${editResource?.id}`" method="POST" enctype="multipart/form-data" data-admin-inplace-form x-show="editResource" x-data="{ isSubmitting: false }" @submit="
                    // Check if there's a file error in the upload component
                    const uploadComponent = $el.querySelector('[x-data*=handleFile]')?._x_dataStack?.[0];
                    if (uploadComponent?.fileError) {
                        $event.preventDefault();
                        alert('Please fix the file error before submitting.');
                        return;
                    }
                    if (isSubmitting) { 
                        $event.preventDefault(); 
                    } else { 
                        isSubmitting = true; 
                    }
                ">
                    @csrf
                    @method('PUT')
                    
                    <!-- Resource Type Display (Read-only) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resource Type</label>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <span class="inline-flex items-center">
                                <i :class="{
                                    'ph ph-file-pdf text-red-600': editResource?.resource_type === 'pdf',
                                    'ph ph-link text-blue-600': editResource?.resource_type === 'link',
                                    'ph ph-file-ppt text-orange-600': editResource?.resource_type === 'presentation'
                                }"></i>
                                <span class="font-medium capitalize" x-text="editResource?.resource_type"></span>
                            </span>
                        </div>
                        <input type="hidden" name="resource_type" :value="editResource?.resource_type">
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" :value="editResource?.title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" x-text="editResource?.description"></textarea>
                        </div>



                        <!-- File Upload (for PDF and Presentation) -->
                        <div x-show="editResource?.resource_type !== 'link'" x-data="{ 
                            fileName: '', 
                            fileSize: '',
                            isDragging: false,
                            fileError: '',
                            isValidFile: false,
                            handleFile(file) {
                                if (!file) return;
                                
                                // Reset states
                                this.fileError = '';
                                this.isValidFile = false;
                                
                                // Get file extension
                                const fileExt = file.name.split('.').pop().toLowerCase();
                                
                                // Read resource type from the parent libraryAdmin() component
                                let adminData = null;
                                for (let el = this.$el; el; el = el.parentElement) {
                                    const d = el._x_dataStack && el._x_dataStack[0];
                                    if (d && d.resourceType !== undefined) { adminData = d; break; }
                                }
                                const resourceType = adminData ? (adminData.editResource?.resource_type ?? adminData.resourceType) : null;
                                
                                // Validate based on resource type
                                if (resourceType === 'pdf') {
                                    if (fileExt !== 'pdf') {
                                        this.fileError = 'Wrong file type. PDF only.';
                                        this.fileName = file.name;
                                        this.fileSize = '';
                                        return;
                                    }
                                } else if (resourceType === 'presentation') {
                                    if (!['ppt', 'pptx'].includes(fileExt)) {
                                        this.fileError = 'Wrong file type. PPT/PPTX only.';
                                        this.fileName = file.name;
                                        this.fileSize = '';
                                        return;
                                    }
                                }
                                
                                // Check file size (20MB max)
                                const maxSize = 20 * 1024 * 1024; // 20MB in bytes
                                if (file.size > maxSize) {
                                    this.fileError = 'File too large. Max 20MB.';
                                    this.fileName = file.name;
                                    this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                                    return;
                                }
                                
                                // File is valid
                                this.isValidFile = true;
                                this.fileName = file.name;
                                this.fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Update File (Optional)</label>
                            
                            <!-- Current File Info -->
                            <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center text-sm">
                                    <i class="ph ph-file text-blue-600 mr-2"></i>
                                    <span class="text-gray-700">Current file: <span class="font-medium" x-text="editResource?.file_path?.split('/').pop()"></span></span>
                                </div>
                            </div>
                            
                            <!-- Drag and Drop Area -->
                            <div @dragover.prevent="isDragging = true" 
                                 @dragleave.prevent="isDragging = false" 
                                 @drop.prevent="isDragging = false; handleFile($event.dataTransfer.files[0]); $refs.editFileInput.files = $event.dataTransfer.files"
                                 :class="{
                                     'bg-opacity-10': isDragging,
                                     'border-red-300 bg-red-50': fileError,
                                     'border-green-300 bg-green-50': isValidFile,
                                     'border-gray-300': !isDragging && !fileError && !isValidFile
                                 }"
                                 :style="isDragging ? 'border-color: var(--primary-color); background-color: var(--primary-color); background-opacity: 0.1;' : ''"
                                 class="relative border-2 border-dashed rounded-lg p-3 transition-all duration-200"
                                 style="border-color: var(--primary-color);"
                                 @mouseenter="$el.style.borderColor = 'var(--primary-color)'"
                                 @mouseleave="if (!isDragging && !fileError && !isValidFile) $el.style.borderColor = 'var(--primary-color)'">
                                
                                <input type="file" 
                                       name="file" 
                                       x-ref="editFileInput"
                                       :accept="editResource?.resource_type === 'pdf' ? '.pdf' : '.ppt,.pptx'"
                                       @change="handleFile($event.target.files[0])"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                
                                <div class="flex items-center gap-3 pointer-events-none">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 h-10 w-10" :class="{
                                        'text-green-500': fileName && !fileError,
                                        'text-red-500': fileError,
                                        'text-gray-400': !fileName
                                    }">
                                        <template x-if="!fileName">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                        </template>
                                        <template x-if="fileError">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </template>
                                        <template x-if="fileName && !fileError">
                                            <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </template>
                                    </div>
                                    
                                    <!-- Text -->
                                    <div class="flex-1 min-w-0 text-left">
                                        <template x-if="!fileName">
                                            <div>
                                                <p class="text-sm font-medium text-gray-700">
                                                    <span style="color: var(--primary-color);" class=" font-semibold">Click</span> or drag file
                                                </p>
                                                <p class="text-xs text-gray-500" x-show="editResource?.resource_type === 'pdf'">PDF (max 20MB) - Optional</p>
                                                <p class="text-xs text-gray-500" x-show="editResource?.resource_type === 'presentation'">PPT/PPTX (max 20MB) - Optional</p>
                                            </div>
                                        </template>
                                        
                                        <template x-if="fileName && !fileError">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="fileName"></p>
                                                <p class="text-xs text-gray-500" x-text="fileSize"></p>
                                            </div>
                                        </template>
                                        
                                        <template x-if="fileError">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="fileName"></p>
                                                <p class="text-xs text-red-600 font-medium" x-text="fileError"></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- URL Input (for Link) -->
                        <div x-show="editResource?.resource_type === 'link'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">URL <span class="text-red-500">*</span></label>
                            <input type="url" name="url" :value="editResource?.url" :required="editResource?.resource_type === 'link'" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);">
                        </div>

                        <input type="hidden" name="is_published" value="1">

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2 hover:opacity-90 text-white rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                <i style="color: var(--complementary-color);" class="ph ph-floppy-disk mr-2"></i>
                                <span x-show="!isSubmitting">Update Resource</span>
                                <span x-show="isSubmitting">Updating...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showDeleteModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-warning text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Resource</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to delete "<span x-text="deleteResource?.title" class="font-semibold"></span>"? This action cannot be undone.
                    </p>
                    <div class="flex space-x-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <form :action="`{{ url('admin/library') }}/${deleteResource?.id}`" method="POST" data-admin-inplace-form class="flex-1" @submit="isDeleting = true">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed" :disabled="isDeleting">
                                <i class="ph mr-2" :class="isDeleting ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                <span x-text="isDeleting ? 'Deleting...' : 'Delete'"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



</div>

<script>
function libraryAdmin() {
    return {
        showCreateModal: false,
        showEditModal: false,
        showDeleteModal: false,
        resourceType: 'pdf',
        editResource: null,
        deleteResource: null,
        isEditMode: false,
        isSubmitting: false,
        isDeleting: false,
        resources: @json($resources->items()),

        init() {
            if (window.Echo) {
                window.Echo.channel('library')
                    .listen('.library-resource-created', (e) => {
                        console.log('Resource created:', e);
                        this.resources.unshift(e);
                        if (this.resources.length > 15) this.resources.pop();
                    })
                    .listen('.library-resource-updated', (e) => {
                        console.log('Resource updated:', e);
                        const index = this.resources.findIndex(r => r.id == e.id);
                        if (index !== -1) {
                            this.resources[index] = { ...this.resources[index], ...e };
                        }
                    })
                    .listen('.library-resource-deleted', (e) => {
                        console.log('Resource deleted:', e);
                        if (!e.id) return;
                        const index = this.resources.findIndex(r => r.id == e.id);
                        if (index !== -1) {
                            this.resources.splice(index, 1);
                        }
                    });
            }
        },

        truncate(text, length) {
            if (!text) return '';
            const decoded = new DOMParser().parseFromString(text, 'text/html').body.textContent || '';
            const clean = decoded.replace(/\s+/g, ' ').trim();
            return clean.length > length ? clean.substring(0, length) + '...' : clean;
        }
    }
}
</script>
@endsection
