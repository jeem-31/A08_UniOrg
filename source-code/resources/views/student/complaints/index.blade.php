@extends('layouts.app')

@section('title', 'My Submissions')

@section('content')
<div class="student-complaints-page student-neu-page min-h-screen">
<div class="student-page-container max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8" x-data="{
    showViewModal: false, 
    showDeleteConfirm: false,
    isDeletingComplaint: false,
    showCreateModal: false, 
    selectedComplaint: null,
    complaints: {{ json_encode($complaints->items()) }}
}" x-init="@if(request('create') === 'true') showCreateModal = true; @endif">
    <header class="complaints-hero">
        <div class="complaints-hero__copy">
            <p class="complaints-eyebrow">Feedback center</p>
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">My Submissions</h1>
                <button onclick="openCreateModal()" class="complaints-new-btn complaints-new-btn--mobile">
                    <span class="complaints-new-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                    <span>New Submission</span>
                </button>
            </div>
            <p>View your complaints, suggestions, and feedback</p>
        </div>
        <div class="complaints-hero__actions">
            <button onclick="openCreateModal()" class="complaints-new-btn complaints-new-btn--desktop">
                <span class="complaints-new-btn__icon"><i class="ph ph-plus" aria-hidden="true"></i></span>
                <span>New Submission</span>
            </button>
        </div>
    </header>

    @if(session('success'))
        <!-- Success Toast Notification -->
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

    <!-- Grid Layout -->
    <div x-show="complaints.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <template x-for="(complaint, i) in complaints" :key="complaint.id">
                <div role="button" tabindex="0" :style="{ '--complaint-index': i }"
                     class="complaint-card group bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden cursor-pointer transform hover:-translate-y-1 focus:outline-none"
                     @click="selectedComplaint = complaint; showDeleteConfirm = false; isDeletingComplaint = false; showViewModal = true"
                     @keydown.enter.prevent="selectedComplaint = complaint; showDeleteConfirm = false; isDeletingComplaint = false; showViewModal = true"
                     @keydown.space.prevent="selectedComplaint = complaint; showDeleteConfirm = false; isDeletingComplaint = false; showViewModal = true">
                    
                    <!-- Colored Header Bar -->
                    <div class="h-2"
                        :class="{
                            'bg-red-500': complaint.type === 'complaint',
                            'bg-blue-500': complaint.type === 'suggestion',
                            'bg-yellow-500': complaint.type === 'inquiry',
                            'bg-green-500': complaint.type === 'feedback'
                        }">
                    </div>

                    <!-- Card Content -->
                    <div class="p-6">
                        <!-- Type Badge -->
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                :class="{
                                    'bg-red-50 text-red-700': complaint.type === 'complaint',
                                    'bg-blue-50 text-blue-700': complaint.type === 'suggestion',
                                    'bg-yellow-50 text-yellow-700': complaint.type === 'inquiry',
                                    'bg-green-50 text-green-700': complaint.type === 'feedback'
                                }"
                                x-text="complaint.type.charAt(0).toUpperCase() + complaint.type.slice(1)">
                            </span>
                            
                            <!-- Status Badge with Tooltip -->
                            <span class="px-2 py-1 text-xs font-semibold rounded-full relative group/status"
                                :class="{
                                    'bg-green-50 text-green-700': complaint.status === 'resolved',
                                    'bg-blue-50 text-blue-700': complaint.status === 'reviewed',
                                    'bg-orange-50 text-orange-700': complaint.status === 'pending'
                                }"
                                :title="complaint.status.charAt(0).toUpperCase() + complaint.status.slice(1)">
                                <i class="ph" :class="{
                                    'ph-check': complaint.status === 'resolved',
                                    'ph-eye': complaint.status === 'reviewed',
                                    'ph-clock': complaint.status === 'pending'
                                }"></i>
                                <span class="absolute bottom-full right-0 mb-2 px-2 py-1 text-xs text-white bg-gray-900 rounded opacity-0 group-hover/status:opacity-100 transition-opacity whitespace-nowrap pointer-events-none"
                                    x-text="complaint.status.charAt(0).toUpperCase() + complaint.status.slice(1)">
                                </span>
                            </span>
                        </div>

                        <!-- Subject -->
                        <h3 class="text-lg font-bold text-gray-900 mb-2 h-14 overflow-hidden" 
                            x-text="complaint.subject.length > 60 ? complaint.subject.substring(0, 60) + '...' : complaint.subject">
                        </h3>

                        <!-- Message Preview -->
                        <p class="text-sm text-gray-600 mb-4 h-20 overflow-hidden"
                            x-text="complaint.message.length > 100 ? complaint.message.substring(0, 100) + '...' : complaint.message">
                        </p>

                        <!-- Date and View Button -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <span class="text-xs text-gray-500" 
                                x-text="new Date(complaint.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })">
                            </span>
                            <span style="color: var(--primary-color);" class="font-semibold text-sm group-hover:">
                                View <i class="ph ph-arrow-right ml-1"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Pagination -->
        <div x-show="complaints.length" class="complaints-pagination flex justify-center">
            {{ $complaints->links() }}
        </div>
    <div x-show="!complaints.length" style="display: none;" class="complaints-empty bg-white rounded-xl shadow-lg p-12 text-center">
            <i class="ph ph-tray text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">No submissions yet</h3>
            <p class="text-gray-500 mb-6">You haven't submitted any complaints, suggestions, or feedback yet.</p>
            <button onclick="openCreateModal()" style="background-color: var(--primary-color);" class="text-white px-6 py-3 rounded-lg font-semibold hover: transition">
                <i class="ph ph-plus mr-2" style="color: var(--complementary-color);"></i>Create Your First Submission
            </button>
    </div>

    <!-- View Complaint Modal -->
    <div x-show="showViewModal"
         x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="complaints-modal fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-start sm:items-center justify-center min-h-screen p-3 sm:p-4">
            <div @click="showViewModal = false; showDeleteConfirm = false" class="complaints-modal__backdrop fixed inset-0"></div>
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95"
                 class="complaints-modal__surface relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-4 sm:p-6 my-4 sm:my-0 max-h-[95vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Submission Details</h2>
                    <button @click="showViewModal = false; showDeleteConfirm = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                
                <div x-show="selectedComplaint">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4 sm:mb-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full"
                                :class="{
                                    'bg-red-100 text-red-800': selectedComplaint && selectedComplaint.type === 'complaint',
                                    'bg-blue-100 text-blue-800': selectedComplaint && selectedComplaint.type === 'suggestion',
                                    'bg-yellow-100 text-yellow-800': selectedComplaint && selectedComplaint.type === 'inquiry',
                                    'bg-green-100 text-green-800': selectedComplaint && selectedComplaint.type === 'feedback'
                                }">
                                <span x-text="selectedComplaint ? selectedComplaint.type.charAt(0).toUpperCase() + selectedComplaint.type.slice(1) : ''"></span>
                            </span>
                            <span x-show="selectedComplaint && selectedComplaint.is_anonymous" class="px-3 py-1 inline-flex items-center text-xs sm:text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <i class="ph ph-user-focus mr-2"></i> Anonymous
                            </span>
                        </div>
                        <div class="sm:text-right">
                            <span class="px-3 py-1 inline-flex items-center text-xs sm:text-sm leading-5 font-semibold rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': selectedComplaint && selectedComplaint.status === 'resolved',
                                    'bg-blue-100 text-blue-800': selectedComplaint && selectedComplaint.status === 'reviewed',
                                    'bg-yellow-100 text-yellow-800': selectedComplaint && selectedComplaint.status === 'pending'
                                }">
                                <i class="ph mr-2" :class="{
                                    'ph-check': selectedComplaint && selectedComplaint.status === 'resolved',
                                    'ph-eye': selectedComplaint && selectedComplaint.status === 'reviewed',
                                    'ph-clock': selectedComplaint && selectedComplaint.status === 'pending'
                                }"></i>
                                <span x-text="selectedComplaint ? selectedComplaint.status.charAt(0).toUpperCase() + selectedComplaint.status.slice(1) : ''"></span>
                            </span>
                        </div>
                    </div>

                    <div class="mb-4 sm:mb-6">
                        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 sm:mb-3 break-words" x-text="selectedComplaint ? selectedComplaint.subject : ''"></h3>
                        <div class="prose max-w-none">
                            <p class="text-sm sm:text-base text-gray-700 whitespace-pre-wrap break-words" x-text="selectedComplaint ? selectedComplaint.message : ''"></p>
                        </div>
                    </div>

                    <div x-show="selectedComplaint && selectedComplaint.admin_response">
                        <div class="border-t pt-4 sm:pt-6">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 sm:mb-3">
                                <i style="color: var(--primary-color);" class="ph ph-arrow-bend-up-left mr-2"></i>Admin Response
                            </h3>
                            <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
                                <p class="text-sm sm:text-base text-gray-700 whitespace-pre-wrap break-words" x-text="selectedComplaint ? selectedComplaint.admin_response : ''"></p>
                            </div>
                        </div>
                    </div>

                    <div x-show="!selectedComplaint || !selectedComplaint.admin_response">
                        <div class="border-t pt-4 sm:pt-6">
                            <div class="text-center py-6 sm:py-8">
                                <i class="ph ph-hourglass-medium text-gray-400 text-4xl mb-3"></i>
                                <p class="text-sm sm:text-base text-gray-500">No response yet. Our team will review your submission and respond as soon as possible.</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-4 mt-4 flex items-center justify-end gap-3 relative">
                        <button type="button"
                                @click.stop="showDeleteConfirm = !showDeleteConfirm"
                                class="px-4 py-2 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition font-semibold text-sm">
                            <i class="ph ph-trash mr-2"></i>Delete Submission
                        </button>

                        <div x-show="showDeleteConfirm" x-cloak x-transition @click.stop
                             class="delete-confirm-popover absolute right-0 bottom-full mb-2 w-64 rounded-lg border border-gray-200 bg-white p-3 text-left shadow-xl z-10">
                            <p class="text-sm font-semibold text-gray-900">Delete this submission?</p>
                            <p class="mt-1 text-xs text-gray-500">This action cannot be undone.</p>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" @click="showDeleteConfirm = false"
                                        class="px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="button" @click="deleteComplaint(selectedComplaint)" :disabled="isDeletingComplaint"
                                        class="px-3 py-1.5 rounded-md bg-red-600 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <i class="ph mr-1" :class="isDeletingComplaint ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                    <span x-text="isDeletingComplaint ? 'Deleting...' : 'Delete'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Complaint Modal -->
    <div id="create-complaint-modal" class="complaints-modal hidden fixed inset-0 z-50 overflow-y-auto transition-opacity duration-300 opacity-0">
        <div class="flex items-start sm:items-center justify-center min-h-screen p-3 sm:p-4">
            <div onclick="closeCreateModal()" class="complaints-modal__backdrop fixed inset-0"></div>
            <div id="create-complaint-modal-content" class="complaints-modal__surface relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-4 sm:p-6 my-4 sm:my-0 max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-95">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">New Submission</h2>
                    <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>
                
                <form id="complaint-form" action="{{ route('complaints.store') }}" method="POST" onsubmit="submitComplaint(event)">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type <span class="text-red-500">*</span></label>
                            <select name="type" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                                <option value="">Select type...</option>
                                <option value="complaint">Complaint</option>
                                <option value="suggestion">Suggestion</option>
                                <option value="inquiry">Inquiry</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" required maxlength="255" style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:" placeholder="Brief description of your concern">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message <span class="text-red-500">*</span></label>
                            <textarea name="message" rows="6" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:" placeholder="Provide detailed information about your concern..."></textarea>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="is_anonymous" value="1" id="is_anonymous" style="color: var(--primary-color);" class="h-4 w-4  focus:ring-purple-500 border-gray-300 rounded">
                            <label for="is_anonymous" class="ml-2 block text-sm text-gray-700">
                                Submit anonymously (your identity will be hidden)
                            </label>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" id="submit-complaint-btn" style="background-color: var(--primary-color);" class="px-4 py-2  text-white rounded-lg hover:">
                                <i class="ph ph-paper-plane-tilt mr-2" style="color: var(--complementary-color);"></i><span id="submit-complaint-text">Submit</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// Check URL parameter and open modal if needed
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view');
    if (viewId) {
        const complaints = @json($complaints->items());
        const complaint = complaints.find(c => c.id == viewId);
        if (complaint) {
            // Trigger Alpine.js to open modal
            const event = new CustomEvent('open-complaint-modal', { detail: complaint });
            window.dispatchEvent(event);
            // Clean URL
            window.history.replaceState({}, '', '{{ route("complaints.index") }}');
        }
    }
});

