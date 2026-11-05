@php
    $icon = 'ph-file'; $color = 'text-gray-600';
    if(in_array($item->file_type, ['pdf'])) { $icon = 'ph-file-pdf'; $color = 'text-red-600'; }
    elseif(in_array($item->file_type, ['doc', 'docx'])) { $icon = 'ph-file-doc'; $color = 'text-blue-600'; }
    elseif(in_array($item->file_type, ['xls', 'xlsx'])) { $icon = 'ph-file-xls'; $color = 'text-green-600'; }
    elseif(in_array($item->file_type, ['ppt', 'pptx'])) { $icon = 'ph-file-ppt'; $color = 'text-orange-600'; }
    elseif(in_array($item->file_type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $icon = 'ph-file-image'; $color = 'text-purple-600'; }
    elseif(in_array($item->file_type, ['zip', 'rar'])) { $icon = 'ph-file-zip'; $color = 'text-yellow-600'; }
@endphp
<div class="edushare-card bg-white rounded-lg shadow hover:shadow-lg transition p-4 border border-gray-200 group relative draggable-item" 
     style="overflow: visible; --edushare-index: {{ $loop->index ?? 0 }};" 
     draggable="{{ $canDelete ? 'true' : 'false' }}" 
     data-type="item" 
     data-id="{{ $item->id }}"
     ondragstart="handleDragStart(event)"
     ondragend="handleDragEnd(event)">
    <div class="flex items-start justify-between mb-3">
        <div class="flex items-start flex-1 min-w-0">
            <div class="bg-purple-100 p-3 rounded-lg mr-3 flex-shrink-0">
                <i class="ph {{ $icon }} {{ $color }} text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-gray-900 truncate">{{ $item->title }}</h3>
                <p class="text-xs text-gray-400 mt-1">{{ strtoupper($item->file_type) }}</p>
            </div>
        </div>
        @php
            $isShared = isset($canRemove) && $canRemove;
        @endphp
        @if($canDelete)
            {{-- 3-dots menu for own files (multiple options) - show on hover --}}
            @php
                $dropdownId = 'file-' . $item->id . '-' . ($tabContext ?? 'default') . '-' . uniqid();
            @endphp
            <div class="relative ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 dropdown-container-wrapper" style="z-index: 100;">
                <button onclick="toggleDropdown('{{ $dropdownId }}', event)" class="p-1 text-gray-400 hover:text-gray-600 dropdown-trigger" style="z-index: 101; cursor: pointer;" type="button">
                    <i class="ph ph-dots-three-vertical"></i>
                </button>
                <div id="dropdown-{{ $dropdownId }}" class="dropdown-menu" style="z-index: 9999; position: absolute;">
                    <button onclick="openRenameModal('item', {{ $item->id }}, '{{ addslashes($item->title) }}'); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" type="button">
                        <i class="ph ph-pencil-simple mr-2 text-blue-600"></i> Rename
                    </button>
                    <button onclick="openShareModal('item', {{ $item->id }}); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" type="button">
                        <i style="color: var(--primary-color);" class="ph ph-share-network mr-2 "></i> Share
                    </button>
                    <button onclick="openDeleteModal('item', {{ $item->id }}, '{{ addslashes($item->title) }}'); event.stopPropagation();" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center" type="button">
                        <i class="ph ph-trash mr-2"></i> Delete
                    </button>
                </div>
            </div>
        @elseif($isShared)
            {{-- Direct remove icon for shared files - show on hover --}}
            <button onclick="openRemoveModal('item', {{ $item->id }}, '{{ addslashes($item->title) }}')" class="ml-2 p-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-gray-400 hover:text-red-600" style="cursor: pointer;" title="Remove">
                <i class="ph ph-x"></i>
            </button>
        @endif
    </div>
    <div class="text-xs text-gray-500 mb-3">
        @if($showOwner)
        <i class="ph ph-user mr-1"></i> {{ $item->user->full_name }}
        @else
        <i class="ph ph-clock mr-1"></i> {{ $item->created_at->diffForHumans() }}
        @endif
    </div>
    <div class="flex space-x-2">
        <button onclick="viewFile({{ $item->id }}, '{{ addslashes($item->title) }}', '{{ $item->file_path }}', '{{ $item->file_type }}')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-center text-sm">
            <i class="ph ph-eye mr-1" style="color: var(--complementary-color);"></i> View
        </button>
        <a href="{{ route('edushare.download', $item) }}" style="background-color: var(--primary-color);" class="flex-1 hover:opacity-90 text-white px-3 py-2 rounded text-center text-sm">
            <i class="ph ph-download-simple mr-1" style="color: var(--complementary-color);"></i> Download
        </a>
    </div>
</div>
