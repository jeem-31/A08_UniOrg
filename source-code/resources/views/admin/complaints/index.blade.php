@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Complaints & Suggestions')

@php
use App\Models\Complaint;
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{
    showViewModal: false,
    selectedComplaint: null,
    isEditingResponse: false,
    filterType: localStorage.getItem('adminComplaintsFilterType') || '',
    filterStatus: localStorage.getItem('adminComplaintsFilterStatus') || '',
    complaints: {{ json_encode($complaints->toArray()) }}
}" x-init="
    $watch('filterType', value => localStorage.setItem('adminComplaintsFilterType', value));
    $watch('filterStatus', value => localStorage.setItem('adminComplaintsFilterStatus', value));
">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between mb-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--primary-color);">Operations / Feedback</p>
            <h1 class="text-2xl font-bold text-gray-900">Complaints & Suggestions</h1>
            <p class="text-sm text-gray-500 mt-1">Review, respond to, and resolve student feedback.</p>
        </div>
        <p class="text-xs text-gray-500"><i class="ph ph-arrows-clockwise mr-1" style="color: var(--primary-color);"></i>Live updates enabled</p>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-4 right-4 z-50 w-[calc(100%-2rem)] max-w-sm bg-white border-l-4 border-green-500 rounded-md shadow-lg px-4 py-3 flex items-start gap-3" style="display: none;">
            <i class="ph ph-check-circle text-green-600 mt-0.5"></i>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900">Success</p>
                <p class="text-sm text-gray-600 break-words">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600" aria-label="Dismiss notification"><i class="ph ph-x"></i></button>
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 border border-gray-200 bg-white rounded-lg divide-x divide-gray-200 mb-5">
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total</p>
                <i class="ph ph-chats text-sm" style="color: var(--primary-color);"></i>
            </div>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ Complaint::count() }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Pending</p>
                <i class="ph ph-clock text-sm text-yellow-600"></i>
            </div>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ Complaint::where('status', 'pending')->count() }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Reviewed</p>
                <i class="ph ph-eye text-sm text-blue-600"></i>
            </div>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ Complaint::where('status', 'reviewed')->count() }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Resolved</p>
                <i class="ph ph-check text-sm text-green-600"></i>
            </div>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ Complaint::where('status', 'resolved')->count() }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <div class="w-full md:max-w-xs">
                <label for="complaint-type" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Type</label>
                <select id="complaint-type" x-model="filterType" style="border-color: var(--primary-color);" class="w-full px-3 py-2 text-sm border rounded-md bg-white focus:outline-none focus:ring-1">
                    <option value="">All types</option>
                    <option value="complaint">Complaint</option>
                    <option value="suggestion">Suggestion</option>
                    <option value="inquiry">Inquiry</option>
                    <option value="feedback">Feedback</option>
                </select>
            </div>
            <div class="w-full md:max-w-xs">
                <label for="complaint-status" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Status</label>
                <select id="complaint-status" x-model="filterStatus" style="border-color: var(--primary-color);" class="w-full px-3 py-2 text-sm border rounded-md bg-white focus:outline-none focus:ring-1">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <div class="flex items-center justify-between gap-3 md:ml-auto">
                <p class="text-xs text-gray-500">
                    <span x-text="complaints.filter(c => (!filterType || c.type === filterType) && (!filterStatus || c.status === filterStatus)).length"></span> matching record(s)
                </p>
                <button x-show="filterType !== '' || filterStatus !== ''" @click="filterType = ''; filterStatus = '';" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50" style="display: none;">
                    <i class="ph ph-x mr-2" style="color: var(--complementary-color);"></i>Clear
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Feedback queue</h2>
                <p class="text-xs text-gray-500 mt-0.5">Select a record to view details or respond.</p>
            </div>
            <span class="hidden sm:inline-flex items-center text-xs text-gray-500"><i class="ph ph-list mr-1.5"></i>Newest first</span>
        </div>

        <template x-if="complaints.length > 0 && complaints.some(c => (!filterType || c.type === filterType) && (!filterStatus || c.status === filterStatus))">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Submitted by</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="complaint in complaints" :key="complaint.id">
                            <tr x-show="(!filterType || filterType === complaint.type) && (!filterStatus || filterStatus === complaint.status)" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 min-w-[230px]">
                                    <p class="text-sm font-semibold text-gray-900 truncate max-w-[320px]" x-text="complaint.subject"></p>
                                    <p class="text-xs text-gray-500 truncate max-w-[360px] mt-0.5" x-text="complaint.message"></p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded" :class="{
                                        'bg-red-50 text-red-700': complaint.type === 'complaint',
                                        'bg-blue-50 text-blue-700': complaint.type === 'suggestion',
                                        'bg-yellow-50 text-yellow-700': complaint.type === 'inquiry',
                                        'bg-green-50 text-green-700': complaint.type === 'feedback'
                                    }" x-text="complaint.type.charAt(0).toUpperCase() + complaint.type.slice(1)"></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-sm" :class="complaint.is_anonymous ? 'text-gray-500' : 'text-gray-900'">
                                        <template x-if="complaint.is_anonymous"><span><i class="ph ph-user-focus mr-1 text-xs"></i>Anonymous</span></template>
                                        <template x-if="!complaint.is_anonymous"><span x-text="complaint.user ? complaint.user.full_name : 'Unknown User'"></span></template>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded" :class="{
                                        'bg-green-50 text-green-700': complaint.status === 'resolved',
                                        'bg-blue-50 text-blue-700': complaint.status === 'reviewed',
                                        'bg-yellow-50 text-yellow-700': complaint.status === 'pending'
                                    }">
                                        <i class="ph mr-1 text-[10px]" :class="{
                                            'ph-check': complaint.status === 'resolved',
                                            'ph-eye': complaint.status === 'reviewed',
                                            'ph-clock': complaint.status === 'pending'
                                        }"></i>
                                        <span x-text="complaint.status.charAt(0).toUpperCase() + complaint.status.slice(1)"></span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500" x-text="new Date(complaint.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"></td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <button @click="selectedComplaint = complaint; showViewModal = true; isEditingResponse = false" class="inline-flex items-center text-sm font-medium hover:underline" style="color: var(--primary-color);">
                                        <i class="ph ph-arrow-square-out mr-1.5 text-xs"></i>View
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <template x-if="complaints.length === 0 || !complaints.some(c => (!filterType || c.type === filterType) && (!filterStatus || c.status === filterStatus))">
            <div class="px-4 py-12 text-center">
                <i class="ph ph-tray text-gray-300 text-3xl mb-3"></i>
                <h3 class="text-sm font-semibold text-gray-900">No complaints found</h3>
                <p class="text-xs text-gray-500 mt-1">No records match the selected filters.</p>
            </div>
        </template>
    </div>

    <div x-show="showViewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-end sm:items-center justify-center min-h-screen p-0 sm:p-4">
            <div @click="showViewModal = false" class="fixed inset-0 bg-gray-900/60 modal-backdrop"></div>
            <div class="relative w-full sm:max-w-2xl max-h-[94vh] sm:max-h-[90vh] overflow-y-auto bg-white rounded-t-xl sm:rounded-lg shadow-xl">
                <div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-white">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider" style="color: var(--primary-color);">Feedback record</p>
                        <h2 class="text-lg font-semibold text-gray-900">Complaint details</h2>
                    </div>
                    <button @click="showViewModal = false" class="h-8 w-8 text-gray-400 hover:text-gray-700 rounded-md hover:bg-gray-100" aria-label="Close details"><i class="ph ph-x"></i></button>
                </div>

                <div x-show="selectedComplaint" class="p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-2 pb-4 border-b border-gray-200">
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded" :class="{
                            'bg-red-50 text-red-700': selectedComplaint.type === 'complaint',
                            'bg-blue-50 text-blue-700': selectedComplaint.type === 'suggestion',
                            'bg-yellow-50 text-yellow-700': selectedComplaint.type === 'inquiry',
                            'bg-green-50 text-green-700': selectedComplaint.type === 'feedback'
                        }" x-text="selectedComplaint.type.charAt(0).toUpperCase() + selectedComplaint.type.slice(1)"></span>
                        <span x-show="selectedComplaint.is_anonymous" class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600"><i class="ph ph-user-focus mr-1.5"></i>Anonymous</span>
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded ml-auto" :class="{
                            'bg-green-50 text-green-700': selectedComplaint.status === 'resolved',
                            'bg-blue-50 text-blue-700': selectedComplaint.status === 'reviewed',
                            'bg-yellow-50 text-yellow-700': selectedComplaint.status === 'pending'
                        }"><i class="ph mr-1.5" :class="{
                            'ph-check': selectedComplaint.status === 'resolved',
                            'ph-eye': selectedComplaint.status === 'reviewed',
                            'ph-clock': selectedComplaint.status === 'pending'
                        }"></i><span x-text="selectedComplaint.status.charAt(0).toUpperCase() + selectedComplaint.status.slice(1)"></span></span>
                    </div>

                    <div class="py-4">
                        <h3 class="text-xl font-semibold text-gray-900 break-words" x-text="selectedComplaint.subject"></h3>
                        <p class="text-xs text-gray-500 mt-1" x-text="'Submitted ' + new Date(selectedComplaint.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })"></p>
                        <div class="mt-4 text-sm text-gray-700 whitespace-pre-wrap break-words" x-text="selectedComplaint.message"></div>
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-1 py-3 border-y border-gray-200 text-xs text-gray-600">
                        <span><strong class="font-semibold text-gray-700">Submitted by:</strong> <span x-show="selectedComplaint.is_anonymous">Anonymous</span><span x-show="!selectedComplaint.is_anonymous" x-text="selectedComplaint.user ? selectedComplaint.user.full_name : 'Unknown'"></span></span>
                        <span x-show="selectedComplaint.user && !selectedComplaint.is_anonymous"><strong class="font-semibold text-gray-700">Email:</strong> <span x-text="selectedComplaint.user.email"></span></span>
                    </div>

                    <div x-show="selectedComplaint && selectedComplaint.admin_response && !isEditingResponse" class="pt-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-900"><i class="ph ph-arrow-bend-up-left mr-1.5" style="color: var(--primary-color);"></i>Admin response</h3>
                            @hasFullAccess('complaints')
                            <button @click="isEditingResponse = true" class="text-xs font-medium hover:underline" style="color: var(--primary-color);"><i class="ph ph-pencil-simple mr-1"></i>Edit</button>
                            @endHasFullAccess
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2.5 text-sm text-gray-700 whitespace-pre-wrap break-words" x-text="selectedComplaint.admin_response"></div>
                    </div>

                    @hasFullAccess('complaints')
                    <div x-show="selectedComplaint && (!selectedComplaint.admin_response || isEditingResponse)" x-data="{ isSubmitting: false }" class="pt-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-900"><i class="ph ph-arrow-bend-up-left mr-1.5" style="color: var(--primary-color);"></i><span x-show="isEditingResponse">Edit response</span><span x-show="!isEditingResponse">Respond to complaint</span></h3>
                            <span class="text-[11px] text-gray-500">Required</span>
                        </div>
                        <textarea x-model="selectedComplaint.admin_response" rows="4" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 text-sm border rounded-md resize-y focus:outline-none focus:ring-1" :placeholder="isEditingResponse ? 'Edit your response here...' : 'Type your response here...'"></textarea>
                        <div class="mt-3">
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Update status</label>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="statusOption in [{value: 'pending', label: 'Pending', icon: 'ph-clock', color: 'yellow'}, {value: 'reviewed', label: 'Reviewed', icon: 'ph-eye', color: 'blue'}, {value: 'resolved', label: 'Resolved', icon: 'ph-check', color: 'green'}]" :key="statusOption.value">
                                    <label class="cursor-pointer">
                                        <input type="radio" x-model="selectedComplaint.status" :value="statusOption.value" class="sr-only peer">
                                        <span class="flex items-center justify-center gap-1.5 px-2 py-2 text-xs font-medium text-gray-600 border border-gray-200 rounded-md peer-checked:border-gray-400 peer-checked:bg-gray-50 hover:bg-gray-50"><i class="ph" :class="statusOption.icon"></i><span x-text="statusOption.label"></span></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 mt-4">
                            <button type="button" x-show="isEditingResponse" @click="isEditingResponse = false" class="px-3 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel edit</button>
                            <button type="button" x-show="!isEditingResponse" @click="showViewModal = false" class="px-3 py-2 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="
                                if (!selectedComplaint.admin_response || selectedComplaint.admin_response.trim() === '') {
                                    showToast('Please enter a response', 'error');
                                    return;
                                }
                                isSubmitting = true;
                                fetch(`/admin/complaints/${selectedComplaint.id}/respond`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        admin_response: selectedComplaint.admin_response,
                                        status: selectedComplaint.status
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const updatedComplaint = data.complaint || selectedComplaint;
                                        const index = complaints.findIndex(c => c.id === selectedComplaint.id);
                                        if (index !== -1) complaints[index] = updatedComplaint;
                                        selectedComplaint = updatedComplaint;
                                        showToast(data.message || 'Response submitted successfully!', 'success');
                                        isEditingResponse = false;
                                    } else {
                                        showToast(data.message || 'Failed to submit response', 'error');
                                    }
                                    isSubmitting = false;
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    showToast('Failed to submit response. Please try again.', 'error');
                                    isSubmitting = false;
                                });
                            " :disabled="isSubmitting" style="background-color: var(--primary-color);" class="px-3 py-2 text-sm text-white rounded-md hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ph ph-paper-plane-tilt mr-1.5" style="color: var(--complementary-color);"></i><span x-show="!isSubmitting"><span x-show="isEditingResponse">Update response</span><span x-show="!isEditingResponse">Submit response</span></span><span x-show="isSubmitting">Submitting...</span>
                            </button>
                        </div>
                    </div>
                    @endHasFullAccess

                    @hasReadOnly('complaints')
                    <div x-show="selectedComplaint && !selectedComplaint.admin_response" class="pt-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2.5 text-xs text-yellow-700"><i class="ph ph-eye mr-1.5"></i><strong>Read-only access.</strong> You can view complaints but cannot respond.</div>
                    </div>
                    @endHasReadOnly
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    @if(auth()->check() && auth()->user()->isAdmin())
    if (!window.Echo) {
        console.error('Admin Complaints: Echo is not defined.');
        return;
    }

    const channel = window.Echo.channel('complaints');
    channel.listen('.complaint-created', (e) => {
        const container = document.querySelector('.max-w-7xl');
        if (container && container._x_dataStack) {
            const alpineData = container._x_dataStack[0];
            alpineData.complaints.unshift(e.complaint);
            showToast('New complaint submitted!', 'info');
        }
    }).listen('.complaint-updated', (e) => {
        const container = document.querySelector('.max-w-7xl');
        if (container && container._x_dataStack) {
            const alpineData = container._x_dataStack[0];
            const index = alpineData.complaints.findIndex(c => c.id === e.complaint.id);
            if (index !== -1) {
                alpineData.complaints[index] = e.complaint;
                if (alpineData.selectedComplaint && alpineData.selectedComplaint.id === e.complaint.id) {
                    alpineData.selectedComplaint = e.complaint;
                }
                showToast('Complaint updated!', 'info');
            }
        }
    });
    @endif
});

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'border-green-500' : type === 'info' ? 'border-blue-500' : 'border-yellow-500';
    const iconColor = type === 'success' ? 'bg-green-100 text-green-600' : type === 'info' ? 'bg-blue-100 text-blue-600' : 'bg-yellow-100 text-yellow-600';
    const icon = type === 'success' ? 'ph-check-circle' : type === 'info' ? 'ph-info' : 'ph-warning-circle';
    toast.className = `fixed top-4 right-4 z-50 w-[calc(100%-2rem)] max-w-sm bg-white border-l-4 ${bgColor} rounded-md shadow-lg px-4 py-3 flex items-start gap-3`;
    toast.innerHTML = `<div class="flex-shrink-0"><div class="h-8 w-8 rounded-full ${iconColor} flex items-center justify-center"><i class="ph ${icon}"></i></div></div><div class="flex-1 min-w-0"><p class="text-sm font-semibold text-gray-900">${type === 'success' ? 'Success!' : type === 'info' ? 'Info' : 'Notice'}</p><p class="text-sm text-gray-600 break-words">${message}</p></div>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

@endsection