// Listen for modal open event
window.addEventListener('open-complaint-modal', function(e) {
    // This will be handled by Alpine.js
    setTimeout(() => {
        const alpineData = Alpine.$data(document.querySelector('.student-page-container[x-data]'));
        if (alpineData) {
            alpineData.selectedComplaint = e.detail;
            alpineData.showDeleteConfirm = false;
            alpineData.isDeletingComplaint = false;
            alpineData.showViewModal = true;
        }
    }, 100);
});

// Open Create Modal with animation
function openCreateModal() {
    const modal = document.getElementById('create-complaint-modal');
    const modalContent = document.getElementById('create-complaint-modal-content');
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Close Create Modal with animation
function closeCreateModal() {
    const modal = document.getElementById('create-complaint-modal');
    const modalContent = document.getElementById('create-complaint-modal-content');
    
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        // Reset form
        document.getElementById('complaint-form').reset();
    }, 300);
}

// Submit Complaint via AJAX
function submitComplaint(event) {
    event.preventDefault();
    
    const form = event.target;
    const btn = document.getElementById('submit-complaint-btn');
    const text = document.getElementById('submit-complaint-text');
    const formData = new FormData(form);
    
    // Disable button and show loading
    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    text.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Submitting...';
    
    fetch('{{ route("complaints.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) {
            console.error('Server error:', data);
            const fieldErrors = data.errors ? Object.values(data.errors).flat() : [];
            throw new Error(fieldErrors.join(' ') || data.message || 'Server error');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            // Add the saved submission immediately. The sender does not receive
            // the broadcast because the backend uses broadcast()->toOthers().
            const alpineData = Alpine.$data(document.querySelector('.student-page-container[x-data]'));
            if (alpineData && data.complaint) {
                const existingIndex = alpineData.complaints.findIndex(c => c.id === data.complaint.id);
                if (existingIndex === -1) {
                    alpineData.complaints.unshift(data.complaint);
                } else {
                    alpineData.complaints[existingIndex] = data.complaint;
                }
            }

            // Reset form
            form.reset();
            
            // Reset button state
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            text.innerHTML = 'Submit';
            
            // Close modal
            closeCreateModal();
            
            // Show success toast
            showToast(data.message || 'Your submission has been received. Thank you!', 'success');
            
            // WebSocket will handle the real-time update, no need to reload
        } else {
            showToast(data.message || 'Failed to submit', 'error');
            
            // Re-enable button
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            text.innerHTML = 'Submit';
        }
    })
    .catch((error) => {
        console.error('Fetch error:', error);
        showToast(error.message || 'Failed to submit complaint', 'error');
        
        // Re-enable button
        btn.disabled = false;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
        text.innerHTML = 'Submit';
    });
}

