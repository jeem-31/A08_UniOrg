@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.system-requests.index') }}" class="text-[var(--primary-color)] hover:text-[rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)]">
            <i class="ph ph-arrow-left mr-2"></i> Back to Requests
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-[var(--primary-color)] to-[rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)] p-6 text-white">
            <h1 class="text-2xl font-bold">{{ $request->organization_name }}</h1>
            <p class="text-gray-200">System Request Details</p>
        </div>

        <div class="p-6">
            <!-- Status Badge -->
            <div class="mb-6">
                @if($request->status === 'Pending')
                    <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                        <i class="ph ph-clock mr-2"></i> Pending Review
                    </span>
                @elseif($request->status === 'Approved')
                    <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-green-100 text-green-800">
                        <i class="ph ph-check mr-2"></i> Approved
                    </span>
                @else
                    <span class="px-4 py-2 inline-flex text-sm font-semibold rounded-full bg-red-100 text-red-800">
                        <i class="ph ph-x mr-2"></i> Rejected
                    </span>
                @endif
            </div>

            <!-- Organization Information -->
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="ph ph-buildings mr-2 text-[var(--primary-color)]"></i>
                    Organization Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Organization Name</label>
                        <p class="text-gray-900">{{ $request->organization_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Organization Type</label>
                        <p class="text-gray-900">{{ $request->organization_type }}</p>
                    </div>
                </div>
            </div>

            <!-- Requester Information -->
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="ph ph-user mr-2 text-[var(--primary-color)]"></i>
                    Requester Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Full Name</label>
                        <p class="text-gray-900">{{ $request->requester_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Email</label>
                        <p class="text-gray-900">{{ $request->requester_email }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Position</label>
                        <p class="text-gray-900">{{ $request->requester_position ?: 'Not specified' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Request Date</label>
                        <p class="text-gray-900">{{ $request->created_at->format('F d, Y h:i A') }}</p>
                    </div>
                </div>
            </div>

            <!-- Accreditation Form -->
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="ph ph-file mr-2 text-[var(--primary-color)]"></i>
                    Accreditation Form
                </h3>
                @if($request->accreditation_form)
                    <a href="{{ transform_images([$request->accreditation_form])[0] }}" 
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-[var(--primary-color)] text-white rounded-lg hover:opacity-90">
                        <i class="ph ph-download-simple mr-2"></i> Download Accreditation Form
                    </a>
                @else
                    <p class="text-gray-500">No file uploaded</p>
                @endif
            </div>

            <!-- Review Information (if reviewed) -->
            @if($request->status !== 'Pending')
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Review Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Reviewed By</label>
                        <p class="text-gray-900">{{ $request->reviewer->full_name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Reviewed At</label>
                        <p class="text-gray-900">{{ $request->reviewed_at?->format('F d, Y h:i A') ?? 'N/A' }}</p>
                    </div>
                </div>
                @if($request->admin_notes)
                <div class="mt-4">
                    <label class="text-sm font-semibold text-gray-600">Admin Notes</label>
                    <p class="text-gray-900 mt-1">{{ $request->admin_notes }}</p>
                </div>
                @endif
            </div>
            @endif

            <!-- Action Buttons (only for pending requests) -->
            @if($request->status === 'Pending')
            <div class="flex space-x-4">
                <button onclick="approveRequest()" class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    <i class="ph ph-check mr-2"></i> Approve Request
                </button>
                <button onclick="rejectRequest()" class="flex-1 bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition">
                    <i class="ph ph-x mr-2"></i> Reject Request
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Approve Request</h3>
        <p class="text-gray-600 mb-4">This will create the organization and send credentials to the requester.</p>
        <textarea id="approveNotes" placeholder="Admin notes (optional)" 
                  class="w-full px-4 py-2 border rounded-lg mb-4" rows="3"></textarea>
        <div class="flex space-x-4" x-data="{ isSubmitting: false }">
            <button @click="closeApproveModal()" :disabled="isSubmitting" class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                Cancel
            </button>
            <button @click="isSubmitting = true; confirmApprove($el)" :disabled="isSubmitting" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                <i class="ph mr-2" :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                <span x-text="isSubmitting ? 'Approving...' : 'Confirm Approve'"></span>
            </button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Reject Request</h3>
        <p class="text-gray-600 mb-4">Please provide a reason for rejection.</p>
        <textarea id="rejectNotes" placeholder="Reason for rejection (required)" 
                  class="w-full px-4 py-2 border rounded-lg mb-4" rows="3" required></textarea>
        <div class="flex space-x-4" x-data="{ isRejecting: false }">
            <button @click="closeRejectModal()" :disabled="isRejecting" class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50" :class="{ 'opacity-50 cursor-not-allowed': isRejecting }">
                Cancel
            </button>
            <button @click="isRejecting = true; confirmReject($el)" :disabled="isRejecting" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition" :class="{ 'opacity-50 cursor-not-allowed': isRejecting }">
                <i class="ph mr-2" :class="isRejecting ? 'ph-spinner ph-spin' : 'ph-x'"></i>
                <span x-text="isRejecting ? 'Rejecting...' : 'Confirm Reject'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function approveRequest() {
    document.getElementById('approveModal').classList.remove('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
}

function rejectRequest() {
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

async function confirmApprove(btn) {
    const notes = document.getElementById('approveNotes').value;
    
    try {
        const response = await fetch('{{ route("admin.system-requests.approve", $request) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ admin_notes: notes })
        });

        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeApproveModal();
            window.clearAdminPageCache && window.clearAdminPageCache();
            window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
        } else {
            alert(data.message || 'Failed to approve request');
            // Reset button state if using Alpine with $el
            if (btn && btn.__x) {
                btn.__x.$data.isSubmitting = false;
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        if (btn && btn.__x) {
            btn.__x.$data.isSubmitting = false;
        }
    }
}

async function confirmReject(btn) {
    const notes = document.getElementById('rejectNotes').value;
    
    if (!notes.trim()) {
        alert('Please provide a reason for rejection');
        if (btn && btn.__x) {
            btn.__x.$data.isRejecting = false;
        }
        return;
    }
    
    try {
        const response = await fetch('{{ route("admin.system-requests.reject", $request) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ admin_notes: notes })
        });

        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeRejectModal();
            window.clearAdminPageCache && window.clearAdminPageCache();
            window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
        } else {
            alert(data.message || 'Failed to reject request');
            if (btn && btn.__x) {
                btn.__x.$data.isRejecting = false;
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        if (btn && btn.__x) {
            btn.__x.$data.isRejecting = false;
        }
    }
}
</script>
@endsection
