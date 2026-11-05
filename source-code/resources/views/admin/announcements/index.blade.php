@extends(request()->boolean('fragment') ? 'layouts.fragment' : ($isSystemWide ? 'layouts.osas' : 'layouts.admin'))

@section('title', $isSystemWide ? 'System Announcements - OSAS' : 'Manage Announcements - Admin')

@section('content')
<style>
[x-cloak] { display: none !important; }

/* Fade in animation for new announcements */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.announcement-row-new {
    animation: fadeIn 0.5s ease-out;
}

/* Fade out animation for deleted announcements */
@keyframes fadeOut {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(20px); }
}

.announcement-row-deleting {
    animation: fadeOut 0.3s ease-out forwards;
}

.announcement-table th {
    padding: 0.7rem 1rem;
    white-space: nowrap;
}

.announcement-table td {
    padding: 0.8rem 1rem;
    vertical-align: middle;
}

.announcement-table tbody tr {
    min-height: 62px;
}

.announcement-type-option {
    border-color: #e2e8f0;
    color: #475569;
}

.announcement-type-option:hover {
    border-color: #26488C;
}

.announcement-type-option.is-selected,
input.peer:checked + .announcement-type-option {
    border-color: #26488C !important;
    background-color: rgba(38, 72, 140, 0.08) !important;
    color: #26488C !important;
}

input.peer:checked + .announcement-type-option .announcement-type-icon,
input.peer:checked + .announcement-type-option p {
    color: #26488C !important;
}

.announcement-primary-focus:focus {
    border-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }} !important;
    --tw-ring-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};
    outline: none;
}

/* Side-by-side org picker: main modal shifts aside + softens while picker has focus */
.announcement-modal-main {
    transition: transform 0.28s ease-out, filter 0.28s ease-out, opacity 0.28s ease-out;
}
.announcement-modal-main.is-dimmed {
    transform: translateX(-12px) scale(0.985);
    filter: blur(2px) brightness(0.96);
    pointer-events: none;
    user-select: none;
}
.announcement-org-panel {
    box-shadow: 0 25px 60px -12px rgba(38, 72, 140, 0.35);
}
@media (prefers-reduced-motion: reduce) {
    .announcement-modal-main,
    .announcement-org-panel {
        transition: none !important;
    }
}
</style>
<div class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8" x-data="{
    showCreateModal: false,
    showEditModal: false,
    showDeleteModal: false,
    showCreateOrgModal: false,
    showEditOrgModal: false,
    editAnnouncement: null,
    deleteAnnouncement: null,
    announcements: {{ json_encode($announcements->items()) }},
    deletingIds: []
}" x-init="
    (() => {
        // Remove stale empty rows restored from an older cached Alpine render.
        const removeDuplicateEmptyStates = () => {
            const emptyRows = Array.from($el.querySelectorAll('tbody tr'))
                .filter(row => row.textContent.includes('No announcements yet'));

            emptyRows.forEach((row, index) => {
                if (announcements.length > 0 || index > 0) row.remove();
            });
        };

        $nextTick(removeDuplicateEmptyStates);

        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit_announcement');
        if (editId) {
            const announcement = announcements.find(a => a.id == editId);
            if (announcement) {
                $nextTick(() => {
                    editAnnouncement = announcement;
                    showEditModal = true;
                    window.history.replaceState({}, '', window.location.pathname);
                });
            }
        }
        
        const currentOrganizationId = @json($currentOrgId);
        const isSystemWideAnnouncementPage = @json($isSystemWide);
        const isForThisAnnouncementPage = (event) => {
            const organizationId = event?.announcement?.organization_id ?? event?.organization_id;

            return isSystemWideAnnouncementPage
                ? organizationId === null
                : Number(organizationId) === Number(currentOrganizationId);
        };

        const subscribeToAnnouncementUpdates = () => {
            if (!window.AnnouncementEcho) return;

            console.log('Admin: Setting up Pusher announcement listeners');

            window.AnnouncementEcho.channel('announcements')
                .listen('.announcement-created', (e) => {
                    if (!isForThisAnnouncementPage(e)) return;

                    console.log('Admin: New announcement created by another user', e);
                    
                    // Check if announcement already exists (avoid duplicates from own creation)
                    const exists = announcements.find(a => Number(a.id) === Number(e.announcement.id));
                    if (!exists) {
                        // Add to beginning of array with animation
                        announcements.unshift({ ...e.announcement, isNew: true });
                        $nextTick(removeDuplicateEmptyStates);
                        
                        // Show toast
                        window.showToast('New announcement created!', 'success');
                    }
                })
                .listen('.announcement-deleted', (e) => {
                    if (!isForThisAnnouncementPage(e)) return;

                    console.log('Admin: Announcement deleted', e);
                    
                    // Add to deleting list for animation
                    if (!deletingIds.includes(e.id)) {
                        deletingIds.push(e.id);
                    }
                    
                    // Remove after animation
                    setTimeout(() => {
                        const index = announcements.findIndex(a => Number(a.id) === Number(e.id));
                        if (index !== -1) {
                            announcements.splice(index, 1);
                        }
                        deletingIds = deletingIds.filter(id => id !== e.id);
                    }, 300);
                })
                .listen('.announcement-updated', (e) => {
                    if (!isForThisAnnouncementPage(e)) return;

                    console.log('Admin: Announcement updated', e);
                    
                    const index = announcements.findIndex(a => Number(a.id) === Number(e.announcement.id));
                    if (index !== -1) {
                        announcements[index] = e.announcement;
                        window.showToast('Announcement updated!', 'info');
                    }
                });
        };

        if (window.AnnouncementEcho) {
            subscribeToAnnouncementUpdates();
        } else {
            window.addEventListener('announcement-echo-ready', subscribeToAnnouncementUpdates, { once: true });
        }
    })()