async function deleteComplaint(complaint) {
    if (!complaint) {
        return;
    }

    const alpineData = Alpine.$data(document.querySelector('.student-page-container[x-data]'));
    if (alpineData?.isDeletingComplaint) {
        return;
    }
    if (alpineData) {
        alpineData.isDeletingComplaint = true;
    }

    try {
        const response = await fetch('{{ url('/complaints') }}/' + complaint.id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete submission.');
        }

        if (alpineData) {
            alpineData.complaints = alpineData.complaints.filter(item => item.id !== complaint.id);
            alpineData.selectedComplaint = null;
            alpineData.showDeleteConfirm = false;
            alpineData.isDeletingComplaint = false;
            alpineData.showViewModal = false;
        }

        showToast(data.message, 'success');
    } catch (error) {
        console.error('Delete complaint error:', error);
        if (alpineData) {
            alpineData.isDeletingComplaint = false;
        }
        showToast(error.message || 'Failed to delete submission.', 'error');
    }
}

// Auto-open modal if create parameter is present
@if(request('create') === 'true')
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        openCreateModal();
    }, 100);
});
@endif

// Toast notification helper (global function)
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed top-6 right-6 z-50 bg-white border-l-4 ${type === 'success' ? 'border-green-500' : type === 'info' ? 'border-blue-500' : 'border-red-500'} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md`;
    toast.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full ${type === 'success' ? 'bg-green-100' : type === 'info' ? 'bg-blue-100' : 'bg-red-100'} flex items-center justify-center">
                <i class="ph ${type === 'success' ? 'ph-check-circle' : type === 'info' ? 'ph-info' : 'ph-warning-circle'} text-xl ${type === 'success' ? 'text-green-600' : type === 'info' ? 'text-blue-600' : 'text-red-600'}"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">${type === 'success' ? 'Success!' : type === 'info' ? 'Info' : 'Error'}</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
    `;
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// WebSocket Real-Time Updates
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔵 Student Complaints - DOMContentLoaded fired');
    
    @if(auth()->check())
    const currentUserId = {{ auth()->id() }};
    
    console.log('🔵 Student Complaints - Current User ID:', currentUserId);
    console.log('🔵 Student Complaints - Echo object:', window.Echo);
    
    if (!window.Echo) {
        console.error('❌ Echo is not defined! WebSocket will not work.');
        return;
    }
    
    const channelName = 'complaints';
    console.log('🔵 Student Complaints - Subscribing to channel:', channelName);
    
    // Listen for new complaints
    const channel = window.Echo.channel(channelName);
    
    console.log('🔵 Student Complaints - Channel object:', channel);
    
    channel.listen('.complaint-created', (e) => {
            console.log('✅ complaint-created event received!', e);
            console.log('✅ Complaint data:', e.complaint);
            console.log('✅ Complaint user_id:', e.complaint.user_id);
            console.log('✅ Current user_id:', currentUserId);
            
            // Only add if it's the current user's complaint
            if (e.complaint.user_id === currentUserId) {
                console.log('✅ User match! Adding complaint dynamically...');
                
                // Get Alpine component using the main container
                const container = document.querySelector('.student-page-container');
                if (container && container._x_dataStack) {
                    const alpineData = container._x_dataStack[0];
                    
                    // Add new complaint to the beginning of the array
                    alpineData.complaints.unshift(e.complaint);
                    
                    // Show toast notification
                    showToast('New submission added!', 'success');
                } else {
                    console.error('❌ Alpine component not found');
                }
            } else {
                console.log('⚠️ User mismatch - not adding');
            }
        })
        .listen('.complaint-updated', (e) => {
            console.log('✅ complaint-updated event received!', e);
            
            // Only update if it's the current user's complaint
            if (e.complaint.user_id === currentUserId) {
                console.log('✅ User match! Updating complaint dynamically...');
                
                // Get Alpine component using the main container
                const container = document.querySelector('.student-page-container');
                if (container && container._x_dataStack) {
                    const alpineData = container._x_dataStack[0];
                    
                    // Find and update the complaint
                    const index = alpineData.complaints.findIndex(c => c.id === e.complaint.id);
                    if (index !== -1) {
                        alpineData.complaints[index] = e.complaint;
                        
                        // Show toast notification
                        showToast('Submission updated!', 'info');
                    }
                } else {
                    console.error('❌ Alpine component not found');
                }
            }
        });
    
    console.log('🔵 Student Complaints - Listeners attached successfully');
    
    @else
    console.log('⚠️ User not authenticated - WebSocket not initialized');
    @endif
});
</script>
<style>
/* Neumorphism */
.student-complaints-page {
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

html:has(.student-complaints-page),
body:has(.student-complaints-page),
main:has(.student-complaints-page) {
    background: var(--neu-bg) !important;
}

.student-complaints-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

.complaints-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
}

