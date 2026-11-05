@extends('layouts.app')

@section('title', 'New Payment')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('payments.index') }}" style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-arrow-left mr-2"></i>Back to Payments
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-black tracking-tight text-gray-900 mb-6">Submit Payment</h1>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('payments.store') }}">
            @csrf

            <div class="mb-6">
                <label for="announcement_id" class="block text-gray-700 text-sm font-bold mb-2">Payment Announcement (Optional)</label>
                <select name="announcement_id" id="announcement_id" 
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                    <option value="">Select Announcement (Optional)</option>
                    @foreach($announcements as $announcement)
                        <option value="{{ $announcement->id }}" 
                                {{ old('announcement_id') == $announcement->id || request('announcement') == $announcement->id ? 'selected' : '' }}>
                            {{ $announcement->title }} - ₱{{ number_format($announcement->amount, 2) }}
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-600 mt-1">Select if this payment is for a specific announcement</p>
            </div>

            <div class="mb-6">
                <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Payment Category</label>
                <select name="category" id="category" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                    <option value="">Select Category</option>
                    <option value="tour_fee" {{ old('category') == 'tour_fee' ? 'selected' : '' }}>Tour Fee</option>
                    <option value="membership_fee" {{ old('category') == 'membership_fee' ? 'selected' : '' }}>Membership Fee</option>
                    <option value="event_fee" {{ old('category') == 'event_fee' ? 'selected' : '' }}>Event Fee</option>
                    <option value="document_fee" {{ old('category') == 'document_fee' ? 'selected' : '' }}>Document Fee</option>
                    <option value="medical_fee" {{ old('category') == 'medical_fee' ? 'selected' : '' }}>Medical Fee</option>
                </select>
            </div>

            <div class="mb-6">
                <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Amount (₱)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" value="{{ old('amount') }}" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
            </div>

            <div class="mb-6">
                <label for="payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                <select name="payment_method" id="payment_method" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                    <option value="">Select Method</option>
                    <option value="gcash" {{ old('payment_method') == 'gcash' ? 'selected' : '' }}>GCash (Online)</option>
                    <option value="manual" {{ old('payment_method') == 'manual' ? 'selected' : '' }}>Manual (Office)</option>
                </select>
            </div>

            <div class="mb-6" id="reference_field" style="display: none;">
                <label for="reference_number" class="block text-gray-700 text-sm font-bold mb-2">Reference Number</label>
                <input type="text" name="reference_number" id="reference_number" value="{{ old('reference_number') }}"
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                <p class="text-sm text-gray-600 mt-1">Enter your GCash reference number after payment</p>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-gray-700 text-sm font-bold mb-2">Notes (Optional)</label>
                <textarea name="notes" id="notes" rows="3" style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">{{ old('notes') }}</textarea>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2"><i class="ph ph-info mr-2"></i>Payment Instructions</h3>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li><strong>GCash:</strong> Send payment to 09XX-XXX-XXXX (SOCCS Treasurer), then enter reference number</li>
                    <li><strong>Manual:</strong> Visit SOCCS office during office hours (Mon-Fri, 8AM-5PM)</li>
                    <li>All payments require verification from SOCCS officers</li>
                    <li>You will receive a notification once your payment is verified</li>
                    <li>A downloadable PDF receipt will be provided upon verification</li>
                </ul>
            </div>

            <button type="submit" style="background-color: var(--primary-color);" class="w-full  text-white font-bold py-3 px-4 rounded-lg hover:">
                Submit Payment
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('payment_method').addEventListener('change', function() {
    const referenceField = document.getElementById('reference_field');
    if (this.value === 'gcash') {
        referenceField.style.display = 'block';
    } else {
        referenceField.style.display = 'none';
    }
});

document.getElementById('announcement_id').addEventListener('change', function() {
    const announcementSelect = this;
    const categorySelect = document.getElementById('category');
    const amountInput = document.getElementById('amount');
    
    if (announcementSelect.value) {
        // Find the selected announcement and auto-fill details
        const selectedOption = announcementSelect.options[announcementSelect.selectedIndex];
        const announcementText = selectedOption.text;
        
        // Extract amount from the selected option text
        if (announcementText.includes('₱')) {
            const amountMatch = announcementText.match(/₱([\d,]+\.?\d*)/);
            if (amountMatch) {
                amountInput.value = amountMatch[1].replace(',', '');
            }
        }
        
        // You could also auto-set category based on announcement if needed
        // For now, we'll let the user confirm the selection
    }
});

// Auto-fill if announcement is passed in URL
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const announcementId = urlParams.get('announcement');
    
    if (announcementId) {
        const announcementSelect = document.getElementById('announcement_id');
        announcementSelect.value = announcementId;
        announcementSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endsection