">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 mb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $isSystemWide ? 'System Announcements' : 'Manage Announcements' }}</h1>
                @if($isSystemWide)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#26488C]">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> All organizations
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $isSystemWide ? 'Broadcast important messages to all students and organizations.' : 'Create and manage announcements for your organization.' }}</p>
        </div>
        @if($canManageAnnouncements)
        <button @click="showCreateModal = true" style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }}; color: white;" class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:opacity-90">
            <i style="color: {{ $isSystemWide ? '#C4973B' : 'var(--complementary-color)' }};" class="ph ph-plus text-xs"></i>
            New Announcement
        </button>
        @endif
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $announcementStats->total }}</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active</p>
            <p class="mt-1 text-2xl font-bold text-emerald-800">{{ $announcementStats->active }}</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Scheduled</p>
            <p class="mt-1 text-2xl font-bold text-blue-800">{{ $announcementStats->scheduled }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Drafts / hidden</p>
            <p class="mt-1 text-2xl font-bold text-slate-800">{{ $announcementStats->drafts }}</p>
        </div>
    </div>

    <div class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Announcement registry</h2>
                <p class="mt-0.5 text-xs text-slate-500">Manage content, publication status, and images.</p>
            </div>
        </div>
        <div class="overflow-visible">
        <table class="announcement-table min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Announcement</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Category</th>
                    @if($isSystemWide)
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Audience</th>
                    @endif
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date Posted</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="(announcement, index) in announcements" :key="announcement.id">
                    <tr class="hover:bg-gray-50" :class="{ 'announcement-row-deleting': deletingIds.includes(announcement.id), 'announcement-row-new': announcement.isNew }">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <template x-if="announcement.images && announcement.images.length > 0">
                                    <div class="relative group mr-3" @mouseenter="$nextTick(() => $el.querySelector('.img-grid-scroll')?.scrollTo(0, 0))">
                                        <img :src="window.storageUrl(announcement.images[0])" :alt="announcement.title" class="w-12 h-12 rounded-lg object-cover">
                                        <template x-if="announcement.images.length > 1">
                                            <span style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};" class="absolute -top-1 -right-1  text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold" x-text="announcement.images.length"></span>
                                        </template>
                                        
                                        <!-- Hover preview for all images (including single image) -->
                                        <div x-show="announcement.images && announcement.images.length > 0" style="display: none;">
                                            <!-- Invisible bridge to keep hover active -->
                                            <div class="absolute left-full top-0 h-12 w-4 img-hover-card z-[9999]"></div>

                                            <!-- Hover Preview - Position based on row index (first 3 down, last 3 up) -->
                                            <div class="absolute left-full ml-4 img-hover-card z-[9999]" :class="index < 3 ? 'top-0' : 'bottom-0'">
                                                    <div class="bg-white rounded-lg shadow-2xl border-2 border-purple-200 p-3 w-80">
                                                        <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
                                                            <h4 class="text-sm font-bold text-gray-900">
                                                                <i style="color: var(--primary-color);" class="ph ph-images  mr-1"></i>
                                                                <span x-text="announcement.images.length"></span>
                                                                <span x-text="announcement.images.length === 1 ? ' Image' : ' Images'"></span>
                                                            </h4>
                                                        </div>
                                                        <div class="img-grid-scroll grid grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                                                            <template x-for="(image, idx) in announcement.images" :key="idx">
                                                                <div class="relative">
                                                                    <img :src="window.storageUrl(image)" :alt="'Image ' + (idx + 1)" style="border-color: {{ $isSystemWide ? '#26488C' : '#D1D5DB' }};" class="w-full h-32 object-cover rounded border border-gray-200">
                                                                    <div style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};" class="absolute top-1 left-1  text-white text-xs px-2 py-0.5 rounded font-bold shadow" x-text="idx + 1"></div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                </template>
                                <template x-if="!announcement.images || announcement.images.length === 0">
                                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                                        <i style="color: var(--primary-color);" class="ph ph-megaphone "></i>
                                    </div>
                                </template>
                                <div>
                                    <div class="text-sm font-medium text-gray-900" x-text="announcement.title"></div>
                                    <div class="text-sm text-gray-500" x-text="announcement.content.replace(/<[^>]*>/g, '').substring(0, 50) + '...'"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="{
                                    'bg-blue-100 text-blue-800': announcement.type === 'academic',
                                    'bg-green-100 text-green-800': announcement.type === 'event',
                                    'bg-gray-100 text-gray-800': announcement.type === 'general',
                                    'bg-red-100 text-red-800': announcement.type === 'urgent'
                                }"
                                x-text="announcement.type.charAt(0).toUpperCase() + announcement.type.slice(1)">
                            </span>
                        </td>
                        @if($isSystemWide)
                        <td class="px-6 py-4 whitespace-nowrap">
                            <template x-if="!announcement.visibility || announcement.visibility === 'all'">
                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-blue-50 text-[#26488C]">
                                    <i class="ph ph-globe mr-1"></i> All organizations
                                </span>
                            </template>
                            <template x-if="announcement.visibility === 'selected'">
                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-amber-50 text-amber-800" :title="(announcement.target_organization_ids || []).join(', ')">
                                    <i class="ph ph-users mr-1"></i><span x-text="((announcement.target_organization_ids || announcement.targetOrganizations || []).length) + ' organizations'"></span>
                                </span>
                            </template>
                        </td>
                        @endif
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="new Date(announcement.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"></div>
                            <div class="text-sm text-gray-500" x-text="new Date(announcement.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <template x-if="announcement.status === 'active'">
                                    <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="ph ph-check-circle mr-1"></i> Active
                                    </span>
                                </template>
                                <template x-if="announcement.status === 'scheduled'">
                                    <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <i class="ph ph-clock mr-1"></i> Scheduled
                                    </span>
                                </template>
                                <template x-if="announcement.status === 'hidden'">
                                    <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="ph ph-eye-slash mr-1"></i> Hidden
                                    </span>
                                </template>
                                <template x-if="announcement.status === 'draft'">
                                    <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <i class="ph ph-file mr-1"></i> Draft
                                    </span>
                                </template>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button @click="editAnnouncement = announcement; showEditModal = true" style="color: var(--primary-color);" class="hover:text-purple-900 mr-3">
                                <i style="color: var(--primary-color);" class="ph ph-pencil-simple "></i> Edit
                            </button>
                            <button @click="deleteAnnouncement = announcement; showDeleteModal = true" class="text-red-600 hover:text-red-900">
                                <i class="ph ph-trash text-red-600"></i> Delete
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="announcements.length === 0" x-cloak data-announcement-empty-state>
                    <td colspan="{{ $isSystemWide ? 6 : 5 }}" class="px-4 py-8 text-center">
                        <div class="flex items-center justify-center gap-3 text-sm text-slate-500">
                            <span class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-100 text-slate-400"><i class="ph ph-megaphone"></i></span>
                            <span>No announcements yet. Create your first announcement.</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination removed - using real-time updates -->

    <!-- Create Announcement Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-auto" style="display: none;"
         @keydown.escape.window="showCreateOrgModal ? showCreateOrgModal = false : showCreateModal = false">
        <div class="flex min-h-screen w-max min-w-full items-center justify-center gap-5 px-4 py-4">
            <div @click="showCreateOrgModal ? showCreateOrgModal = false : showCreateModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="announcement-modal-main relative max-h-[calc(100vh-2rem)] w-full max-w-5xl shrink-0 overflow-y-auto rounded-lg bg-white p-5 shadow-xl"
                 :class="showCreateOrgModal ? 'is-dimmed' : ''" :aria-hidden="showCreateOrgModal ? 'true' : 'false'">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xl font-bold text-gray-900">Create New Announcement</h2>
                    <button @click="showCreateModal = false; showCreateOrgModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form id="createAnnouncementForm" action="{{ route($announcementRoutePrefix . '.announcements.store') }}" method="POST" enctype="multipart/form-data" x-data="{
                    imagePreviews: [], 
                    selectedFiles: [],
                    isSubmitting: false,
                    createVisibility: 'all',
                    createSelectedOrgs: [],
                    createOrgSearch: '',
                    toggleCreateOrg(id, checked) {
                        id = Number(id);
                        if (checked) {
                            if (!this.createSelectedOrgs.includes(id)) this.createSelectedOrgs.push(id);
                        } else {
                            this.createSelectedOrgs = this.createSelectedOrgs.filter(x => x !== id);
                        }
                    },
                    showToast(message, type = 'error') {
                        const toast = document.createElement('div');
                        toast.className = 'fixed top-6 right-6 z-[60] bg-white border-l-4 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md transition-all';
                        toast.className += type === 'error' ? ' border-red-500' : ' border-green-500';
                        toast.innerHTML = `
                            <div class='flex-shrink-0'>
                                <div class='h-10 w-10 rounded-full ${type === 'error' ? 'bg-red-100' : 'bg-green-100'} flex items-center justify-center'>
                                    <i class='ph ${type === 'error' ? 'ph-warning-circle text-red-600' : 'ph-check-circle text-green-600'} text-xl'></i>
                                </div>
                            </div>
                            <div class='flex-1'>
                                <p class='text-sm font-semibold text-gray-900'>${type === 'error' ? 'Error!' : 'Success!'}</p>
                                <p class='text-sm text-gray-600'>${message}</p>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        setTimeout(() => { toast.remove(); }, 4000);
                    },
                    addFiles(newFiles) {
                        const filesArray = Array.from(newFiles);
                        
                        filesArray.forEach(file => {
                            if (file.size > 5 * 1024 * 1024) {
                                this.showToast('Image ' + file.name + ' exceeds 5MB');
                                return;
                            }
                            this.selectedFiles.push(file);
                            this.imagePreviews.push(URL.createObjectURL(file));
                        });
                        
                        this.updateFileInput();
                    },
                    removeFile(index) {
                        this.selectedFiles.splice(index, 1);
                        this.imagePreviews.splice(index, 1);
                        this.updateFileInput();
                    },
                    updateFileInput() {
                        const dt = new DataTransfer();
                        this.selectedFiles.forEach(file => dt.items.add(file));
                        this.$refs.createImagesInput.files = dt.files;
                    },
                    async submitForm(event) {
                        event.preventDefault();
                        
                        if (this.isSubmitting) return;
                        @if($isSystemWide)
                        if (this.createVisibility === 'selected' && this.createSelectedOrgs.length === 0) {
                            this.showToast('Please select at least one organization for a targeted announcement.');
                            return;
                        }
                        @endif
                        this.isSubmitting = true;
                        
                        const form = event.target;
                        const formData = new FormData(form);
                        
                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            });
                            
                            const data = await response.json();
                            
                            if (response.ok) {
                                // Close modal immediately
                                showCreateModal = false;
                                showCreateOrgModal = false;
                                
                                // Show success toast
                                this.showToast('Announcement created successfully!', 'success');
                                
                                // Reset form
                                form.reset();
                                this.imagePreviews = [];
                                this.selectedFiles = [];
                                this.createVisibility = 'all';
                                this.createSelectedOrgs = [];
                                this.createOrgSearch = '';
                                document.getElementById('create-editor').innerHTML = '';
                                
                                // Add announcement to list immediately (optimistic update)
                                if (data.announcement && !announcements.some(announcement => Number(announcement.id) === Number(data.announcement.id))) {
                                    announcements.unshift(data.announcement);
                                }
                            } else {
                                this.showToast(data.message || 'Failed to create announcement', 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            this.showToast('An error occurred. Please try again.', 'error');
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                }" @submit="submitForm($event)" @org-picker-done-create.window="createSelectedOrgs = $event.detail.ids">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <!-- Left Side - Multiple Images Preview -->
                        <div class="flex flex-col">
                            <label class="mb-2 block text-xs font-semibold text-gray-700">Upload Images</label>
                            
                            <!-- Fixed Height Container for Images -->
                            <div class="h-64 mb-3 border-2 border-gray-200 rounded-lg p-3 bg-gray-50">
                                <!-- Empty State -->
                                <div x-show="imagePreviews.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400">
                                    <i class="ph ph-images text-4xl mb-2"></i>
                                    <p class="text-sm font-semibold">No images uploaded</p>
                                    <p class="text-xs">Click button below to add images</p>
                                </div>
                                
                                <!-- Image Previews Grid -->
                                <div x-show="imagePreviews.length > 0" class="grid grid-cols-2 gap-3 h-full overflow-y-auto">
                                    <template x-for="(preview, index) in imagePreviews" :key="index">
                                        <div class="relative group h-36">
                                            <img :src="preview" style="border-color: {{ $isSystemWide ? '#26488C' : '#D1D5DB' }};" class="w-full h-full object-cover rounded-lg border-2 border-gray-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeFile(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};" class="absolute top-2 right-2  text-white text-sm px-2 py-1 rounded font-bold shadow-lg" x-text="index + 1"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Upload Button (Bottom) - Fixed at bottom -->
                            <div style="border-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }}; background-color: transparent;" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition cursor-pointer"
                                 @click="$refs.createImagesInput.click()">
                                <input type="file" name="images[]" accept="image/*" multiple id="create-images-upload" x-ref="createImagesInput"
                                    @change="addFiles($event.target.files);"
                                    class="hidden">
                                <i style="color: {{ $isSystemWide ? '#26488C' : '#9CA3AF' }};" class="ph ph-cloud-arrow-up text-4xl mb-2"></i>
                                <p style="color: {{ $isSystemWide ? '#26488C' : '#4B5563' }};" class="font-semibold text-sm">Click to add images</p>
                                <p style="color: {{ $isSystemWide ? '#64748B' : '#6B7280' }};" class="text-xs mt-1">
                                    <span x-show="selectedFiles.length === 0">PNG, JPG up to 5MB each</span>
                                    <span x-show="selectedFiles.length > 0" style="color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};" class="font-semibold ">
                                        <span x-text="selectedFiles.length"></span> image(s) selected
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right Side - Form Fields -->
                        <div class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" required style="border-color: var(--primary-color);" class="announcement-primary-focus w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:" placeholder="Enter announcement title">
                            </div>
                            <div x-data="{ content: '' }">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Content <span class="text-red-500">*</span></label>
                                
                                <!-- Rich Text Toolbar -->
                                <div class="border border-gray-300 rounded-t-lg bg-gray-50 px-2 py-2 flex items-center space-x-1">
                                    <button type="button" onclick="document.execCommand('bold', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Bold">
                                        <i class="ph ph-text-b"></i>
                                    </button>
                                    <button type="button" onclick="document.execCommand('italic', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Italic">
                                        <i class="ph ph-text-italic"></i>
                                    </button>
                                    <button type="button" onclick="document.execCommand('underline', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Underline">
                                        <i class="ph ph-text-underline"></i>
                                    </button>
                                </div>
                                
                                <!-- Editable Content Area -->
                                <div id="create-editor" 
                                     contenteditable="true" 
                                     style="border-color: var(--primary-color); height: 120px; max-height: 120px; overflow-y: auto;" 
                                     class="announcement-primary-focus w-full px-4 py-3 text-sm border border-t-0 border-gray-300 rounded-b-lg focus:ring-2 focus:"
                                     @input="content = $el.innerHTML"
                                     placeholder="Enter announcement content..."></div>
                                
                                <!-- Hidden input to submit content -->
                                <input type="hidden" name="content" :value="content" required>
                            </div>
                            @if($isSystemWide)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Audience <span class="text-red-500">*</span></label>
                                <div class="flex space-x-3">
                                    <label class="flex-1 cursor-pointer" @click="showCreateOrgModal = false">
                                        <input type="radio" name="visibility" value="all" x-model="createVisibility" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all" :class="{ 'is-selected': createVisibility === 'all' }">
                                            <i class="announcement-type-icon ph ph-globe mr-2 text-base"></i>
                                            <p class="text-sm font-medium">All Organizations</p>
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer" @click="showCreateOrgModal = true; $dispatch('org-picker-load-create', { ids: createSelectedOrgs })">
                                        <input type="radio" name="visibility" value="selected" x-model="createVisibility" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all" :class="{ 'is-selected': createVisibility === 'selected' }">
                                            <i class="announcement-type-icon ph ph-users mr-2 text-base"></i>
                                            <p class="text-sm font-medium">Selected Organizations</p>
                                        </div>
                                    </label>
                                </div>
                                <!-- Selections are submitted via hidden inputs (same organization_ids[] payload as before) -->
                                <template x-for="id in createSelectedOrgs" :key="'create-org-' + id">
                                    <input type="hidden" name="organization_ids[]" :value="id">
                                </template>
                                <button type="button" x-show="createVisibility === 'selected'" x-cloak
                                        @click="showCreateOrgModal = true; $dispatch('org-picker-load-create', { ids: createSelectedOrgs })"
                                        class="mt-3 flex w-full items-center justify-between rounded-lg border-2 border-[#26488C]/30 bg-[#26488C]/5 px-3 py-2.5 text-left transition hover:border-[#26488C] hover:bg-[#26488C]/10">
                                    <span class="text-sm font-semibold text-[#26488C]"><span x-text="createSelectedOrgs.length"></span> organization(s) selected</span>
                                    <span class="text-xs font-bold text-[#26488C]">Manage <i class="ph ph-arrow-right ml-1"></i></span>
                                </button>
                            </div>
                            @endif
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Type <span class="text-red-500">*</span></label>
                                <div class="flex space-x-4">
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="type" value="general" checked class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all">
                                            <i class="announcement-type-icon ph ph-megaphone mr-2 text-base"></i>
                                            <p class="text-sm font-medium">General</p>
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="type" value="urgent" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all">
                                            <i class="announcement-type-icon ph ph-warning mr-2 text-base"></i>
                                            <p class="text-sm font-medium">Urgent</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div x-data="{ publishNow: true }">
                                <label class="flex items-center mb-3">
                                    <input type="checkbox" x-model="publishNow" name="is_published" value="1" checked style="color: var(--primary-color);" class="h-4 w-4  focus:ring-purple-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Publish immediately</span>
                                </label>
                                <div x-show="!publishNow" style="background-color: rgba(38, 72, 140, 0.06); border-color: var(--primary-color);" class="rounded-lg border p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        <i style="color: var(--primary-color);" class="ph ph-calendar-blank mr-1 "></i>Schedule Publication
                                    </label>
                                    <div class="relative">
                                        <input type="datetime-local" name="scheduled_at" 
                                            style="border-color: var(--primary-color);" class="announcement-primary-focus w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-base cursor-pointer focus:ring-2"
                                            onclick="this.showPicker()">
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="showCreateModal = false; showCreateOrgModal = false" class="px-4 py-2 text-xs border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }}; color: white;" class="px-4 py-2 text-xs hover:opacity-90 rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                    <i style="color: {{ $isSystemWide ? '#C4973B' : 'var(--complementary-color)' }};" class="ph ph-floppy-disk mr-2 "></i>
                                    <span x-show="!isSubmitting">Create Announcement</span>
                                    <span x-show="isSubmitting">Creating...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            @if($isSystemWide)
            <!-- Organization picker side modal (Create) -->
            <div x-show="showCreateOrgModal" x-cloak
                 x-data="{
                     ids: [],
                     search: '',
                     allIds: {{ $organizations->pluck('id')->toJson() }},
                     init() {
                         this.$watch('showCreateOrgModal', (open) => {
                             if (open) this.$nextTick(() => this.$refs.createOrgSearchInput?.focus());
                         });
                     },
                     toggle(id, checked) {
                         id = Number(id);
                         if (checked) {
                             if (!this.ids.includes(id)) this.ids.push(id);
                         } else {
                             this.ids = this.ids.filter(x => x !== id);
                         }
                     }
                 }"
                 @org-picker-load-create.window="ids = [...$event.detail.ids]; search = ''"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-8"
                 class="announcement-org-panel relative flex max-h-[calc(100vh-2rem)] w-full max-w-md shrink-0 flex-col overflow-hidden rounded-xl bg-white ring-1 ring-[#26488C]/20"
                 role="dialog" aria-modal="true" aria-label="Select organizations">
                <div class="flex items-center justify-between bg-[#26488C] px-4 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-white"><i class="ph ph-users mr-1.5" style="color: #C4973B;"></i>Select Organizations</h3>
                        <p class="mt-0.5 text-xs text-blue-100"><span x-text="ids.length"></span> selected</p>
                    </div>
                    <button type="button" @click="showCreateOrgModal = false" aria-label="Close organization picker" class="flex h-9 w-9 items-center justify-center rounded-lg text-blue-100 transition hover:bg-white/10 hover:text-white">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>
                <div class="border-b border-slate-200 px-4 py-3">
                    <input type="text" x-ref="createOrgSearchInput" x-model="search" placeholder="Search organizations..."
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                    <div class="flex items-center justify-between pt-2">
                        <p class="text-xs font-semibold text-gray-600"><span x-text="ids.length"></span> organization(s) selected</p>
                        <div class="flex gap-3 text-xs">
                            <button type="button" @click="ids = [...allIds]" class="font-semibold text-[#26488C] hover:underline">Select all</button>
                            <button type="button" @click="ids = []" class="font-semibold text-gray-500 hover:underline">Clear</button>
                        </div>
                    </div>
                </div>
                <div class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    @foreach($organizations as $org)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition hover:bg-slate-50" x-show="!search || @js(strtolower($org->name . ' ' . ($org->org_acronym ?? ''))).includes(search.toLowerCase())">
                        <input type="checkbox" value="{{ $org->id }}" :checked="ids.includes({{ $org->id }})" @change="toggle({{ $org->id }}, $el.checked)" class="h-4 w-4 rounded border-gray-300 text-[#26488C] focus:ring-[#26488C]">
                        <span class="text-sm text-gray-800">{{ $org->org_acronym ? $org->org_acronym . ' - ' : '' }}{{ $org->name }}</span>
                        <span class="ml-auto text-[11px] text-gray-500">{{ $org->type }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="flex gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <button type="button" @click="showCreateOrgModal = false" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="button" @click="$dispatch('org-picker-done-create', { ids: ids }); showCreateOrgModal = false" class="flex-1 rounded-lg bg-[#26488C] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Done (<span x-text="ids.length"></span>)</button>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-auto" style="display: none;"
         @keydown.escape.window="showEditOrgModal ? showEditOrgModal = false : showEditModal = false">
        <div class="flex min-h-screen w-max min-w-full items-center justify-center gap-5 px-4 py-4">
            <div @click="showEditOrgModal ? showEditOrgModal = false : showEditModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="announcement-modal-main relative max-h-[calc(100vh-2rem)] w-full max-w-5xl shrink-0 overflow-y-auto rounded-lg bg-white p-5 shadow-xl"
                 :class="showEditOrgModal ? 'is-dimmed' : ''" :aria-hidden="showEditOrgModal ? 'true' : 'false'">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xl font-bold text-gray-900">Edit Announcement</h2>
                    <button @click="showEditModal = false; showEditOrgModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form :action="`{{ url($announcementBasePath) }}/${editAnnouncement?.id}`" method="POST" enctype="multipart/form-data" x-show="editAnnouncement" x-data="{
                    existingImages: [],
                    existingImageUrls: [],
                    newImagePreviews: [],
                    newSelectedFiles: [],
                    isSubmitting: false,
                    editVisibility: 'all',
                    editSelectedOrgs: [],
                    editOrgSearch: '',
                    toggleEditOrg(id, checked) {
                        id = Number(id);
                        if (checked) {
                            if (!this.editSelectedOrgs.includes(id)) this.editSelectedOrgs.push(id);
                        } else {
                            this.editSelectedOrgs = this.editSelectedOrgs.filter(x => x !== id);
                        }
                    },
                    init() {
                        this.$watch('editAnnouncement', (value) => {
                            if (value && value.images) {
                                // Store paths for form submission
                                this.existingImages = [...value.images];
                                // Store URLs for display
                                this.existingImageUrls = value.images_urls && value.images_urls.length > 0 
                                    ? [...value.images_urls] 
                                    : [...value.images];
                                this.newImagePreviews = [];
                                this.newSelectedFiles = [];
                            }
                            if (value) {
                                this.editVisibility = value.visibility || 'all';
                                const ids = value.target_organization_ids || [];
                                this.editSelectedOrgs = ids.map(x => Number(x));
                                this.editOrgSearch = '';
                            }
                        });
                    },
                    formatLocalDateTime(dateStr) {
                        if (!dateStr) return '';
                        const d = new Date(dateStr);
                        if (isNaN(d.getTime())) return dateStr;
                        const pad = (n) => String(n).padStart(2, '0');
                        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                    },
                    removeExisting(index) {
                        this.existingImages.splice(index, 1);
                        this.existingImageUrls.splice(index, 1);
                    },
                    showToast(message, type = 'error') {
                        const toast = document.createElement('div');
                        toast.className = 'fixed top-6 right-6 z-[60] bg-white border-l-4 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md transition-all';
                        toast.className += type === 'error' ? ' border-red-500' : ' border-green-500';
                        toast.innerHTML = `
                            <div class='flex-shrink-0'>
                                <div class='h-10 w-10 rounded-full ${type === 'error' ? 'bg-red-100' : 'bg-green-100'} flex items-center justify-center'>
                                    <i class='ph ${type === 'error' ? 'ph-warning-circle text-red-600' : 'ph-check-circle text-green-600'} text-xl'></i>
                                </div>
                            </div>
                            <div class='flex-1'>
                                <p class='text-sm font-semibold text-gray-900'>${type === 'error' ? 'Error!' : 'Success!'}</p>
                                <p class='text-sm text-gray-600'>${message}</p>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        setTimeout(() => { toast.remove(); }, 4000);
                    },
                    addNewFiles(files) {
                        const filesArray = Array.from(files);
                        
                        filesArray.forEach(file => {
                            if (file.size > 5 * 1024 * 1024) {
                                this.showToast('Image ' + file.name + ' exceeds 5MB');
                                return;
                            }
                            this.newSelectedFiles.push(file);
                            this.newImagePreviews.push(URL.createObjectURL(file));
                        });
                        
                        this.updateFileInput();
                    },
                    removeNewFile(index) {
                        this.newSelectedFiles.splice(index, 1);
                        this.newImagePreviews.splice(index, 1);
                        this.updateFileInput();
                    },
                    updateFileInput() {
                        const dt = new DataTransfer();
                        this.newSelectedFiles.forEach(file => dt.items.add(file));
                        this.$refs.editImagesInput.files = dt.files;
                    },
                    async submitForm(event) {
                        event.preventDefault();

                        if (this.isSubmitting) return;
                        @if($isSystemWide)
                        if (this.editVisibility === 'selected' && this.editSelectedOrgs.length === 0) {
                            this.showToast('Please select at least one organization for a targeted announcement.');
                            return;
                        }
                        @endif
                        this.isSubmitting = true;

                        const form = event.target;
                        const formData = new FormData(form);

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            });
                            const data = await response.json();

                            if (!response.ok) {
                                this.showToast(data.message || 'Failed to update announcement');
                                return;
                            }

                            if (data.announcement) {
                                const index = announcements.findIndex(announcement => Number(announcement.id) === Number(data.announcement.id));
                                if (index !== -1) {
                                    announcements[index] = data.announcement;
                                }
                            }

                            showEditModal = false;
                            showEditOrgModal = false;
                            editAnnouncement = null;
                            this.showToast('Announcement updated successfully!', 'success');
                        } catch (error) {
                            console.error('Announcement update error:', error);
                            this.showToast('An error occurred. Please try again.');
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                }" @submit.prevent="submitForm($event)" @org-picker-done-edit.window="editSelectedOrgs = $event.detail.ids">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <!-- Left Side - Multiple Images Management -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Manage Images (Up to 5 total)</label>
                            
                            <!-- Fixed Height Container for Images -->
                            <div class="h-64 mb-3 border-2 border-gray-200 rounded-lg p-3 bg-gray-50">
                                <!-- Empty State -->
                                <div x-show="existingImages.length === 0 && newImagePreviews.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400">
                                    <i class="ph ph-images text-6xl mb-3"></i>
                                    <p class="text-lg font-semibold">No images uploaded</p>
                                    <p class="text-sm">Click button below to add images</p>
                                </div>
                                
                                <!-- All Images Grid -->
                                <div x-show="existingImages.length > 0 || newImagePreviews.length > 0" class="grid grid-cols-2 gap-3 h-full overflow-y-auto">
                                    <!-- Existing Images -->
                                    <template x-for="(image, index) in existingImages" :key="'existing-' + index">
                                        <div class="relative group h-36">
                                            <img :src="existingImageUrls[index]" style="border-color: {{ $isSystemWide ? '#26488C' : '#D1D5DB' }};" class="w-full h-full object-cover rounded-lg border-2 border-gray-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeExisting(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div style="background-color: {{ $isSystemWide ? '#26488C' : '#2563EB' }};" class="absolute top-2 right-2 text-white text-sm px-2 py-1 rounded font-bold shadow-lg" x-text="index + 1"></div>
                                            <input type="hidden" :name="'existing_images[]'" :value="image">
                                        </div>
                                    </template>
                                    
                                    <!-- New Images -->
                                    <template x-for="(preview, index) in newImagePreviews" :key="'new-' + index">
                                        <div class="relative group h-36">
                                            <img :src="preview" class="w-full h-full object-cover rounded-lg border-2 border-green-400">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeNewFile(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div class="absolute top-2 left-2 bg-green-500 text-white text-xs px-2 py-1 rounded font-bold shadow-lg">NEW</div>
                                            <div style="background-color: {{ $isSystemWide ? '#26488C' : '#16A34A' }};" class="absolute top-2 right-2 text-white text-sm px-2 py-1 rounded font-bold shadow-lg" x-text="existingImages.length + index + 1"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Upload Button (Bottom) - Fixed at bottom -->
                            <div style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover: transition cursor-pointer"
                                 @click="$refs.editImagesInput.click()">
                                <input type="file" name="images[]" accept="image/*" multiple x-ref="editImagesInput"
                                    @change="addNewFiles($event.target.files);"
                                    class="hidden">
                                <i class="ph ph-cloud-arrow-up text-5xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600 font-semibold text-lg">Click to add more images</p>
                                <p class="text-sm text-gray-500 mt-2">
                                    <span style="color: var(--primary-color);" class="font-semibold ">
                                        <span x-text="existingImages.length + newSelectedFiles.length"></span> image(s) total
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right Side - Form Fields -->
                        <div class="space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" :value="editAnnouncement?.title" required style="border-color: var(--primary-color);" class="announcement-primary-focus w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:">
                            </div>
                            <div x-data="{ editContent: '' }" x-init="$watch('editAnnouncement', value => { if(value) { editContent = value.content; setTimeout(() => { document.getElementById('edit-editor').innerHTML = value.content; }, 100); } })">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Content <span class="text-red-500">*</span></label>
                                
                                <!-- Rich Text Toolbar -->
                                <div class="border border-gray-300 rounded-t-lg bg-gray-50 px-2 py-2 flex items-center space-x-1">
                                    <button type="button" onclick="document.execCommand('bold', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Bold">
                                        <i class="ph ph-text-b"></i>
                                    </button>
                                    <button type="button" onclick="document.execCommand('italic', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Italic">
                                        <i class="ph ph-text-italic"></i>
                                    </button>
                                    <button type="button" onclick="document.execCommand('underline', false, null)" class="px-3 py-1 hover:bg-gray-200 rounded" title="Underline">
                                        <i class="ph ph-text-underline"></i>
                                    </button>
                                </div>
                                
                                <!-- Editable Content Area -->
                                <div id="edit-editor" 
                                     contenteditable="true" 
                                     style="border-color: var(--primary-color); height: 120px; max-height: 120px; overflow-y: auto;" 
                                     class="announcement-primary-focus w-full px-4 py-3 text-sm border border-t-0 border-gray-300 rounded-b-lg focus:ring-2 focus:"
                                     @input="editContent = $el.innerHTML"></div>
                                
                                <!-- Hidden input to submit content -->
                                <input type="hidden" name="content" :value="editContent" required>
                            </div>
                            @if($isSystemWide)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Audience <span class="text-red-500">*</span></label>
                                <div class="flex space-x-3">
                                    <label class="flex-1 cursor-pointer" @click="showEditOrgModal = false">
                                        <input type="radio" name="visibility" value="all" x-model="editVisibility" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all" :class="{ 'is-selected': editVisibility === 'all' }">
                                            <i class="announcement-type-icon ph ph-globe mr-2 text-base"></i>
                                            <p class="text-sm font-medium">All Organizations</p>
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer" @click="showEditOrgModal = true; $dispatch('org-picker-load-edit', { ids: editSelectedOrgs })">
                                        <input type="radio" name="visibility" value="selected" x-model="editVisibility" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all" :class="{ 'is-selected': editVisibility === 'selected' }">
                                            <i class="announcement-type-icon ph ph-users mr-2 text-base"></i>
                                            <p class="text-sm font-medium">Selected Organizations</p>
                                        </div>
                                    </label>
                                </div>
                                <!-- Selections are submitted via hidden inputs (same organization_ids[] payload as before) -->
                                <template x-for="id in editSelectedOrgs" :key="'edit-org-' + id">
                                    <input type="hidden" name="organization_ids[]" :value="id">
                                </template>
                                <button type="button" x-show="editVisibility === 'selected'" x-cloak
                                        @click="showEditOrgModal = true; $dispatch('org-picker-load-edit', { ids: editSelectedOrgs })"
                                        class="mt-3 flex w-full items-center justify-between rounded-lg border-2 border-[#26488C]/30 bg-[#26488C]/5 px-3 py-2.5 text-left transition hover:border-[#26488C] hover:bg-[#26488C]/10">
                                    <span class="text-sm font-semibold text-[#26488C]"><span x-text="editSelectedOrgs.length"></span> organization(s) selected</span>
                                    <span class="text-xs font-bold text-[#26488C]">Manage <i class="ph ph-arrow-right ml-1"></i></span>
                                </button>
                            </div>
                            @endif
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Type <span class="text-red-500">*</span></label>
                                <div class="flex space-x-4">
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="type" value="general" :checked="editAnnouncement?.type === 'general'" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all">
                                            <i class="announcement-type-icon ph ph-megaphone mr-2 text-base"></i>
                                            <p class="text-sm font-medium">General</p>
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="type" value="urgent" :checked="editAnnouncement?.type === 'urgent'" class="sr-only peer">
                                        <div class="announcement-type-option flex items-center justify-center rounded-lg border-2 p-3 transition-all">
                                            <i class="announcement-type-icon ph ph-warning mr-2 text-base"></i>
                                            <p class="text-sm font-medium">Urgent</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <!-- Schedule Section - Only show for scheduled/draft status -->
                            <div x-show="editAnnouncement?.status === 'scheduled' || editAnnouncement?.status === 'draft'">
                                <div style="background-color: rgba(38, 72, 140, 0.06); border-color: var(--primary-color);" class="rounded-lg border p-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        <i style="color: var(--primary-color);" class="ph ph-calendar-blank mr-1 "></i>Schedule Publication
                                    </label>
                                    <div class="relative">
                                        <input type="datetime-local" name="scheduled_at" :value="editAnnouncement?.scheduled_at ? formatLocalDateTime(editAnnouncement.scheduled_at) : ''" 
                                            style="border-color: var(--primary-color);" class="announcement-primary-focus w-full rounded-lg border-2 border-gray-300 px-4 py-3 text-base cursor-pointer focus:ring-2"
                                            onclick="this.showPicker()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hide Button - Only show for active status -->
                            <div x-show="editAnnouncement?.status === 'active'">
                                <input type="hidden" name="hide_announcement" value="0" x-ref="hideInput">
                                <button type="button" 
                                    @click="$refs.hideInput.value = '1'; $refs.hideBtn.classList.add('hidden'); $refs.hideMsg.classList.remove('hidden')"
                                    x-ref="hideBtn"
                                    class="w-full px-4 py-3 bg-yellow-50 hover:bg-yellow-100 border-2 border-yellow-300 rounded-lg text-yellow-800 font-medium transition flex items-center justify-center">
                                    <i class="ph ph-eye-slash mr-2"></i>Hide from Students
                                </button>
                                <div x-ref="hideMsg" class="hidden p-3 bg-red-50 border-2 border-red-300 rounded-lg text-red-800 text-sm">
                                    <i class="ph ph-warning mr-2"></i>This announcement will be hidden when you save.
                                </div>
                            </div>
                            
                            <!-- Unhide Button - Only show for hidden status -->
                            <div x-show="editAnnouncement?.status === 'hidden'">
                                <input type="hidden" name="unhide_announcement" value="0" x-ref="unhideInput">
                                <button type="button" 
                                    @click="$refs.unhideInput.value = '1'; $refs.unhideBtn.classList.add('hidden'); $refs.unhideMsg.classList.remove('hidden')"
                                    x-ref="unhideBtn"
                                    class="w-full px-4 py-3 bg-green-50 hover:bg-green-100 border-2 border-green-300 rounded-lg text-green-800 font-medium transition flex items-center justify-center">
                                    <i class="ph ph-eye mr-2"></i>Unhide & Make Active
                                </button>
                                <div x-ref="unhideMsg" class="hidden p-3 bg-green-50 border-2 border-green-300 rounded-lg text-green-800 text-sm">
                                    <i class="ph ph-check-circle mr-2"></i>This announcement will be visible to students when you save.
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="showEditModal = false; showEditOrgModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" style="background-color: {{ $isSystemWide ? '#26488C' : 'var(--primary-color)' }};" class="px-4 py-2  hover: text-white rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                    <i style="color: var(--complementary-color);" class="ph ph-floppy-disk mr-2 "></i>
                                    <span x-show="!isSubmitting">Update Announcement</span>
                                    <span x-show="isSubmitting">Updating...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            @if($isSystemWide)
            <!-- Organization picker side modal (Edit) -->
            <div x-show="showEditOrgModal" x-cloak
                 x-data="{
                     ids: [],
                     search: '',
                     allIds: {{ $organizations->pluck('id')->toJson() }},
                     init() {
                         this.$watch('showEditOrgModal', (open) => {
                             if (open) this.$nextTick(() => this.$refs.editOrgSearchInput?.focus());
                         });
                     },
                     toggle(id, checked) {
                         id = Number(id);
                         if (checked) {
                             if (!this.ids.includes(id)) this.ids.push(id);
                         } else {
                             this.ids = this.ids.filter(x => x !== id);
                         }
                     }
                 }"
                 @org-picker-load-edit.window="ids = [...$event.detail.ids]; search = ''"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-8"
                 class="announcement-org-panel relative flex max-h-[calc(100vh-2rem)] w-full max-w-md shrink-0 flex-col overflow-hidden rounded-xl bg-white ring-1 ring-[#26488C]/20"
                 role="dialog" aria-modal="true" aria-label="Select organizations">
                <div class="flex items-center justify-between bg-[#26488C] px-4 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-white"><i class="ph ph-users mr-1.5" style="color: #C4973B;"></i>Select Organizations</h3>
                        <p class="mt-0.5 text-xs text-blue-100"><span x-text="ids.length"></span> selected</p>
                    </div>
                    <button type="button" @click="showEditOrgModal = false" aria-label="Close organization picker" class="flex h-9 w-9 items-center justify-center rounded-lg text-blue-100 transition hover:bg-white/10 hover:text-white">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>
                <div class="border-b border-slate-200 px-4 py-3">
                    <input type="text" x-ref="editOrgSearchInput" x-model="search" placeholder="Search organizations..."
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]">
                    <div class="flex items-center justify-between pt-2">
                        <p class="text-xs font-semibold text-gray-600"><span x-text="ids.length"></span> organization(s) selected</p>
                        <div class="flex gap-3 text-xs">
                            <button type="button" @click="ids = [...allIds]" class="font-semibold text-[#26488C] hover:underline">Select all</button>
                            <button type="button" @click="ids = []" class="font-semibold text-gray-500 hover:underline">Clear</button>
                        </div>
                    </div>
                </div>
                <div class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    @foreach($organizations as $org)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition hover:bg-slate-50" x-show="!search || @js(strtolower($org->name . ' ' . ($org->org_acronym ?? ''))).includes(search.toLowerCase())">
                        <input type="checkbox" value="{{ $org->id }}" :checked="ids.includes({{ $org->id }})" @change="toggle({{ $org->id }}, $el.checked)" class="h-4 w-4 rounded border-gray-300 text-[#26488C] focus:ring-[#26488C]">
                        <span class="text-sm text-gray-800">{{ $org->org_acronym ? $org->org_acronym . ' - ' : '' }}{{ $org->name }}</span>
                        <span class="ml-auto text-[11px] text-gray-500">{{ $org->type }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="flex gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <button type="button" @click="showEditOrgModal = false" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="button" @click="$dispatch('org-picker-done-edit', { ids: ids }); showEditOrgModal = false" class="flex-1 rounded-lg bg-[#26488C] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Done (<span x-text="ids.length"></span>)</button>
                </div>
            </div>
            @endif
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;" 
         x-data="{ isDeleting: false }"
         @delete-complete.window="showDeleteModal = false; deleteAnnouncement = null; isDeleting = false"
         @delete-error.window="isDeleting = false">
        <div class="flex items-center justify-center min-h-screen py-4 px-4">
            <div @click="if (!isDeleting) showDeleteModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-warning text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Announcement</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to delete "<span x-text="deleteAnnouncement?.title" class="font-semibold"></span>"? This action cannot be undone.
                    </p>
                    <div class="flex space-x-3">
                        <button @click="showDeleteModal = false" :disabled="isDeleting" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button @click="isDeleting = true; confirmDeleteAnnouncement(deleteAnnouncement.id)" :disabled="isDeleting" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                            <i :class="isDeleting ? 'ph ph-spinner ph-spin mr-2' : 'ph ph-trash mr-2'"></i>
                            <span x-text="isDeleting ? 'Deleting...' : 'Delete'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast Notification -->
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

    <!-- Info Toast Notification -->
    @if(session('info'))
    <div x-data="{ show: true }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-x-8"
         x-transition:enter-end="opacity-100 transform translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-x-0"
         x-transition:leave-end="opacity-0 transform translate-x-8"
         x-init="setTimeout(() => show = false, 4000)" 
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-blue-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" 
         style="display: none;">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                <i class="ph ph-info text-xl text-blue-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Info!</p>
            <p class="text-sm text-gray-600">{{ session('info') }}</p>
        </div>
        <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
    @endif
</div>

<style>
[x-cloak] { display: none !important; }
.img-hover-card { display: none; }
.group:hover .img-hover-card { display: block; }
    
    /* Rich Text Editor Styling */
    [contenteditable]:empty:before {
        content: attr(placeholder);
        color: #9CA3AF;
        pointer-events: none;
    }
    [contenteditable]:focus {
        outline: none;
    }
    [contenteditable] a {
        color: var(--primary-color);
        text-decoration: underline;
    }
</style>

<script>
function insertLink() {
    const url = prompt('Enter URL:');
    if (url) {
        const text = prompt('Enter link text:', url);
        if (text) {
            document.execCommand('insertHTML', false, `<a href="${url}" target="_blank">${text}</a>`);
        }
    }
}

// Auto-convert pasted URLs to clickable links
document.addEventListener('DOMContentLoaded', function() {
    const editors = ['create-editor', 'edit-editor'];
    
    editors.forEach(editorId => {
        const editor = document.getElementById(editorId);
        if (editor) {
            editor.addEventListener('paste', function(e) {
                e.preventDefault();
                
                // Get pasted text
                const text = (e.clipboardData || window.clipboardData).getData('text');
                
                // Check if it's a URL
                const urlPattern = /^(https?:\/\/)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/;
                
                if (urlPattern.test(text.trim())) {
                    // It's a URL, make it clickable
                    let url = text.trim();
                    // Add https:// if not present
                    if (!url.startsWith('http://') && !url.startsWith('https://')) {
                        url = 'https://' + url;
                    }
                    document.execCommand('insertHTML', false, `<a href="${url}" target="_blank">${url}</a>`);
                } else {
                    // Not a URL, paste as plain text
                    document.execCommand('insertText', false, text);
                }
            });
        }
    });
});
</script>

<script>
function confirmDeleteAnnouncement(announcementId) {
    if (!announcementId) {
        console.error('No announcement ID found');
        showToast('No announcement ID found', 'error');
        return;
    }
    
    console.log('Deleting announcement:', announcementId);
    
    const url = `{{ url($announcementBasePath) }}/${announcementId}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Delete failed');
            }
            return data;
        });
    })
    .then(data => {
        console.log('Delete successful:', data);
        
        // Dispatch event to close modal
        window.dispatchEvent(new CustomEvent('delete-complete'));
        
        // Show success toast
        showToast('Announcement deleted successfully!', 'success');
        
        // Real-time update will handle the UI refresh
        // No page reload needed!
    })
    .catch(error => {
        console.error('Delete error:', error);
        showToast(error.message || 'Failed to delete announcement', 'error');
        
        // Dispatch event to reset deleting state
        window.dispatchEvent(new CustomEvent('delete-error'));
    });
}

function showToast(message, type = 'success') {
    const isInfo = type === 'info';
    const borderColor = type === 'success' ? 'border-green-500' : isInfo ? 'border-blue-500' : 'border-red-500';
    const bgColor = type === 'success' ? 'bg-green-100' : isInfo ? 'bg-blue-100' : 'bg-red-100';
    const textColor = type === 'success' ? 'text-green-600' : isInfo ? 'text-blue-600' : 'text-red-600';
    const icon = type === 'success' ? 'ph-check-circle' : isInfo ? 'ph-info' : 'ph-warning-circle';
    const title = type === 'success' ? 'Success!' : isInfo ? 'Updated' : 'Error!';
    
    const toast = document.createElement('div');
    toast.className = `fixed top-6 right-6 z-[60] bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md transition-all`;
    toast.innerHTML = `
        <div class='flex-shrink-0'>
            <div class='h-10 w-10 rounded-full ${bgColor} flex items-center justify-center'>
                <i class='ph ${icon} text-xl ${textColor}'></i>
            </div>
        </div>
        <div class='flex-1'>
            <p class='text-sm font-semibold text-gray-900'>${title}</p>
            <p class='text-sm text-gray-600'>${message}</p>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4000);
}
</script>

@endsection