.complaints-hero__copy { max-width: 44rem; }
.complaints-eyebrow {
    margin: 0 0 .45rem;
    color: var(--primary-color, #7c3aed);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    line-height: 1.25;
    text-transform: uppercase;
}

.complaints-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
.complaints-hero__copy > p:last-child {
    max-width: 38rem;
    margin: .8rem 0 0;
    color: var(--neu-muted);
    font-size: clamp(.95rem, 2vw, 1.05rem);
    line-height: 1.55;
}

.complaints-new-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    min-height: 2.25rem;
    padding: .3rem .85rem .3rem .35rem;
    border: 0;
    border-radius: 999px;
    background: var(--primary-color, #0071e3);
    box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
    color: #fff;
    font-size: .8rem;
    font-weight: 600;
    white-space: nowrap;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
}
.complaints-new-btn__icon {
    display: inline-flex;
    width: 1.65rem;
    height: 1.65rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, .18);
    color: #fff;
    font-size: .9rem;
}
.complaints-new-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.04);
    box-shadow: 7px 7px 16px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -7px -7px 16px rgba(255, 255, 255, .9);
}

.complaints-new-btn--desktop { display: none; }
@media (min-width: 640px) {
    .complaints-new-btn--desktop { display: inline-flex; }
    .complaints-new-btn--mobile { display: none; }
}

