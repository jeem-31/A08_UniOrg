@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Request Events - Admin')

@section('content')
<style>
[x-cloak] { display: none !important; }

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
    transition: opacity 0.3s ease-out;
}

.action-btn-full { display: none !important; }
.action-btn-icon { display: inline-flex !important; }
.action-btn-icon > div.absolute {
    background-color: #ffffff !important;
    color: var(--primary-color);
    border: 1px solid #eceaf6;
    box-shadow: 0 6px 16px rgba(0,0,0,.12);
    font-weight: 600;
}
.action-btn-icon > div.absolute > div:last-child { border-top-color: #ffffff !important; }
.action-btn-icon.text-red-600 > div.absolute { color: #dc2626; }
.img-hover-card { display: none; }
.group:hover .img-hover-card { display: block; }
</style>

<script>
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-6 right-6 z-[60] bg-white border-l-4 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md transition-all';
    toast.className += type === 'error' ? ' border-red-500' : type === 'success' ? ' border-green-500' : ' border-blue-500';
    const iconClass = type === 'error' ? 'ph-warning-circle text-red-600' : type === 'success' ? 'ph-check-circle text-green-600' : 'ph-info text-blue-600';
    const bgClass = type === 'error' ? 'bg-red-100' : type === 'success' ? 'bg-green-100' : 'bg-blue-100';
    const title = type === 'error' ? 'Error!' : type === 'success' ? 'Success!' : 'Info';
    toast.innerHTML = '<div class="flex-shrink-0"><div class="h-10 w-10 rounded-full ' + bgClass + ' flex items-center justify-center"><i class="ph ' + iconClass + ' text-xl"></i></div></div><div class="flex-1"><p class="text-sm font-semibold text-gray-900">' + title + '</p><p class="text-sm text-gray-600">' + message + '</p></div><button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>';
    document.body.appendChild(toast);
    setTimeout(() => { 
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Format a date value for datetime-local inputs using the browser's local
// timezone (toISOString would shift to UTC and change the saved time).
function formatLocalDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Set minimum date/time to now for all datetime inputs
function initializeEventDateInputs() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const minDateTime = now.toISOString().slice(0, 16);
    
    const dateInputs = document.querySelectorAll('input[type="datetime-local"]');
    dateInputs.forEach(input => {
        input.setAttribute('min', minDateTime);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEventDateInputs);
} else {
    initializeEventDateInputs();
}
</script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{
    showCreateModal: false,
    showEditModal: false,
    showDeleteModal: false,
    showPostModal: false,
    showSubmitToOsasModal: false,
    showRejectionReasonModal: false,
    showResubmitModal: false,
    showRequestRescheduleModal: false,
    showRescheduleReasonModal: false,
    venueOptions: {{ Js::from($venues) }},
    categoryOptions: {{ Js::from($categories ?? []) }},
    selectedVenue: '',
    customVenue: '',
    isCustomVenue: false,
    editEvent: null,
    deleteEvent: null,
    postEvent: null,
    submitToOsasEvent: null,
    rejectionReasonEvent: null,
    resubmitEvent: null,
    rescheduleEvent: null,
    rescheduleReasonEvent: null,
    events: {{ json_encode($events->toArray()) }},
    updateEvent(updatedEvent) {
        const eventsList = this.events.data || this.events;
        const eventIndex = eventsList.findIndex(e => e.id === updatedEvent.id);
        if (eventIndex !== -1) {
            // Update the event properties individually to trigger reactivity
            Object.keys(updatedEvent).forEach(key => {
                eventsList[eventIndex][key] = updatedEvent[key];
            });
            // Force Alpine to detect the change by reassigning the entire events object
            if (this.events.data) {
                this.events = { ...this.events, data: [...eventsList] };
            } else {
                this.events = [...eventsList];
            }
        }
    },
    openEditModal(event) {
        this.editEvent = event;
        if (event.venue && !this.venueOptions.includes(event.venue)) {
            this.isCustomVenue = true;
            this.customVenue = event.venue;
            this.selectedVenue = '__other__';
        } else {
            this.isCustomVenue = false;
            this.selectedVenue = event.venue || '';
            this.customVenue = '';
        }
        this.showEditModal = true;
    }
}" x-init="
    (() => {
        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('edit_event');
        if (editId) {
            const eventsList = events.data || events;
            const event = eventsList.find(e => e.id == editId);
            if (event) {
                $nextTick(() => {
                    openEditModal(event);
                    window.history.replaceState({}, '', window.location.pathname);
                });
            }
        }
    })();
    
    // Listen for real-time event updates from Dean
    if (window.Echo) {
        window.Echo.channel('events')
            .listen('.event-updated', (e) => {
                console.log('✅ Admin received: event-updated', e);
                // Fetch updated event data
                fetch(`{{ url('admin/events') }}/${e.id}/json`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Use Alpine's $data to access the component
                            const eventsList = $data.events.data || $data.events;
                            const eventIndex = eventsList.findIndex(ev => ev.id === data.event.id);
                            if (eventIndex !== -1) {
                                // Update the event properties individually
                                Object.keys(data.event).forEach(key => {
                                    eventsList[eventIndex][key] = data.event[key];
                                });
                                // Force Alpine to detect the change
                                if ($data.events.data) {
                                    $data.events = { ...$data.events, data: [...eventsList] };
                                } else {
                                    $data.events = [...eventsList];
                                }
                            }
                            showToast('Event status updated!', 'success');
                        }
                    })
                    .catch(error => console.error('Error fetching event:', error));
            });
    }
">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Request Events</h1>
            <p class="text-gray-600 mt-2">Submit event requests for Super Admin approval</p>
        </div>
        @hasFullAccess('events')
        <button @click="showCreateModal = true" style="background-color: var(--primary-color);" class="hover: text-white px-6 py-3 rounded-lg font-semibold transition flex items-center">
            <i style="color: var(--complementary-color);" class="ph ph-plus mr-2 "></i> Request Event
        </button>
        @endHasFullAccess
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
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                <i class="ph ph-check-circle text-xl text-green-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Success!</p>
            <p class="text-sm text-gray-600">{!! e(session('success')) !!}</p>
        </div>
        <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
    @endif

    <!-- Error Toast Notification -->
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
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                <i class="ph ph-warning-circle text-xl text-red-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Error!</p>
            <p class="text-sm text-gray-600">{!! e(session('error')) !!}</p>
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
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-blue-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                <i class="ph ph-info text-xl text-blue-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Info</p>
            <p class="text-sm text-gray-600">{!! e(session('info')) !!}</p>
        </div>
        <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>
    @endif

    <!-- Validation Errors Toast -->
    @if($errors->any())
    <div x-data="{ show: true }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-x-8"
         x-transition:enter-end="opacity-100 transform translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-x-0"
         x-transition:leave-end="opacity-0 transform translate-x-8"
         x-init="setTimeout(() => show = false, 5000)"
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 max-w-md">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="ph ph-warning text-xl text-red-600"></i>
                </div>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900 mb-2">Please fix the following errors:</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="text-sm text-gray-600">{!! e($error) !!}</li>
                    @endforeach
                </ul>
            </div>
            <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-visible">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venue</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Participants</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="(event, index) in (events.data || events)" :key="event.id">
                    <tr class="hover:bg-gray-50 relative">
                        <td class="px-6 py-4 overflow-visible">
                            <div class="flex items-center">
                                <template x-if="event.images && event.images.length > 0">
                                    <div class="relative group mr-3" @mouseenter="$nextTick(() => $el.querySelector('.img-grid-scroll')?.scrollTo(0, 0))">
                                        <img :src="window.storageUrl(event.images[0])" :alt="event.title" class="w-12 h-12 rounded-lg object-cover" onerror="var g=this.closest('.group'); if(g){g.querySelectorAll('img').forEach(function(i){i.style.display='none'}); var b=g.querySelector('.img-count-badge'); if(b){b.style.display='none'} var f=g.querySelector('.img-broken-fallback'); if(f){f.classList.remove('hidden'); f.classList.add('flex')}}">
                                        <div class="img-broken-fallback hidden w-12 h-12 rounded-lg bg-purple-100 items-center justify-center">
                                            <i style="color: var(--primary-color);" class="ph ph-calendar"></i>
                                        </div>
                                        <template x-if="event.images.length > 1">
                                            <span style="background-color: var(--primary-color);" class="img-count-badge absolute -top-1 -right-1 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold" x-text="event.images.length"></span>
                                        </template>
                                        
                                        <!-- Hover preview for all images (including single image) -->
                                        <div x-show="event.images && event.images.length > 0" style="display: none;">
                                            <!-- Invisible bridge to keep hover active -->
                                            <div class="absolute left-full top-0 h-12 w-4 img-hover-card z-[9999]"></div>

                                            <!-- Hover Preview - Position based on row index (first 3 down, last 3 up) -->
                                            <div class="absolute left-full ml-4 img-hover-card z-[9999]" :class="index < 3 ? 'top-0' : 'bottom-0'">
                                                    <div class="bg-white rounded-lg shadow-2xl border-2 border-purple-200 p-3 w-80">
                                                        <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
                                                            <h4 class="text-sm font-bold text-gray-900">
                                                                <i style="color: var(--primary-color);" class="ph ph-images mr-1"></i>
                                                                <span x-text="event.images.length"></span>
                                                                <span x-text="event.images.length === 1 ? ' Image' : ' Images'"></span>
                                                            </h4>
                                                        </div>
                                                        <div class="img-grid-scroll grid grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                                                            <template x-for="(image, idx) in event.images" :key="idx">
                                                                <div class="relative">
                                                                    <img :src="window.storageUrl(image)" :alt="'Image ' + (idx + 1)" class="w-full h-32 object-cover rounded border border-gray-200" onerror="this.closest('.relative').style.display='none'">
                                                                    <div style="background-color: var(--primary-color);" class="absolute top-1 left-1 text-white text-xs px-2 py-0.5 rounded font-bold shadow" x-text="idx + 1"></div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                </template>
                                <template x-if="!event.images || event.images.length === 0">
                                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                                        <i style="color: var(--primary-color);" class="ph ph-calendar "></i>
                                    </div>
                                </template>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 whitespace-nowrap overflow-hidden text-ellipsis" style="max-width: 150px;" x-text="event.title"></div>
                                    <div class="text-sm text-gray-500 whitespace-nowrap overflow-hidden text-ellipsis" style="max-width: 150px;" x-text="event.description ? event.description.replace(/<[^>]*>/g, '').substring(0, 50) : ''"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 flex items-center">
                                <i style="color: var(--primary-color);" class="ph ph-map-pin  mr-1 flex-shrink-0"></i>
                                <span class="whitespace-nowrap overflow-hidden text-ellipsis" style="max-width: 120px;" x-text="event.venue"></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="relative group inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 whitespace-nowrap overflow-visible" style="max-width: 64px;">
                                <span class="overflow-hidden text-ellipsis" style="max-width: 64px;" x-text="((event.category||'').indexOf('outreach')===0 ? 'OP' : ((event.category||'').indexOf('general')===0 ? 'GA' : (event.category_label || event.category || '—')))"></span>
                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 font-semibold border shadow-lg" style="color: var(--primary-color); border-color: #eceaf6;" x-text="(categoryOptions[event.category] || event.category_label || event.category || 'Category')"></span>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })"></div>
                            <div class="text-sm text-gray-500" x-text="new Date(event.start_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900" x-text="'₱' + parseFloat(event.planned_budget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <div class="flex items-center justify-center">
                                <i style="color: var(--primary-color);" class="ph ph-users  mr-1"></i>
                                <span class="text-sm font-semibold text-gray-900" x-text="event.registrations_count || 0"></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <!-- Approval Status Icon -->
                                <div class="relative group" x-show="event.approval_status === 'approved' && !event.osas_status">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-check-circle text-blue-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Approved by Dean
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'approved' && event.osas_status === 'pending'">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center cursor-help">
                                        <svg class="animate-spin h-4 w-4 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Waiting for OSAS approval
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'approved' && event.osas_status === 'approved'">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-check-circle text-green-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        OSAS Approved
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'approved' && event.osas_status === 'approved' && !event.hardcopy_received_at">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center cursor-help">
                                        <svg class="animate-spin h-4 w-4 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Awaiting Received
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'rejected'">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-x-circle text-red-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Rejected
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'approved' && event.osas_status === 'rejected'">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-x-circle text-red-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Rejected by OSAS
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.proposal?.reschedule_status === 'pending' && new Date(event.end_date) > new Date()">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center cursor-help">
                                        <svg class="animate-spin h-4 w-4 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Reschedule Pending OSAS
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.proposal?.reschedule_status === 'rejected' && new Date(event.end_date) > new Date()">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-calendar-x text-red-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Reschedule Rejected by OSAS
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.proposal?.reschedule_status === 'approved' && new Date(event.end_date) > new Date()">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-calendar-check text-blue-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Reschedule Approved - You can now edit this event
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.approval_status === 'pending'">
                                    <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center cursor-help">
                                        <svg class="animate-spin h-4 w-4 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Awaiting Approval
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>

                                
                                <!-- Event Status Icon -->
                                <div class="relative group" x-show="new Date(event.end_date) < new Date()">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-flag-checkered text-gray-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Completed
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="relative group" x-show="event.is_published && event.status === 'active'">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center cursor-help">
                                        <i class="ph ph-globe text-green-600 text-lg"></i>
                                    </div>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                        Posted
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <!-- Dean approved - show Submit -->
                            <template x-if="event.approval_status === 'approved' && !event.osas_status && !event.is_published">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="submitToOsasEvent = event; showSubmitToOsasModal = true" class="action-btn-full text-blue-600 hover:text-blue-900 items-center">
                                        <i class="ph ph-paper-plane-tilt text-blue-600 mr-1"></i> Submit
                                    </button>
                                    <button @click="submitToOsasEvent = event; showSubmitToOsasModal = true" class="action-btn-icon text-blue-600 hover:text-blue-900 items-center relative group">
                                        <i class="ph ph-paper-plane-tilt text-blue-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Submit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- Submitted to OSAS - waiting (icon only, centered under the action buttons; details on hover + Status column) -->
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'pending' && !event.is_published">
                                <div class="flex items-center justify-center space-x-3">
                                    <button type="button" class="action-btn-icon text-yellow-600 hover:text-yellow-700 items-center relative group cursor-help">
                                        <i class="ph ph-hourglass-medium text-yellow-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Waiting for OSAS review
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- OSAS approved - waiting for hardcopy, edit only -->
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'approved' && !event.hardcopy_received_at && !event.is_published">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <template x-if="event.proposal?.reschedule_status === 'pending'">
                                        <span class="text-sm text-amber-500 italic">Reschedule pending OSAS review...</span>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status !== 'pending' && new Date(event.end_date) > new Date()">
                                        <button @click="rescheduleEvent = event; showRequestRescheduleModal = true" class="action-btn-full text-amber-600 hover:text-amber-900 items-center">
                                            <i class="ph ph-calendar-blank text-amber-600 mr-1"></i> Reschedule
                                        </button>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status !== 'pending' && new Date(event.end_date) > new Date()">
                                        <button @click="rescheduleEvent = event; showRequestRescheduleModal = true" class="action-btn-icon text-amber-600 hover:text-amber-900 items-center relative group">
                                            <i class="ph ph-calendar-blank text-amber-600"></i>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                                Request Reschedule
                                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                            </div>
                                        </button>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status === 'rejected'">
                                        <button @click="rescheduleReasonEvent = event; showRescheduleReasonModal = true" class="action-btn-full text-red-500 hover:text-red-700 items-center">
                                            <i class="ph ph-eye text-red-500 mr-1"></i> View Reason
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <!-- OSAS approved - hardcopy received, ready to post -->
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'approved' && event.hardcopy_received_at && !event.is_published">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <template x-if="event.proposal?.reschedule_status === 'pending'">
                                        <span class="text-sm text-amber-500 italic">Reschedule pending OSAS review...</span>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status !== 'pending' && new Date(event.end_date) > new Date()">
                                        <button @click="rescheduleEvent = event; showRequestRescheduleModal = true" class="action-btn-full text-amber-600 hover:text-amber-900 items-center">
                                            <i class="ph ph-calendar-blank text-amber-600 mr-1"></i> Reschedule
                                        </button>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status !== 'pending' && new Date(event.end_date) > new Date()">
                                        <button @click="rescheduleEvent = event; showRequestRescheduleModal = true" class="action-btn-icon text-amber-600 hover:text-amber-900 items-center relative group">
                                            <i class="ph ph-calendar-blank text-amber-600"></i>
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                                Request Reschedule
                                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                            </div>
                                        </button>
                                    </template>
                                    <template x-if="event.proposal?.reschedule_status === 'rejected'">
                                        <button @click="rescheduleReasonEvent = event; showRescheduleReasonModal = true" class="action-btn-full text-red-500 hover:text-red-700 items-center">
                                            <i class="ph ph-eye text-red-500 mr-1"></i> View Reason
                                        </button>
                                    </template>
                                    <button @click="postEvent = event; showPostModal = true" class="action-btn-full text-green-600 hover:text-green-900 items-center">
                                        <i class="ph ph-paper-plane-tilt text-green-600 mr-1"></i> Post Now
                                    </button>
                                    <button @click="postEvent = event; showPostModal = true" class="action-btn-icon text-green-600 hover:text-green-900 items-center relative group">
                                        <i class="ph ph-paper-plane-tilt text-green-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Post Now
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- OSAS rejected - edit, resubmit, view reason or delete -->
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'rejected'">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-full text-red-600 hover:text-red-900 items-center">
                                        <i class="ph ph-trash text-red-600 mr-1"></i> Delete
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-icon text-red-600 hover:text-red-900 items-center relative group">
                                        <i class="ph ph-trash text-red-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Delete
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="rejectionReasonEvent = event; showRejectionReasonModal = true" class="action-btn-full text-amber-600 hover:text-amber-900 items-center">
                                        <i class="ph ph-eye text-amber-600 mr-1"></i> View
                                    </button>
                                    <button @click="rejectionReasonEvent = event; showRejectionReasonModal = true" class="action-btn-icon text-amber-600 hover:text-amber-900 items-center relative group">
                                        <i class="ph ph-eye text-amber-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            View Reason
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- Pending - edit or delete -->
                            <template x-if="event.approval_status === 'pending'">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-full text-red-600 hover:text-red-900 items-center">
                                        <i class="ph ph-trash text-red-600 mr-1"></i> Delete
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-icon text-red-600 hover:text-red-900 items-center relative group">
                                        <i class="ph ph-trash text-red-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Delete
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- Rejected by dean - view reason, resubmit or delete -->
                            <template x-if="event.approval_status === 'rejected'">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-full text-red-600 hover:text-red-900 items-center">
                                        <i class="ph ph-trash text-red-600 mr-1"></i> Delete
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-icon text-red-600 hover:text-red-900 items-center relative group">
                                        <i class="ph ph-trash text-red-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Delete
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="rejectionReasonEvent = event; showRejectionReasonModal = true" class="action-btn-full text-amber-600 hover:text-amber-900 items-center">
                                        <i class="ph ph-eye text-amber-600 mr-1"></i> View
                                    </button>
                                    <button @click="rejectionReasonEvent = event; showRejectionReasonModal = true" class="action-btn-icon text-amber-600 hover:text-amber-900 items-center relative group">
                                        <i class="ph ph-eye text-amber-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            View Reason
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                            <!-- Published - edit or delete -->
                            <template x-if="event.is_published && event.status === 'active'">
                                <div class="flex items-center space-x-3">
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-full hover:text-purple-900 items-center">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple mr-1"></i> Edit
                                    </button>
                                    <button @click="openEditModal(event)" style="color: var(--primary-color);" class="action-btn-icon hover:text-purple-900 items-center relative group">
                                        <i style="color: var(--primary-color);" class="ph ph-pencil-simple"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Edit
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-full text-red-600 hover:text-red-900 items-center">
                                        <i class="ph ph-trash text-red-600 mr-1"></i> Delete
                                    </button>
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="action-btn-icon text-red-600 hover:text-red-900 items-center relative group">
                                        <i class="ph ph-trash text-red-600"></i>
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Delete
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </button>
                                </div>
                            </template>
                        </td>
                    </tr>
                </template>
                
                <!-- Empty state -->
                <tr x-show="!(events.data || events) || (events.data || events).length === 0">
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        No events yet. Request your first event!
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $events->links() }}
    </div>

    <!-- Create Event Request Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showCreateModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-6xl w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Request New Event</h2>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <form action="{{ route('admin.events.store') }}" method="POST" enctype="multipart/form-data" id="create-event-form" data-admin-inplace-form x-data="{
                    imagePreviews: [], 
                    selectedFiles: [],
                    isSubmitting: false,
                    venueOptions: {{ Js::from($venues) }},
                    availableVenues: {{ Js::from($venues) }},
                    selectedVenue: '',
                    isCustomVenue: false,
                    customVenue: '',
                    venueConflictMessage: '',
                    conflictingEvents: [],
                    showConflictModal: false,
                    selectedLetterFile: null,
                    selectedLetterName: '',
                    formatConflictDate(dateStr) {
                        const d = new Date((dateStr || '').replace(' ', 'T'));
                        if (isNaN(d.getTime())) return dateStr;
                        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
                    },
                    get currentVenue() {
                        return this.isCustomVenue ? this.customVenue : this.selectedVenue;
                    },
                    get venueConflicts() {
                        // No venue picked yet (dates filled first) - show all same-date conflicts as a heads up
                        if (!this.currentVenue) return this.conflictingEvents;
                        // Venue picked - only flag conflicts for that specific venue
                        return this.conflictingEvents.filter(e => e.venue === this.currentVenue);
                    },
                    openConflictModal() {
                        this.showConflictModal = true;
                        this.$parent.showCreateModal = false;
                    },
                    closeConflictModal() {
                        this.showConflictModal = false;
                        this.$parent.showCreateModal = true;
                    },
                    selectLetterFile(event) {
                        const file = event.target.files[0];
                        if (file) {
                            if (!/\.(pdf|doc|docx)$/i.test(file.name)) {
                                showToast('Only PDF or Word documents are allowed', 'error');
                                event.target.value = '';
                                return;
                            }
                            if (file.size > 10 * 1024 * 1024) {
                                showToast('File exceeds 10MB', 'error');
                                event.target.value = '';
                                return;
                            }
                            this.selectedLetterFile = file;
                            this.selectedLetterName = file.name;
                        }
                    },
                    removeLetterFile() {
                        this.selectedLetterFile = null;
                        this.selectedLetterName = '';
                        this.$refs.createRequestLetter.value = '';
                    },
                    addFiles(newFiles) {
                        const filesArray = Array.from(newFiles);
                        
                        filesArray.forEach(file => {
                            if (file.size > 5 * 1024 * 1024) {
                                showToast('Image ' + file.name + ' exceeds 5MB', 'error');
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
                    syncEndDateMin() {
                        const startInput = this.$refs.createStartDate;
                        const endInput = this.$refs.createEndDate;
                        if (!startInput?.value) return;

                        // End date can never be earlier than start date
                        endInput.setAttribute('min', startInput.value);
                        if (endInput.value && endInput.value < startInput.value) {
                            endInput.value = startInput.value;
                        }
                    },
                    checkVenueAvailability() {
                        this.syncEndDateMin();
                        const startDate = this.$refs.createStartDate?.value;
                        const endDate = this.$refs.createEndDate?.value;
                        
                        if (startDate && endDate) {
                            fetch(`{{ url('admin/events/venue-availability') }}?start_date=${startDate}&end_date=${endDate}`)
                                .then(response => response.json())
                                .then(data => {
                                    this.availableVenues = data.available_venues;
                                    this.conflictingEvents = data.conflicting_events;
                                    
                                    if (this.selectedVenue && this.venueOptions.includes(this.selectedVenue) && !this.availableVenues.includes(this.selectedVenue)) {
                                        this.venueConflictMessage = 'Selected venue is not available at that time';
                                    } else {
                                        this.venueConflictMessage = '';
                                    }
                                });
                        }
                    }
                }" @submit="if (isSubmitting) { $event.preventDefault(); } else { isSubmitting = true; }">
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Left Side - Multiple Images Preview -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Upload Images <span class="text-red-500">*</span></label>
                            
                            <!-- Fixed Height Container for Images -->
                            <div class="h-80 mb-3 border-2 border-gray-200 rounded-lg p-3 bg-gray-50">
                                <!-- Empty State -->
                                <div x-show="imagePreviews.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400">
                                    <i class="ph ph-images text-6xl mb-3"></i>
                                    <p class="text-lg font-semibold">No images uploaded</p>
                                    <p class="text-sm">Click button below to add images</p>
                                </div>
                                
                                <!-- Image Previews Grid -->
                                <div x-show="imagePreviews.length > 0" class="grid grid-cols-2 gap-3 h-full overflow-y-auto">
                                    <template x-for="(preview, index) in imagePreviews" :key="index">
                                        <div class="relative group h-36">
                                            <img :src="preview" class="w-full h-full object-cover rounded-lg border-2 border-gray-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeFile(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div style="background-color: var(--primary-color);" class="absolute top-2 right-2  text-white text-sm px-2 py-1 rounded font-bold shadow-lg" x-text="index + 1"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Upload Button (Bottom) - Fixed at bottom -->
                            <div style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover: transition cursor-pointer"
                                 @click="$refs.createImagesInput.click()">
                                <input type="file" name="images[]" accept="image/*" multiple required id="create-images-upload" x-ref="createImagesInput"
                                    @change="addFiles($event.target.files);"
                                    class="hidden">
                                <i class="ph ph-cloud-arrow-up text-5xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600 font-semibold text-lg">Click to add images</p>
                                <p class="text-sm text-gray-500 mt-2">
                                    <span x-show="selectedFiles.length === 0">PNG, JPG up to 5MB each</span>
                                    <span x-show="selectedFiles.length > 0" style="color: var(--primary-color);" class="font-semibold ">
                                        <span x-text="selectedFiles.length"></span> image(s) selected
                                    </span>
                                </p>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Request Letter <span class="text-red-500">*</span></label>
                                <div style="border-color: var(--primary-color); min-height: 48px;" class="border-2 border-dashed rounded-lg px-4 py-2 flex items-center hover:opacity-80 transition cursor-pointer"
                                     @click="!selectedLetterFile && $refs.createRequestLetter.click()">
                                    <input type="file" name="request_letter" accept=".pdf,.doc,.docx" required id="create-request-letter" x-ref="createRequestLetter" @change="selectLetterFile($event)" class="hidden">
                                    <template x-if="!selectedLetterFile">
                                        <div class="flex items-center">
                                            <i class="ph ph-file-text text-lg text-gray-400 mr-3"></i>
                                            <div>
                                                <p class="text-gray-600 font-semibold text-sm">Click to upload request letter</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="selectedLetterFile">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center">
                                                <template x-if="selectedLetterName.endsWith('.pdf')">
                                                    <i class="ph ph-file-pdf text-lg text-red-500 mr-3"></i>
                                                </template>
                                                <template x-if="selectedLetterName.endsWith('.doc') || selectedLetterName.endsWith('.docx')">
                                                    <i class="ph ph-file-doc text-lg text-blue-600 mr-3"></i>
                                                </template>
                                                <span class="text-sm font-semibold text-gray-700" x-text="selectedLetterName"></span>
                                            </div>
                                            <button type="button" @click.stop="removeLetterFile()" class="text-red-500 hover:text-red-700">
                                                <i class="ph ph-x"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side - Form Fields -->
                        <div class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" placeholder="Enter event title">
                            </div>
                            <div x-data="{ description: '' }">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                                
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
                                <div id="create-event-editor" 
                                     contenteditable="true" 
                                     style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); height: 120px; max-height: 120px; overflow-y: auto;" 
                                     class="w-full px-4 py-3 border border-t-0 border-gray-300 rounded-b-lg focus:ring-2 focus:outline-none"
                                     @input="description = $el.innerHTML"
                                     placeholder="Enter event description..."></div>
                                
                                <!-- Hidden input to submit content -->
                                <input type="hidden" name="description" :value="description" required>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-medium text-gray-700">Start Date & Time <span class="text-red-500">*</span></label>
                                        <button type="button" onclick="window.openSmartScheduler('create')"
                                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full text-white transition hover:opacity-90 active:scale-95" style="background-color: var(--primary-color);">
                                            <i class="ph ph-sparkle"></i> Suggest
                                        </button>
                                    </div>
                                    <input type="datetime-local" name="start_date" x-ref="createStartDate" @change="checkVenueAvailability()" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date & Time <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-2 relative z-10">
                                        <input type="datetime-local" name="end_date" x-ref="createEndDate" @change="checkVenueAvailability()" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="flex-1 min-w-0 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none">
                                        <button type="button" x-show="venueConflicts.length > 0" x-cloak @click.stop.prevent="openConflictModal()"
                                                class="relative z-10 flex-shrink-0 w-9 h-9 rounded-full bg-red-100 hover:bg-red-200 flex items-center justify-center text-red-600 focus:outline-none transition pointer-events-auto" title="Same date events found - click for details">
                                            <i class="ph ph-warning animate-pulse"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Venue <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div x-show="!isCustomVenue" class="relative">
                                            <select required x-model="selectedVenue" @change="if(selectedVenue === '__other__') { isCustomVenue = true; customVenue = ''; $nextTick(() => $refs.createVenueInput?.focus()) }; venueConflictMessage = ''" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); appearance: none;-webkit-appearance: none;-moz-appearance: none;" class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none">
                                                <option value="">Select Venue</option>
                                                <template x-for="venue in venueOptions" :key="venue">
                                                    <option :value="venue" x-text="venue" :disabled="!availableVenues.includes(venue)"></option>
                                                </template>
                                                <option value="__other__">Other</option>
                                            </select>
                                            <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                        </div>
                                        <div x-show="isCustomVenue" class="relative">
                                            <input type="text" x-ref="createVenueInput" x-model="customVenue" @input="venueConflictMessage = ''" :required="isCustomVenue" placeholder="Type venue name" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none pr-10">
                                            <button type="button" @click="isCustomVenue = false; selectedVenue = ''; customVenue = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 bg-white px-1">
                                                <i class="ph ph-caret-down"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="venue" :value="isCustomVenue ? customVenue : selectedVenue">
                                    </div>
                                    <div x-show="venueConflictMessage" class="mt-1 text-xs text-red-600" x-text="venueConflictMessage"></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="category" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); appearance: none;-webkit-appearance: none;-moz-appearance: none;" class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none">
                                            <option value="">Select Category</option>
                                            @foreach(($categories ?? []) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Planned Budget <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500">₱</span>
                                    <input type="number" name="planned_budget" step="0.01" min="0" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" placeholder="0.00">
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                                <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2  hover: text-white rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                    <i style="color: var(--complementary-color);" class="ph mr-2 " :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-paper-plane-tilt'"></i>
                                    <span x-show="!isSubmitting">Submit Request</span>
                                    <span x-show="isSubmitting">Submitting...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Same-Date Conflict Modal (Create) -->
                    <template x-teleport="body">
                        <div x-show="showConflictModal" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" style="display: none;">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div @click="closeConflictModal()" class="fixed inset-0 bg-black/60 modal-backdrop"></div>
                                <div class="relative bg-white rounded-lg shadow-2xl max-w-lg w-full p-6">
                                    <div class="flex items-center mb-4">
                                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center mr-3 flex-shrink-0">
                                            <i class="ph ph-warning text-red-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900">Same Date Event Conflict</h3>
                                            <p class="text-sm text-gray-500">
                                                <span x-text="venueConflicts.length"></span>
                                                <span x-text="venueConflicts.length === 1 ? ' event' : ' events'"></span> found on the same schedule
                                            </p>
                                        </div>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto space-y-3 mb-6">
                                        <template x-for="event in venueConflicts" :key="event.id">
                                            <div class="border border-red-200 bg-red-50 rounded-lg p-3">
                                                <p class="font-semibold text-gray-900 text-sm" x-text="event.title"></p>
                                                <p class="text-sm text-gray-700 mt-1">
                                                    <i class="ph ph-map-pin text-red-500 mr-1"></i>
                                                    Venue: <span class="font-medium" x-text="event.venue"></span>
                                                </p>
                                                <p class="text-sm text-gray-700">
                                                    <i class="ph ph-clock text-red-500 mr-1"></i>
                                                    From <span class="font-medium" x-text="formatConflictDate(event.start_date)"></span>
                                                    to <span class="font-medium" x-text="formatConflictDate(event.end_date)"></span>
                                                </p>
                                                <p class="text-sm text-gray-700" x-show="event.organization">
                                                    <i class="ph ph-buildings text-red-500 mr-1"></i>
                                                    Organization: <span class="font-medium" x-text="event.organization"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" @click="closeConflictModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold">
                                            <i class="ph ph-arrow-left mr-2"></i>Back to Form
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </form>
            </div>
        </div>
    </div>

    <!-- Post Event Confirmation Modal -->
    <div x-show="showPostModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showPostModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <i class="ph ph-paper-plane-tilt text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Post Event</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to post "<span x-text="postEvent?.title" class="font-semibold"></span>"? This will make it visible to all students.
                    </p>
                    <div class="flex space-x-3" x-data="{ isPosting: false }">
                        <button @click="showPostModal = false" :disabled="isPosting" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isPosting }">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button @click="
                            isPosting = true;
                            fetch(`{{ url('admin/events') }}/${postEvent.id}/post`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Event posted successfully!', 'success');
                                    showPostModal = false;
                                    // Update event in local array
                                    const eventsList = events.data || events;
                                    const eventIndex = eventsList.findIndex(e => e.id === postEvent.id);
                                    if (eventIndex !== -1) {
                                        eventsList[eventIndex].is_published = true;
                                        eventsList[eventIndex].status = 'active';
                                    }
                                } else {
                                    showToast(data.message || 'Failed to post event', 'error');
                                }
                                isPosting = false;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Failed to post event. Please try again.', 'error');
                                isPosting = false;
                            });
                        " :disabled="isPosting" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isPosting }">
                            <i class="ph mr-2" :class="isPosting ? 'ph-spinner ph-spin' : 'ph-paper-plane-tilt'"></i>
                            <span x-show="!isPosting">Post Now</span>
                            <span x-show="isPosting">Posting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Modal -->
    <div x-show="showSubmitToOsasModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showSubmitToOsasModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                        <i class="ph ph-paper-plane-tilt text-3xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Submit</h3>
                    <div class="text-left bg-gray-50 rounded-lg p-4 mb-4 text-sm">
                        <p class="font-semibold text-gray-900 mb-1" x-text="submitToOsasEvent?.title"></p>
                        <p class="text-gray-600"><span class="font-medium">Venue:</span> <span x-text="submitToOsasEvent?.venue"></span></p>
                        <p class="text-gray-600"><span class="font-medium">Budget:</span> ₱<span x-text="submitToOsasEvent?.planned_budget ? parseFloat(submitToOsasEvent.planned_budget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''"></span></p>
                        <p class="text-gray-600"><span class="font-medium">Date:</span> <span x-text="submitToOsasEvent?.start_date ? new Date(submitToOsasEvent.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : ''"></span></p>
                    </div>
                    <p class="text-gray-600 mb-6">
                        Submit this event to OSAS for verification and final approval?
                    </p>
                    <div class="flex space-x-3" x-data="{ isSubmitting: false }">
                        <button @click="showSubmitToOsasModal = false" :disabled="isSubmitting" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button @click="
                            isSubmitting = true;
                            fetch(`{{ url('admin/events') }}/${submitToOsasEvent.id}/submit-to-osas`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Event submitted to OSAS!', 'success');
                                    showSubmitToOsasModal = false;
                                    const eventsList = events.data || events;
                                    const eventIndex = eventsList.findIndex(e => e.id === submitToOsasEvent.id);
                                    if (eventIndex !== -1) {
                                        eventsList[eventIndex].osas_status = 'pending';
                                    }
                                } else {
                                    showToast(data.message || 'Failed to submit', 'error');
                                }
                                isSubmitting = false;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Failed to submit event. Please try again.', 'error');
                                isSubmitting = false;
                            });
                        " :disabled="isSubmitting" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                            <i class="ph mr-2" :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-paper-plane-tilt'"></i>
                            <span x-show="!isSubmitting">Submit</span>
                            <span x-show="isSubmitting">Submitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div x-show="showRejectionReasonModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRejectionReasonModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-red-100 mr-3">
                            <i class="ph ph-x-circle text-xl text-red-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Reason for Rejection</h3>
                    </div>
                    <button @click="showRejectionReasonModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-base font-bold text-gray-900 mb-3" x-text="rejectionReasonEvent?.title"></p>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1" x-text="rejectionReasonEvent?.osas_status === 'rejected' ? 'Rejected by OSAS' : 'Rejected by Dean'"></p>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-gray-800 text-sm leading-relaxed" x-text="(rejectionReasonEvent?.osas_status === 'rejected' ? rejectionReasonEvent?.proposal?.osas_rejection_reason : rejectionReasonEvent?.proposal?.rejection_reason) || 'No reason provided'"></p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button @click="showRejectionReasonModal = false" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Reschedule Modal -->
    <div x-show="showRequestRescheduleModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRequestRescheduleModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-data="{ rescheduleText: '', isSubmittingReschedule: false }">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-4">
                        <i class="ph ph-calendar-blank text-3xl text-amber-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Request Reschedule</h3>
                    <p class="text-gray-600 mb-2">This will ask OSAS for permission to change the date, time, venue, or budget for:</p>
                    <p class="text-sm font-medium text-gray-900 mb-4" x-text="rescheduleEvent?.title"></p>
                    <div class="text-left mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Reason <span class="text-red-500">*</span></label>
                        <textarea x-model="rescheduleText" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="e.g. Typhoon Kristine forced us to move the venue and date..."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showRequestRescheduleModal = false; rescheduleText = ''" :disabled="isSubmittingReschedule" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmittingReschedule }">
                            <i class="ph ph-arrow-left mr-2"></i>Cancel
                        </button>
                        <button @click="
                            if (!rescheduleText.trim()) { showToast('Please provide a reason for the reschedule request.', 'error'); return; }
                            isSubmittingReschedule = true;
                            fetch(`{{ url('admin/events') }}/${rescheduleEvent.id}/request-reschedule`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({ reschedule_reason: rescheduleText })
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showRequestRescheduleModal = false; rescheduleText = ''; window.clearAdminPageCache && window.clearAdminPageCache(); window.refreshCurrentAdminPage && window.refreshCurrentAdminPage(); }
                                else { showToast(d.message || 'Failed', 'error'); isSubmittingReschedule = false; }
                            }).catch(() => { showToast('Error submitting reschedule request', 'error'); isSubmittingReschedule = false; });
                        " :disabled="isSubmittingReschedule || !rescheduleText.trim()" class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmittingReschedule || !rescheduleText.trim() }">
                            <i class="ph mr-2" :class="isSubmittingReschedule ? 'ph-spinner ph-spin' : 'ph-paper-plane-tilt'"></i>
                            <span x-text="isSubmittingReschedule ? 'Sending...' : 'Send to OSAS'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Rejection Reason Modal -->
    <div x-show="showRescheduleReasonModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRescheduleReasonModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-red-100 mr-3">
                            <i class="ph ph-calendar-x text-xl text-red-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Reschedule Request Rejected</h3>
                    </div>
                    <button @click="showRescheduleReasonModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-base font-bold text-gray-900 mb-3" x-text="rescheduleReasonEvent?.title"></p>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Reason from OSAS</p>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-gray-800 text-sm leading-relaxed" x-text="rescheduleReasonEvent?.proposal?.reschedule_rejection_reason || 'No reason provided'"></p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button @click="showRescheduleReasonModal = false" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resubmit Confirmation Modal -->
    <div x-show="showResubmitModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showResubmitModal = false; showEditModal = true" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-4">
                        <i class="ph ph-arrow-clockwise text-3xl text-amber-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Resubmit Event?</h3>
                    <template x-if="resubmitEvent?.approval_status === 'approved' && resubmitEvent?.osas_status === 'rejected'">
                        <p class="text-gray-600 mb-2">This event was rejected by OSAS. The Dean has already approved it, so resubmitting will send it straight back to OSAS.</p>
                    </template>
                    <template x-if="!(resubmitEvent?.approval_status === 'approved' && resubmitEvent?.osas_status === 'rejected')">
                        <p class="text-gray-600 mb-2">This event was previously rejected. Resubmit it for Dean review?</p>
                    </template>
                    <p class="text-sm font-medium text-gray-900 mb-6" x-text="resubmitEvent?.title"></p>
                    <div class="flex space-x-3" x-data="{ isResubmitting: false }">
                        <button @click="showResubmitModal = false; showEditModal = true" :disabled="isResubmitting" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isResubmitting }">
                            <i class="ph ph-arrow-left mr-2"></i>Back
                        </button>
                        <button @click="
                            isResubmitting = true;
                            fetch(`{{ url('admin/events') }}/${resubmitEvent.id}/resubmit`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) {
                                    showToast(d.message, 'success');
                                    showResubmitModal = false;
                                    showEditModal = false;
                                    window.clearAdminPageCache && window.clearAdminPageCache();
                                    window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
                                } else {
                                    showToast(d.message || 'Failed', 'error');
                                    isResubmitting = false;
                                }
                            })
                            .catch(() => { showToast('Error resubmitting event', 'error'); isResubmitting = false; });
                        " :disabled="isResubmitting" class="flex-1 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isResubmitting }">
                            <i class="ph mr-2" :class="isResubmitting ? 'ph-spinner ph-spin' : 'ph-arrow-clockwise'"></i>
                            <span x-show="!isResubmitting">Yes, Resubmit</span>
                            <span x-show="isResubmitting">Resubmitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showEditModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-6xl w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Edit Event</h2>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                <template x-if="editEvent?.proposal?.reschedule_status === 'approved'">
                    <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start">
                        <i class="ph ph-calendar-check text-blue-600 mt-0.5 mr-2"></i>
                        <p class="text-sm text-blue-800">OSAS approved your reschedule request. You can now update the date, time, venue, and budget for this event.</p>
                    </div>
                </template>
                <template x-if="editEvent">
                <form :action="`{{ url('admin/events') }}/${editEvent.id}`" method="POST" enctype="multipart/form-data" id="edit-event-form" data-admin-inplace-form x-data="{
                    existingImages: [],
                    existingImageUrls: [],
                    newImagePreviews: [],
                    newSelectedFiles: [],
                    editDescription: '',
                    isSubmitting: false,
                    availableVenues: {{ Js::from($venues) }},
                    selectedVenue: '',
                    customVenue: '',
                    isCustomVenue: false,
                    venueConflictMessage: '',
                    conflictingEvents: [],
                    showConflictModal: false,
                    selectedLetterFile: null,
                    selectedLetterName: '',
                    formatConflictDate(dateStr) {
                        const d = new Date((dateStr || '').replace(' ', 'T'));
                        if (isNaN(d.getTime())) return dateStr;
                        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
                    },
                    get currentVenue() {
                        return this.isCustomVenue ? this.customVenue : this.selectedVenue;
                    },
                    get venueConflicts() {
                        // No venue picked yet (dates filled first) - show all same-date conflicts as a heads up
                        if (!this.currentVenue) return this.conflictingEvents;
                        // Venue picked - only flag conflicts for that specific venue
                        return this.conflictingEvents.filter(e => e.venue === this.currentVenue);
                    },
                    openConflictModal() {
                        this.showConflictModal = true;
                        this.$parent.showEditModal = false;
                    },
                    closeConflictModal() {
                        this.showConflictModal = false;
                        this.$parent.showEditModal = true;
                    },
                    selectLetterFile(event) {
                        const file = event.target.files[0];
                        if (file) {
                            if (!/\.(pdf|doc|docx)$/i.test(file.name)) {
                                showToast('Only PDF or Word documents are allowed', 'error');
                                event.target.value = '';
                                return;
                            }
                            if (file.size > 10 * 1024 * 1024) {
                                showToast('File exceeds 10MB', 'error');
                                event.target.value = '';
                                return;
                            }
                            this.selectedLetterFile = file;
                            this.selectedLetterName = file.name;
                        }
                    },
                    removeLetterFile() {
                        this.selectedLetterFile = null;
                        this.selectedLetterName = '';
                        this.$refs.editRequestLetter.value = '';
                    },
                    init() {
                        this.initFormFromEvent(editEvent);
                        // Watch for future changes
                        this.$watch('editEvent', (value) => {
                            if (value) this.initFormFromEvent(value);
                        });
                    },
                    initFormFromEvent(event) {
                        if (!event) return;
                        if (event.images) {
                            this.existingImages = [...event.images];
                            this.existingImageUrls = event.images_urls && event.images_urls.length > 0 
                                ? [...event.images_urls] 
                                : [...event.images];
                            this.newImagePreviews = [];
                            this.newSelectedFiles = [];
                        }
                        if (event.description) {
                            this.editDescription = event.description;
                            setTimeout(() => {
                                const editor = document.getElementById('edit-event-editor');
                                if(editor) editor.innerHTML = event.description;
                            }, 100);
                        }
                        const venues = {{ Js::from($venues) }};
                        if (event.venue && !venues.includes(event.venue)) {
                            this.isCustomVenue = true;
                            this.customVenue = event.venue;
                            this.selectedVenue = '__other__';
                        } else {
                            this.isCustomVenue = false;
                            this.selectedVenue = event.venue || '';
                            this.customVenue = '';
                        }
                        // Programmatically set the select value to ensure it displays correctly
                        this.$nextTick(() => {
                            const select = this.$refs.editVenueSelect;
                            if (select) {
                                select.value = this.selectedVenue;
                            }
                        });
                    },
                    removeExisting(index) {
                        this.existingImages.splice(index, 1);
                        this.existingImageUrls.splice(index, 1);
                    },
                    addNewFiles(files) {
                        const filesArray = Array.from(files);
                        
                        filesArray.forEach(file => {
                            if (file.size > 5 * 1024 * 1024) {
                                showToast('Image ' + file.name + ' exceeds 5MB', 'error');
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
                    syncEndDateMin() {
                        const startInput = this.$refs.editStartDate;
                        const endInput = this.$refs.editEndDate;
                        if (!startInput?.value) return;

                        // End date can never be earlier than start date
                        endInput.setAttribute('min', startInput.value);
                        if (endInput.value && endInput.value < startInput.value) {
                            endInput.value = startInput.value;
                        }
                    },
                    checkVenueAvailability() {
                        this.syncEndDateMin();
                        const startDate = this.$refs.editStartDate?.value;
                        const endDate = this.$refs.editEndDate?.value;
                        
                        if (startDate && endDate) {
                            fetch(`{{ url('admin/events/venue-availability') }}?start_date=${startDate}&end_date=${endDate}&exclude_event_id=${editEvent.id}`)
                                .then(response => response.json())
                                .then(data => {
                                    this.availableVenues = data.available_venues;
                                    this.conflictingEvents = data.conflicting_events;
                                    
                                    if (this.selectedVenue && this.venueOptions.includes(this.selectedVenue) && !this.availableVenues.includes(this.selectedVenue)) {
                                        this.venueConflictMessage = 'Selected venue is not available at that time';
                                    } else {
                                        this.venueConflictMessage = '';
                                    }
                                });
                        }
                    }
                }" @submit="if (isSubmitting) { $event.preventDefault(); } else { isSubmitting = true; }">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Left Side - Multiple Images Management -->
                        <div class="flex flex-col">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Manage Images</label>
                            
                            <!-- Fixed Height Container for Images -->
                            <div class="h-80 mb-3 border-2 border-gray-200 rounded-lg p-3 bg-gray-50">
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
                                            <img :src="existingImageUrls[index]" class="w-full h-full object-cover rounded-lg border-2 border-gray-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeExisting(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div class="absolute top-2 right-2 bg-blue-600 text-white text-sm px-2 py-1 rounded font-bold shadow-lg" x-text="index + 1"></div>
                                            <input type="hidden" :name="'existing_images[]'" :value="image">
                                        </div>
                                    </template>
                                    
                                    <!-- New Images -->
                                    <template x-for="(preview, index) in newImagePreviews" :key="'new-' + index">
                                        <div class="relative group h-36">
                                            <img :src="preview" class="w-full h-full object-cover rounded-lg border-2 border-purple-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                                <button type="button" @click="removeNewFile(index)" class="text-white text-sm bg-red-500 px-3 py-2 rounded hover:bg-red-600">
                                                    <i class="ph ph-trash mr-1"></i> Remove
                                                </button>
                                            </div>
                                            <div style="background-color: var(--primary-color);" class="absolute top-2 right-2  text-white text-sm px-2 py-1 rounded font-bold shadow-lg">NEW</div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Upload Button (Bottom) -->
                            <div style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover: transition cursor-pointer"
                                 @click="$refs.editImagesInput.click()">
                                <input type="file" name="images[]" accept="image/*" multiple x-ref="editImagesInput"
                                    @change="addNewFiles($event.target.files);"
                                    class="hidden">
                                <i class="ph ph-cloud-arrow-up text-5xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600 font-semibold text-lg">Click to add more images</p>
                                <p class="text-sm text-gray-500 mt-2">
                                    <span x-show="(existingImages.length + newSelectedFiles.length) === 0">PNG, JPG up to 5MB each</span>
                                    <span x-show="(existingImages.length + newSelectedFiles.length) > 0" style="color: var(--primary-color);" class="font-semibold ">
                                        <span x-text="existingImages.length + newSelectedFiles.length"></span> image(s) total
                                    </span>
                                </p>
                            </div>
                            <div class="mt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Request Letter</label>
                                <div style="border-color: var(--primary-color); min-height: 48px;" class="border-2 border-dashed rounded-lg px-4 py-2 flex items-center hover:opacity-80 transition cursor-pointer"
                                     @click="$refs.editRequestLetter.click()">
                                    <input type="file" name="request_letter" accept=".pdf,.doc,.docx" id="edit-request-letter" x-ref="editRequestLetter" @change="selectLetterFile($event)" class="hidden">
                                    <template x-if="!selectedLetterFile && !editEvent?.proposal?.request_letter">
                                        <div class="flex items-center">
                                            <i class="ph ph-file-text text-lg text-gray-400 mr-3"></i>
                                            <div>
                                                <p class="text-gray-600 font-semibold text-sm">Click to upload request letter</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!selectedLetterFile && editEvent?.proposal?.request_letter">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center">
                                                <i class="ph ph-file-pdf text-lg text-red-500 mr-3"></i>
                                                <div>
                                                    <p class="text-gray-600 font-semibold text-sm">Request letter uploaded</p>
                                                </div>
                                            </div>
                                            <button type="button" @click.stop="$refs.editRequestLetter.click()" class="text-xs text-amber-600 hover:text-amber-800 font-medium">
                                                <i class="ph ph-arrows-clockwise mr-1"></i> Change
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="selectedLetterFile">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="flex items-center">
                                                <template x-if="selectedLetterName.endsWith('.pdf')">
                                                    <i class="ph ph-file-pdf text-lg text-red-500 mr-3"></i>
                                                </template>
                                                <template x-if="selectedLetterName.endsWith('.doc') || selectedLetterName.endsWith('.docx')">
                                                    <i class="ph ph-file-doc text-lg text-blue-600 mr-3"></i>
                                                </template>
                                                <span class="text-sm font-semibold text-gray-700" x-text="selectedLetterName"></span>
                                            </div>
                                            <button type="button" @click.stop="removeLetterFile()" class="text-red-500 hover:text-red-700">
                                                <i class="ph ph-x"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side - Form Fields -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" :value="editEvent.title" required style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                                
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
                                <div id="edit-event-editor" 
                                     contenteditable="true" 
                                     style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); height: 120px; max-height: 120px; overflow-y: auto;" 
                                     class="w-full px-4 py-3 border border-t-0 border-gray-300 rounded-b-lg focus:ring-2 focus:outline-none"
                                     @input="editDescription = $el.innerHTML"></div>
                                
                                <!-- Hidden input to submit content -->
                                <input type="hidden" name="description" :value="editDescription" required>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-medium text-gray-700">Start Date & Time <span class="text-red-500">*</span></label>
                                        <button type="button" onclick="window.openSmartScheduler('edit')"
                                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full text-white transition hover:opacity-90 active:scale-95" style="background-color: var(--primary-color);">
                                            <i class="ph ph-sparkle"></i> Suggest
                                        </button>
                                    </div>
                                    <input type="datetime-local" name="start_date" x-ref="editStartDate" :value="formatLocalDateTime(editEvent.start_date)" @change="checkVenueAvailability()" required :readonly="editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" :class="{ 'bg-gray-100 cursor-not-allowed': editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved' }">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date & Time <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-2 relative z-10">
                                        <input type="datetime-local" name="end_date" x-ref="editEndDate" :value="formatLocalDateTime(editEvent.end_date)" @change="checkVenueAvailability()" required :readonly="editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="flex-1 min-w-0 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" :class="{ 'bg-gray-100 cursor-not-allowed': editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved' }">
                                        <button type="button" x-show="venueConflicts.length > 0" x-cloak @click.stop.prevent="openConflictModal()"
                                                class="relative z-10 flex-shrink-0 w-9 h-9 rounded-full bg-red-100 hover:bg-red-200 flex items-center justify-center text-red-600 focus:outline-none transition pointer-events-auto" title="Same date events found - click for details">
                                            <i class="ph ph-warning animate-pulse"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Venue <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <div x-show="!isCustomVenue" class="relative">
                                            <select x-ref="editVenueSelect" required :value="selectedVenue" @change="selectedVenue = $event.target.value; if(selectedVenue === '__other__') { isCustomVenue = true; customVenue = ''; $nextTick(() => $refs.editVenueInput?.focus()) }; venueConflictMessage = ''" :disabled="(editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); appearance: none;-webkit-appearance: none;-moz-appearance: none;" class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" :class="{ 'bg-gray-100 cursor-not-allowed': (editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished' }">
                                                <option value="">Select Venue</option>
                                                <template x-for="venue in venueOptions" :key="venue">
                                                    <option :value="venue" x-text="venue" :disabled="!availableVenues.includes(venue)"></option>
                                                </template>
                                                <option value="__other__">Other</option>
                                            </select>
                                            <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                        </div>
                                        <div x-show="isCustomVenue" class="relative">
                                            <input type="text" x-ref="editVenueInput" :value="customVenue" @input="customVenue = $event.target.value" :required="isCustomVenue" placeholder="Type venue name" :disabled="(editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none pr-10" :class="{ 'bg-gray-100 cursor-not-allowed': (editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished' }">
                                            <button type="button" @click="isCustomVenue = false; selectedVenue = ''; customVenue = ''" :disabled="(editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished'" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 bg-white px-1" :class="{ 'opacity-50 cursor-not-allowed': (editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished' }">
                                                <i class="ph ph-caret-down"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="venue" :value="isCustomVenue ? customVenue : selectedVenue">
                                    </div>
                                    <div x-show="venueConflictMessage" class="mt-1 text-xs text-red-600" x-text="venueConflictMessage"></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <select name="category" required :value="editEvent.category" :disabled="(editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color); appearance: none;-webkit-appearance: none;-moz-appearance: none;" class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" :class="{ 'bg-gray-100 cursor-not-allowed': (editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved') || editEvent.status === 'finished' }">
                                            <option value="">Select Category</option>
                                            @foreach(($categories ?? []) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Planned Budget <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500">₱</span>
                                    <input type="number" name="planned_budget" step="0.01" min="0" :value="editEvent.planned_budget" required :readonly="editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved'" style="border-color: var(--primary-color); --tw-ring-color: var(--primary-color);" class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" :class="{ 'bg-gray-100 cursor-not-allowed': editEvent.approval_status === 'approved' && editEvent.proposal?.reschedule_status !== 'approved' }">
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                                <template x-if="editEvent?.approval_status === 'rejected' || (editEvent?.approval_status === 'approved' && editEvent?.osas_status === 'rejected')">
                                    <button type="button" @click="resubmitEvent = editEvent; showEditModal = false; showResubmitModal = true" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold transition">
                                        <i class="ph ph-arrow-clockwise mr-2"></i> Resubmit Event
                                    </button>
                                </template>
                                <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2  hover: text-white rounded-lg font-semibold" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                    <i style="color: var(--complementary-color);" class="ph mr-2 " :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-floppy-disk'"></i>
                                    <span x-show="!isSubmitting">Update Event</span>
                                    <span x-show="isSubmitting">Updating...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Same-Date Conflict Modal (Edit) -->
                    <template x-teleport="body">
                        <div x-show="showConflictModal" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" style="display: none;">
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div @click="closeConflictModal()" class="fixed inset-0 bg-black/60 modal-backdrop"></div>
                                <div class="relative bg-white rounded-lg shadow-2xl max-w-lg w-full p-6">
                                    <div class="flex items-center mb-4">
                                        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center mr-3 flex-shrink-0">
                                            <i class="ph ph-warning text-red-600 text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900">Same Date Event Conflict</h3>
                                            <p class="text-sm text-gray-500">
                                                <span x-text="venueConflicts.length"></span>
                                                <span x-text="venueConflicts.length === 1 ? ' event' : ' events'"></span> found on the same schedule
                                            </p>
                                        </div>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto space-y-3 mb-6">
                                        <template x-for="event in venueConflicts" :key="event.id">
                                            <div class="border border-red-200 bg-red-50 rounded-lg p-3">
                                                <p class="font-semibold text-gray-900 text-sm" x-text="event.title"></p>
                                                <p class="text-sm text-gray-700 mt-1">
                                                    <i class="ph ph-map-pin text-red-500 mr-1"></i>
                                                    Venue: <span class="font-medium" x-text="event.venue"></span>
                                                </p>
                                                <p class="text-sm text-gray-700">
                                                    <i class="ph ph-clock text-red-500 mr-1"></i>
                                                    From <span class="font-medium" x-text="formatConflictDate(event.start_date)"></span>
                                                    to <span class="font-medium" x-text="formatConflictDate(event.end_date)"></span>
                                                </p>
                                                <p class="text-sm text-gray-700" x-show="event.organization">
                                                    <i class="ph ph-buildings text-red-500 mr-1"></i>
                                                    Organization: <span class="font-medium" x-text="event.organization"></span>
                                                </p>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" @click="closeConflictModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold">
                                            <i class="ph ph-arrow-left mr-2"></i>Back to Form
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </form>
                </template>
            </div>
        </div>
    </div>

    @include('admin.events.partials._smart-scheduler')

    <!-- Delete Event Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showDeleteModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-warning text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Event</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to delete "<span x-text="deleteEvent?.title" class="font-semibold"></span>"? This action cannot be undone.
                    </p>
                    <div class="flex space-x-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <form :action="`{{ url('admin/events') }}/${deleteEvent?.id}`" method="POST" data-admin-inplace-form class="flex-1" x-data="{ isDeleting: false }" @submit="isDeleting = true">
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
</div>

<style>
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
// Auto-convert pasted URLs to clickable links
function attachPasteHandler(editorId) {
    const editor = document.getElementById(editorId);
    if (editor && !editor.dataset.pasteHandlerAttached) {
        editor.dataset.pasteHandlerAttached = 'true';
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
}

document.addEventListener('DOMContentLoaded', function() {
    // Attach to create editor immediately
    attachPasteHandler('create-event-editor');
    
    // Watch for edit modal to open and attach handler
    const observer = new MutationObserver(function(mutations) {
        attachPasteHandler('edit-event-editor');
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>

@endsection
