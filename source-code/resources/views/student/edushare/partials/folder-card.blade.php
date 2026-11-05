<div class="edushare-card bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 group relative draggable-item drop-zone" 
     style="--edushare-index: {{ $loop->index ?? 0 }};"
     draggable="{{ $canDelete ? 'true' : 'false' }}" 
     data-type="folder" 
     data-id="{{ $folder->id }}"
     ondragstart="handleDragStart(event)"
     ondragend="handleDragEnd(event)"
     ondragover="handleDragOver(event)"
     ondragleave="handleDragLeave(event)"
     ondrop="handleDrop(event, {{ $folder->id }})">
    <div class="flex items-start justify-between mb-3">
        @php
            // Get current tab from URL, or determine based on context
            $currentTab = request()->get('tab');
            $currentFolderId = request()->get('folder');
            
            // If no tab specified but we're in a folder, determine tab based on folder ownership
            if (!$currentTab && $currentFolderId) {
                $currentFolder = \App\Models\EdushareFolder::find($currentFolderId);
                $currentTab = ($currentFolder && $currentFolder->user_id === auth()->id()) ? 'my' : 'shared';
            }
            
            // Default to 'all' if still no tab
            $currentTab = $currentTab ?: 'all';
            
            // Determine link tab based on folder ownership
            if ($folder->user_id !== auth()->id()) {
                $linkTab = 'shared';
            } else {
                // For own folders, preserve current tab (or use 'my' if in 'shared')
                $linkTab = $currentTab === 'shared' ? 'my' : $currentTab;
            }
        @endphp
        <a href="{{ route('edushare.index', ['folder' => $folder->id, 'tab' => $linkTab]) }}" data-edushare-navigation onclick="navigateToFolder(event, {{ $folder->id }}, {{ $folder->user_id === auth()->id() ? 'true' : 'false' }})" class="flex-1 min-w-0">
            <div class="flex items-center">
                <i style="color: var(--complementary-color);" class="ph ph-folder  text-3xl mr-3"></i>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate">{{ $folder->name }}</h3>
                </div>
            </div>
        </a>
        @php
            $isShared = isset($canRemove) && $canRemove;
        @endphp
        @if($canDelete)
            {{-- 3-dots menu for own folders (multiple options) - show on hover --}}
            <div class="relative ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 dropdown-container-wrapper" style="z-index: 100;">
                <button onclick="toggleDropdown('folder-{{ $folder->id }}', event)" class="p-1 text-gray-400 hover:text-gray-600 dropdown-trigger" style="z-index: 101; cursor: pointer;" type="button">
                    <i class="ph ph-dots-three-vertical"></i>
                </button>
                <div id="dropdown-folder-{{ $folder->id }}" class="dropdown-menu" style="z-index: 9999; position: absolute;">
                    <button onclick="openRenameModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}'); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" type="button">
                        <i class="ph ph-pencil-simple mr-2 text-blue-600"></i> Rename
                    </button>
                    <button onclick="openShareModal('folder', {{ $folder->id }}); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" type="button">
                        <i style="color: var(--primary-color);" class="ph ph-share-network mr-2 "></i> Share
                    </button>
                    <button onclick="openDeleteModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}'); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center" type="button">
                        <i class="ph ph-trash mr-2"></i> Delete
                    </button>
                </div>
            </div>
        @elseif($isShared)
            {{-- Direct remove icon for shared folders - show on hover --}}
            <button onclick="openRemoveModal('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')" class="ml-2 p-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-gray-400 hover:text-red-600" style="cursor: pointer;" title="Remove">
                <i class="ph ph-x"></i>
            </button>
        @endif
    </div>
    <div class="text-xs text-gray-500">
        <i class="ph ph-clock mr-1"></i> {{ $folder->created_at->diffForHumans() }}
    </div>
</div>