.complaint-card {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
    transition: transform .32s cubic-bezier(.2, .8, .2, 1), box-shadow .32s cubic-bezier(.2, .8, .2, 1) !important;
    animation: complaint-card-in .65s both cubic-bezier(.2, .8, .2, 1);
    animation-delay: calc(var(--complaint-index, 0) * 45ms);
}
.complaint-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 11px 11px 22px var(--neu-dark-shadow), -11px -11px 22px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
}
.complaint-card h3 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }
.complaint-card p.text-sm { color: var(--neu-muted) !important; }
.complaint-card .border-t { border-color: var(--neu-divider) !important; }
.complaint-card .text-xs { color: var(--neu-muted) !important; }
.complaint-card .h-2 { border-radius: 1.5rem 1.5rem 0 0 !important; }

.complaints-empty {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8) !important;
}
.complaints-empty > i { color: rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important; }
.complaints-empty h3 { color: var(--neu-text) !important; letter-spacing: -.02em !important; }
.complaints-empty p { color: var(--neu-muted) !important; }
.complaints-empty button { border-radius: .9rem !important; box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; transition: transform .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease !important; }
.complaints-empty button:hover { transform: translateY(-2px); filter: brightness(1.04); }

/* Custom Pagination Styles */
.complaints-pagination nav div:last-child a.relative,
.complaints-pagination nav div:last-child span.relative {
    background-color: var(--primary-color) !important;
    border-color: transparent !important;
    color: #ffffff !important;
    box-shadow: 4px 4px 8px rgba(var(--primary-color-rgb, 124, 58, 237), .3), -4px -4px 8px rgba(255, 255, 255, .85) !important;
}

