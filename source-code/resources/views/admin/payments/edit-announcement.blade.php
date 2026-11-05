@extends('layouts.admin')

@section('title', 'Edit Payment Announcement')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.payments.announcements') }}" style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-arrow-left mr-2"></i>Back to Announcements
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Edit Payment Announcement</h1>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

<form method="POST" action="{{ route('admin.payments.announcements.update', $announcement) }}" data-admin-inplace-form data-admin-inplace-success-url="{{ route('admin.payments.index', ['tab' => 'announcements']) }}">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="title" class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                <input type="text" name="title" id="title" value="{{ old('title', $announcement->title) }}" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="e.g., 2nd Semester Tour Fee">
            </div>

            <div class="mb-6">
                <label for="description" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" id="description" rows="4" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="Provide details about this payment requirement...">{{ old('description', $announcement->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                    <select name="category" id="category" required
                        style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                        <option value="">Select Category</option>
                        <option value="tour_fee" {{ old('category', $announcement->category) == 'tour_fee' ? 'selected' : '' }}>Tour Fee</option>
                        <option value="membership_fee" {{ old('category', $announcement->category) == 'membership_fee' ? 'selected' : '' }}>Membership Fee</option>
                        <option value="event_fee" {{ old('category', $announcement->category) == 'event_fee' ? 'selected' : '' }}>Event Fee</option>
                        <option value="document_fee" {{ old('category', $announcement->category) == 'document_fee' ? 'selected' : '' }}>Document Fee</option>
                        <option value="medical_fee" {{ old('category', $announcement->category) == 'medical_fee' ? 'selected' : '' }}>Medical Fee</option>
                        <option value="other" {{ old('category', $announcement->category) == 'other' ? 'selected' : '' }}>Others</option>
                    </select>
                </div>

                <div>
                    <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Amount (₱)</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0" value="{{ old('amount', $announcement->amount) }}" required
                        style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                        placeholder="0.00">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="due_date" class="block text-gray-700 text-sm font-bold mb-2">Due Date</label>
                    <input type="date" name="due_date" id="due_date" value="{{ old('due_date', is_string($announcement->due_date) ? $announcement->due_date : $announcement->due_date->format('Y-m-d')) }}" required
                        style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                </div>

                <div>
                    <label for="status" class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                    <select name="status" id="status" required
                        style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                        <option value="active" {{ old('status', $announcement->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $announcement->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="closed" {{ old('status', $announcement->status) == 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Methods Allowed</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_gcash" value="1" {{ old('allow_gcash', $announcement->allow_gcash) ? 'checked' : '' }}
                            style="color: var(--primary-color);" class="mr-2 rounded border-gray-300 focus:ring-[var(--primary-color)]">
                        <span class="text-sm text-gray-700">Allow GCash Payments</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_manual" value="1" {{ old('allow_manual', $announcement->allow_manual) ? 'checked' : '' }}
                            style="color: var(--primary-color);" class="mr-2 rounded border-gray-300 focus:ring-[var(--primary-color)]">
                        <span class="text-sm text-gray-700">Allow Manual (Office) Payments</span>
                    </label>
                </div>
            </div>

            <div class="mb-6">
                <label for="gcash_number" class="block text-gray-700 text-sm font-bold mb-2">GCash Number (Optional)</label>
                <input type="text" name="gcash_number" id="gcash_number" value="{{ old('gcash_number', $announcement->gcash_number) }}"
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="09XX-XXX-XXXX"
                    pattern="[0-9]{11}"
                    inputmode="numeric"
                    maxlength="11"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    title="GCash number must be exactly 11 digits">
            </div>

            <div class="mb-6">
                <label for="payment_instructions" class="block text-gray-700 text-sm font-bold mb-2">Payment Instructions (Optional)</label>
                <textarea name="payment_instructions" id="payment_instructions" rows="3"
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="Special instructions for payment...">{{ old('payment_instructions', $announcement->payment_instructions) }}</textarea>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Images (Optional - Up to 5 images)</label>
                
                @if($announcement->images && count($announcement->images) > 0)
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Current Images:</p>
                    <div class="grid grid-cols-5 gap-4">
                        @foreach($announcement->images as $index => $image)
                        <div class="relative group">
                            <img src="{{ storage_url($image) }}" class="w-full h-24 object-cover rounded-lg border-2 border-gray-300">
                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                <button type="button" onclick="removeImage({{ $index }})" class="text-white text-xs bg-red-500 px-2 py-1 rounded">
                                    <i class="ph ph-trash"></i> Remove
                                </button>
                            </div>
                            <input type="hidden" name="existing_images[]" value="{{ $image }}" id="existing_image_{{ $index }}">
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <div style="border-color: var(--primary-color);" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover: transition">
                    <input type="file" name="images[]" id="images" accept="image/*" multiple class="hidden" onchange="previewImages(event)">
                    <label for="images" class="cursor-pointer">
                        <i class="ph ph-cloud-arrow-up text-4xl text-gray-400 mb-2"></i>
                        <p class="text-gray-600 font-semibold">Click to upload new images</p>
                        <p class="text-sm text-gray-500 mt-1">PNG, JPG up to 5MB each (Max 5 images total)</p>
                    </label>
                </div>
                <div id="imagePreview" class="grid grid-cols-5 gap-4 mt-4"></div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-yellow-900 mb-2"><i class="ph ph-warning mr-2"></i>Warning</h3>
                <p class="text-sm text-yellow-800">
                    Editing this announcement will affect all pending payments linked to it.
                    Students who haven't paid yet will see the updated information.
                </p>
            </div>

            <div class="flex gap-4">
                <button type="submit" style="background-color: var(--primary-color);" class="flex-1  text-white font-bold py-3 px-4 rounded-lg hover:">
                    <i class="ph ph-floppy-disk mr-2"></i>Update Announcement
                </button>
                <a href="{{ route('admin.payments.announcements') }}"
                   class="flex-1 bg-gray-300 text-gray-700 font-bold py-3 px-4 rounded-lg text-center hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function removeImage(index) {
    if (confirm('Remove this image?')) {
        document.getElementById('existing_image_' + index).remove();
        event.target.closest('.relative').remove();
    }
}

function previewImages(event) {
    const files = event.target.files;
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    const existingCount = document.querySelectorAll('input[name="existing_images[]"]').length;
    const totalCount = existingCount + files.length;
    
    if (totalCount > 5) {
        alert('Maximum 5 images allowed (including existing images)');
        event.target.value = '';
        return;
    }
    
    Array.from(files).forEach((file, index) => {
        if (file.size > 5 * 1024 * 1024) {
            alert(`Image ${index + 1} exceeds 5MB`);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'relative group';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-24 object-cover rounded-lg border-2 border-gray-300">
                <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                    <span class="text-white text-xs font-semibold">New ${index + 1}</span>
                </div>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}
</script>
@endsection
