@extends('layouts.dean')

@section('title', 'Event Proposals')

@section('content')
<style>
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
</script>

<div class="container mx-auto px-4 py-8" x-data="{
    showApproveModal: false,
    showRejectModal: false,
    showDeleteModal: false,
    showViewModal: false,
    selectedEvent: null,
    deleteEvent: null,
    viewEvent: null,
    currentImage: 0,
    activeTab: 'details',
    activeStatus: '{{ request('status') ?? 'all' }}',
    allEvents: {{ Js::from($events->items()) }},
    ownedVenueMap: {{ Js::from(\App\Models\Event::VENUE_OWNER_MAP) }},
    venueOwner(venue) {
        if (!venue) return null;
        const needle = String(venue).trim().toLowerCase();
        for (const [owned, acronym] of Object.entries(this.ownedVenueMap || {})) {
            if (owned.toLowerCase() === needle) return acronym;
        }
        return null;
    },
    updateEvent(updatedEvent) {
        const eventIndex = this.allEvents.findIndex(e => e.id === updatedEvent.id);
        if (eventIndex !== -1) {
            // Update the event properties individually to trigger reactivity
            Object.keys(updatedEvent).forEach(key => {
                this.allEvents[eventIndex][key] = updatedEvent[key];
            });
            // Force Alpine to detect the change
            this.allEvents = [...this.allEvents];
        }
    },
    get filteredEvents() {
        if (this.activeStatus === 'all') {
            return this.allEvents;
        }
        return this.allEvents.filter(event => event.approval_status === this.activeStatus);
    },
    cleanHTML(html) {
        if (!html) return '';
        return html.replace(/&nbsp;/g, ' ').replace(/\s\s+/g, ' ');
    }
}">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold">Event Proposals</h1>
            <p class="text-gray-600 mt-1">Review and approve event requests from admins</p>
        </div>
    </div>

    <!-- Status Filter -->
    <div class="mb-6 bg-white rounded-lg shadow p-4">
        <div class="flex flex-wrap gap-2">
            <button @click="activeStatus = 'all'"
                    :style="activeStatus === 'all' ? 'background-color: var(--primary-color); color: white;' : ''"
                    :class="activeStatus === 'all' ? '' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition border border-gray-200">
                <i style="color: var(--complementary-color);" class="ph ph-list mr-2 "></i>All Events
            </button>
            <button @click="activeStatus = 'pending'" 
                    :class="activeStatus === 'pending' ? 'bg-yellow-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition border border-gray-200">
                <i style="color: var(--complementary-color);" class="ph ph-clock mr-2 "></i>Pending
            </button>
            <button @click="activeStatus = 'approved'" 
                    :class="activeStatus === 'approved' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition border border-gray-200">
                <i style="color: var(--complementary-color);" class="ph ph-check-circle mr-2 "></i>Approved
            </button>
            <button @click="activeStatus = 'rejected'" 
                    :class="activeStatus === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition border border-gray-200">
                <i style="color: var(--complementary-color);" class="ph ph-x-circle mr-2 "></i>Rejected
            </button>
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

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Planned Budget</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="event in filteredEvents" :key="event.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <template x-if="event.images && event.images.length > 0">
                                    <img :src="window.storageUrl(event.images[0])" :alt="event.title" class="w-12 h-12 rounded-lg object-cover mr-3">
                                </template>
                                <template x-if="!event.images || event.images.length === 0">
                                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                                        <i style="color: var(--primary-color);" class="ph ph-calendar "></i>
                                    </div>
                                </template>
                                <div>
                                    <div class="font-medium text-gray-900" x-text="event.title"></div>
                                    <div class="text-sm text-gray-500" x-text="(() => { if (!event.description) return ''; const temp = document.createElement('div'); temp.innerHTML = event.description; const text = temp.textContent.replace(/\s+/g, ' ').trim(); return text.length > 50 ? text.substring(0, 50) + '...' : text; })()"></div>
                                    <div class="text-xs text-gray-500 mt-1"><i class="ph ph-map-pin"></i> <span x-text="event.venue"></span></div>
                                    <template x-if="venueOwner(event.venue)">
                                        <span class="mt-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-purple-100 text-purple-800 whitespace-nowrap">
                                            <i class="ph ph-map-pin"></i> <span x-text="`Uses ${event.venue} (${venueOwner(event.venue)} Premise · Advisory)`"></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900" x-text="'₱' + parseFloat(event.planned_budget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900" x-text="new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })"></div>
                            <div class="text-xs text-gray-500" x-text="new Date(event.start_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <template x-if="event.approval_status === 'pending'">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 flex items-center w-fit">
                                    <i class="ph ph-clock mr-1"></i> Pending
                                </span>
                            </template>
                            <template x-if="event.approval_status === 'approved' && (!event.osas_status || event.osas_status === 'pending')">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 flex items-center w-fit">
                                    <i class="ph ph-hourglass-medium mr-1"></i> For OSAS approval
                                </span>
                            </template>
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'approved'">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 flex items-center w-fit">
                                    <i class="ph ph-check-circle mr-1"></i> Approved by OSAS
                                </span>
                            </template>
                            <template x-if="event.approval_status === 'rejected'">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 flex items-center w-fit relative group cursor-help">
                                    <i class="ph ph-x-circle mr-1"></i> Rejected
                                    <template x-if="event.proposal?.rejection_reason">
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-normal max-w-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-left">
                                            <p class="font-semibold mb-1">Reason:</p>
                                            <p x-text="event.proposal.rejection_reason"></p>
                                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </template>
                                </span>
                            </template>
                            <template x-if="event.approval_status === 'approved' && event.osas_status === 'rejected'">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 flex items-center w-fit">
                                    <i class="ph ph-x-circle mr-1"></i> Rejected by OSAS
                                </span>
                            </template>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center space-x-2">
                                <button @click="viewEvent = event; currentImage = 0; activeTab = 'details'; showViewModal = true" class="text-blue-600 hover:text-blue-900 font-semibold">
                                    <i class="ph ph-eye mr-1"></i> View
                                </button>
                                <template x-if="event.approval_status === 'pending'">
                                    <div class="flex items-center space-x-2">
                                        <button @click="selectedEvent = event; showApproveModal = true" class="text-green-600 hover:text-green-900 font-semibold">
                                            <i class="ph ph-check mr-1"></i> Approve
                                        </button>
                                        <button @click="selectedEvent = event; showRejectModal = true" class="text-red-600 hover:text-red-900 font-semibold">
                                            <i class="ph ph-x mr-1"></i> Reject
                                        </button>
                                    </div>
                                </template>
                                <template x-if="event.approval_status === 'approved'">
                                    <span class="text-gray-400 text-xs" x-text="'Approved on ' + new Date(event.approved_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })"></span>
                                </template>
                                <template x-if="event.approval_status === 'rejected'">
                                    <button @click="deleteEvent = event; showDeleteModal = true" class="text-red-600 hover:text-red-900 font-semibold">
                                        <i class="ph ph-trash mr-1"></i> Delete
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>
                
                <!-- Empty state -->
                <tr x-show="filteredEvents.length === 0">
                    <td colspan="6" class="px-6 py-16 text-center">
                        <i class="ph ph-tray text-gray-300 text-6xl mb-4"></i>
                        <p class="text-lg font-semibold text-gray-600">No Events Found</p>
                        <p class="text-sm text-gray-400 mt-2" x-text="activeStatus === 'all' ? 'No event proposals yet.' : 'No ' + activeStatus + ' events found.'"></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $events->links() }}
    </div>

    <!-- Approve Confirmation Modal -->
    <div x-show="showApproveModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showApproveModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <i class="ph ph-check-circle text-3xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Approve Event</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to approve "<span x-text="selectedEvent?.title" class="font-semibold"></span>"? Once approved, this event will be automatically submitted to OSAS for final review.
                    </p>
                    <div class="flex space-x-3" x-data="{ isApproving: false }">
                        <button @click="showApproveModal = false" :disabled="isApproving" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isApproving }">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button @click="
                            isApproving = true;
                            fetch(`{{ url('dean/events') }}/${selectedEvent.id}/approve`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'X-Socket-ID': window.Echo && typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : '',
                                    'Accept': 'application/json'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Event approved and submitted to OSAS!', 'success');
                                    showApproveModal = false;
                                    // Update event in local array
                                    const eventIndex = allEvents.findIndex(e => e.id === selectedEvent.id);
                                    if (eventIndex !== -1) {
                                        allEvents[eventIndex].approval_status = 'approved';
                                        allEvents[eventIndex].approved_at = new Date().toISOString();
                                        allEvents[eventIndex].approved_by = {{ auth()->id() }};
                                        allEvents[eventIndex].dean_approved_at = new Date().toISOString();
                                        allEvents[eventIndex].osas_status = 'pending';
                                    }
                                } else {
                                    showToast(data.message || 'Failed to approve event', 'error');
                                }
                                isApproving = false;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Failed to approve event. Please try again.', 'error');
                                isApproving = false;
                            });
                        " :disabled="isApproving" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isApproving }">
                            <i class="ph mr-2" :class="isApproving ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                            <span x-show="!isApproving">Approve</span>
                            <span x-show="isApproving">Approving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Confirmation Modal -->
    <div x-show="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRejectModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-data="{ rejectionText: '', isRejecting: false }">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-x-circle text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Event</h3>
                    <p class="text-gray-600 mb-4">
                        Are you sure you want to reject "<span x-text="selectedEvent?.title" class="font-semibold"></span>"?
                    </p>
                    <div class="text-left mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                        <textarea x-model="rejectionText" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showRejectModal = false" :disabled="isRejecting" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isRejecting }">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <button @click="
                            if (!rejectionText.trim()) { showToast('Please provide a rejection reason.', 'error'); return; }
                            isRejecting = true;
                            fetch(`{{ url('dean/events') }}/${selectedEvent.id}/reject`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'X-Socket-ID': window.Echo && typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ rejection_reason: rejectionText })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message || 'Event rejected successfully!', 'success');
                                    showRejectModal = false;
                                    const eventIndex = allEvents.findIndex(e => e.id === selectedEvent.id);
                                    if (eventIndex !== -1) {
                                        allEvents[eventIndex].approval_status = 'rejected';
                                        allEvents[eventIndex].approved_at = new Date().toISOString();
                                        allEvents[eventIndex].approved_by = {{ auth()->id() }};
                                    }
                                } else {
                                    showToast(data.message || 'Failed to reject event', 'error');
                                }
                                isRejecting = false;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showToast('Failed to reject event. Please try again.', 'error');
                                isRejecting = false;
                            });
                        " :disabled="isRejecting || !rejectionText.trim()" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isRejecting || !rejectionText.trim() }">
                            <i class="ph mr-2" :class="isRejecting ? 'ph-spinner ph-spin' : 'ph-prohibit'"></i>
                            <span x-show="!isRejecting">Reject</span>
                            <span x-show="isRejecting">Rejecting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showDeleteModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-trash text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Event</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to delete "<span x-text="deleteEvent?.title" class="font-semibold"></span>"? This action cannot be undone.
                    </p>
                    <div class="flex space-x-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="ph ph-x mr-2"></i>Cancel
                        </button>
                        <div class="flex-1" x-data="{ isDeleting: false }">
                            <button type="button" @click="
                                isDeleting = true;
                                fetch(`{{ url('dean/events') }}/${deleteEvent.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'X-Socket-ID': window.Echo && typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : '',
                                        'Accept': 'application/json'
                                    }
                                })
                                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                                .then(({ ok, data }) => {
                                    if (!ok || !data.success) throw new Error(data.message || 'Failed to delete event.');
                                    allEvents = allEvents.filter(event => event.id !== deleteEvent.id);
                                    showDeleteModal = false;
                                    showToast(data.message || 'Event deleted successfully.', 'success');
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    showToast(error.message || 'Failed to delete event. Please try again.', 'error');
                                })
                                .finally(() => { isDeleting = false; });
                            " class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :disabled="isDeleting" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                                <i class="ph mr-2" :class="isDeleting ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                <span x-show="!isDeleting">Delete</span>
                                <span x-show="isDeleting">Deleting...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Event Details Modal -->
    <div x-show="showViewModal" 
         @click.self="showViewModal = false"
         class="fixed inset-0 bg-gray-600/50 modal-backdrop z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="relative max-w-6xl w-full shadow-2xl rounded-lg bg-white overflow-hidden" x-show="viewEvent">
            <button @click="showViewModal = false" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 text-gray-600 hover:text-gray-800 shadow-lg">
                <i class="ph ph-x text-xl"></i>
            </button>
            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-8 pt-4">
                <button @click="activeTab = 'details'" 
                        :class="activeTab === 'details' ? 'border-b-2 text-purple-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2 font-semibold text-sm border-purple-600 mr-6 transition">
                    <i class="ph ph-calendar mr-1"></i> Proposed Event
                </button>
                <button @click="activeTab = 'letter'"
                        :class="activeTab === 'letter' ? 'border-b-2 text-purple-600' : 'text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2 font-semibold text-sm border-purple-600 transition">
                    <i class="ph ph-file-text mr-1"></i> Request Letter
                </button>
            </div>
            <!-- Tab 1: Proposed Event -->
            <div x-show="activeTab === 'details'" class="grid grid-cols-1 md:grid-cols-2">
                <!-- Left: Image Carousel -->
                <div class="relative aspect-square w-full overflow-hidden group bg-gray-100">
                    <template x-if="viewEvent && viewEvent.images && viewEvent.images.length > 0">
                        <div>
                            <template x-for="(image, index) in viewEvent.images" :key="index">
                                <div x-show="currentImage === index" 
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     class="w-full h-full absolute inset-0">
                                    <img :src="window.storageUrl(image)" :alt="viewEvent.title" class="w-full h-full object-contain">
                                </div>
                            </template>
                            <template x-if="viewEvent.images.length > 1">
                                <div>
                                    <button @click="currentImage = currentImage > 0 ? currentImage - 1 : viewEvent.images.length - 1" 
                                            class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                                        <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                        </svg>
                                    </button>
                                    <button @click="currentImage = currentImage < viewEvent.images.length - 1 ? currentImage + 1 : 0" 
                                            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                                        <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                    <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-yellow-600 bg-opacity-80 text-white text-base font-bold px-4 py-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition">
                                        <span x-text="currentImage + 1"></span> / <span x-text="viewEvent.images.length"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!viewEvent?.images || viewEvent.images.length === 0">
                        <div class="bg-gradient-to-br from-blue-400 to-blue-600 h-full flex items-center justify-center">
                            <i class="ph ph-calendar-blank text-white text-8xl"></i>
                        </div>
                    </template>
                </div>
                <!-- Right: Content -->
                <div class="p-8 overflow-y-auto overflow-x-hidden max-h-[600px] custom-scrollbar">
                    <div class="flex items-center text-sm text-gray-500 mb-6">
                        <i class="ph ph-clock mr-2" style="color: var(--complementary-color);"></i>
                        <span x-text="viewEvent?.start_date ? new Date(viewEvent.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : ''"></span>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-6" x-text="viewEvent?.title"></h2>
                    <div class="grid grid-cols-1 gap-4 mb-6 pb-6 border-b border-gray-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg mr-3">
                                    <i class="ph ph-calendar text-xl" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Start Date</p>
                                    <p class="font-semibold text-gray-900" x-text="viewEvent?.start_date ? new Date(viewEvent.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : ''"></p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg mr-3">
                                    <i class="ph ph-calendar text-xl" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">End Date</p>
                                    <p class="font-semibold text-gray-900" x-text="viewEvent?.end_date ? new Date(viewEvent.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : ''"></p>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-lg mr-3">
                                    <i class="ph ph-money-wavy text-xl" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Planned Budget</p>
                                    <p class="font-semibold text-gray-900" x-text="viewEvent?.planned_budget ? '₱' + parseFloat(viewEvent.planned_budget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''"></p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-lg mr-3">
                                    <i class="ph ph-map-pin text-xl" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Venue</p>
                                    <p class="font-semibold text-gray-900" x-text="viewEvent?.venue"></p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg mr-3">
                                    <i class="ph ph-tag text-xl" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Category</p>
                                    <p class="font-semibold text-gray-900" x-text="viewEvent?.category_label || viewEvent?.category || '—'"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="prose max-w-none text-gray-700">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Event Details</h3>
                        <div x-html="cleanHTML(viewEvent?.description)" style="white-space: pre-line;" class="break-words"></div>
                    </div>
                </div>
            </div>
            <!-- Tab 2: Request Letter -->
            <div x-show="activeTab === 'letter'" class="p-8">
                <template x-if="viewEvent?.proposal?.request_letter">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-900">Request Letter</h3>
                            <a :href="`{{ url('dean/proposals') }}/${viewEvent.id}/download-letter`"
                               style="background-color: var(--primary-color);"
                               class="inline-flex items-center px-4 py-2 text-white text-sm rounded-lg font-semibold hover:opacity-90 transition">
                                <i class="ph ph-download-simple mr-2"></i> Download
                            </a>
                        </div>
                        <template x-if="window.storageUrl(viewEvent.proposal.request_letter).toLowerCase().endsWith('.pdf')">
                            <embed :src="window.storageUrl(viewEvent.proposal.request_letter)" 
                                   class="w-full h-[500px] rounded-lg border border-gray-200"
                                   type="application/pdf">
                        </template>
                        <template x-if="!window.storageUrl(viewEvent.proposal.request_letter).toLowerCase().endsWith('.pdf')">
                            <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="ph ph-file-doc text-7xl text-blue-500 mb-4"></i>
                                <p class="text-gray-600 text-sm mb-4">This document cannot be previewed. Please download to view.</p>
                                <a :href="`{{ url('dean/proposals') }}/${viewEvent.id}/download-letter`"
                                   style="background-color: var(--primary-color);"
                                   class="inline-flex items-center px-6 py-3 text-white rounded-lg font-semibold hover:opacity-90 transition">
                                    <i class="ph ph-download-simple mr-2"></i> Download Document
                                </a>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!viewEvent?.proposal?.request_letter">
                    <div class="text-center py-12 text-gray-400">
                        <i class="ph ph-file-text text-7xl mb-6"></i>
                        <h3 class="text-xl font-semibold mb-2">No Request Letter</h3>
                        <p class="text-sm">This event does not have a request letter attached.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

<style>
/* Custom Purple Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8);
}

/* Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: var(--primary-color) #f1f1f1;
}
</style>
</div>
@endsection