.complaints-pagination nav div:last-child span.relative.z-10 {
    background-color: var(--primary-color) !important;
    filter: brightness(0.9);
    color: #ffffff !important;
    font-weight: bold;
}

.complaints-pagination nav div:last-child a.relative:not(.z-10):hover {
    filter: brightness(1.1);
    background-color: var(--primary-color) !important;
}

.complaints-pagination nav div:last-child a[rel="next"],
.complaints-pagination nav div:last-child span[aria-label="Next &raquo;"],
.complaints-pagination nav div:last-child a[rel="prev"],
.complaints-pagination nav div:last-child span[aria-label="&laquo; Previous"] {
    color: var(--complementary-color) !important;
}

.complaints-pagination nav div:last-child a[rel="next"] svg,
.complaints-pagination nav div:last-child a[rel="next"] i,
.complaints-pagination nav div:last-child a[rel="prev"] svg,
.complaints-pagination nav div:last-child a[rel="prev"] i {
    color: var(--complementary-color) !important;
}

.complaints-modal {
    background: rgba(17, 17, 19, .46) !important;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
}
.complaints-modal__backdrop { background: transparent !important; }
.complaints-modal__surface {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: #ffffff !important;
    box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important;
}
.complaints-modal__surface h2, .complaints-modal__surface h3 { color: var(--neu-text) !important; letter-spacing: -.03em !important; }
.complaints-modal__surface .bg-purple-50 { background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important; border-radius: 1rem !important; }
.complaints-modal__surface .text-gray-400 { color: var(--neu-muted) !important; }
.complaints-modal__surface button { transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease; }
.complaints-modal__surface button:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35); outline-offset: 3px; }
.complaints-modal__surface button.bg-red-600 { border-radius: .8rem !important; box-shadow: 4px 4px 10px rgba(220, 38, 38, .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }

.delete-confirm-popover {
    border: 0 !important;
    border-radius: 1rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 0 18px 44px rgba(17, 17, 19, .22) !important;
}
.delete-confirm-popover button.bg-red-600 { border-radius: .75rem !important; box-shadow: 4px 4px 8px rgba(220, 38, 38, .3), -4px -4px 8px rgba(255, 255, 255, .85) !important; }
.delete-confirm-popover button.border { border: 0 !important; border-radius: .75rem !important; background: var(--neu-bg) !important; color: var(--neu-muted) !important; box-shadow: 3px 3px 6px var(--neu-dark-shadow), -3px -3px 6px var(--neu-light-shadow) !important; }
.delete-confirm-popover button.border:hover { box-shadow: inset 2px 2px 5px var(--neu-dark-shadow), inset -2px -2px 5px var(--neu-light-shadow) !important; }

#create-complaint-modal input, #create-complaint-modal select, #create-complaint-modal textarea {
    border: 0 !important;
    border-radius: .8rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
}
#create-complaint-modal input:focus, #create-complaint-modal select:focus, #create-complaint-modal textarea:focus {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
    outline-offset: 2px;
}
#create-complaint-modal button.border { border: 0 !important; border-radius: .9rem !important; background: var(--neu-bg) !important; color: var(--neu-muted) !important; box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important; }
#create-complaint-modal button.border:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }
#create-complaint-modal button[style*="background-color"] {
    border-radius: .9rem !important;
    box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
}
#create-complaint-modal button[style*="background-color"]:hover { transform: translateY(-2px); filter: brightness(1.04); }

.complaints-new-btn:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35); outline-offset: 3px; }

@keyframes complaint-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }

@media (max-width: 640px) {
    .student-complaints-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
    .student-complaints-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
    .complaints-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
    .complaints-hero__copy { width: 100%; }
    .complaint-card { border-radius: 1.25rem !important; }
    .complaints-modal__surface { border-radius: 1.25rem !important; }
}

@media (prefers-reduced-motion: reduce) {
    .complaint-card { animation: none !important; }
    .complaint-card, .complaints-new-btn { transition-duration: .01ms !important; }
}
</style>
@endsection
