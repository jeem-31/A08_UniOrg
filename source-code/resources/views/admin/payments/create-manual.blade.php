@extends('layouts.admin')

@section('title', 'Process Manual Payment')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.payments.index') }}" style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-arrow-left mr-2"></i>Back to Payments
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Process Manual Payment</h1>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

<form method="POST" action="{{ route('admin.payments.manual.store') }}" data-admin-inplace-form data-admin-inplace-success-url="{{ route('admin.payments.index') }}">
            @csrf

            <div class="mb-6">
                <label for="announcement_id" class="block text-gray-700 text-sm font-bold mb-2">Payment Announcement (Optional)</label>
                <select name="announcement_id" id="announcement_id" 
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                    <option value="">Select Announcement (Optional)</option>
                    @foreach($announcements as $announcement)
                        <option value="{{ $announcement->id }}" {{ old('announcement_id') == $announcement->id ? 'selected' : '' }}>
                            {{ $announcement->title }} - ₱{{ number_format($announcement->amount, 2) }}
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-600 mt-1">Link this payment to an existing announcement</p>
            </div>

            <div class="mb-6">
                <label for="student_id" class="block text-gray-700 text-sm font-bold mb-2">Student ID</label>
                <input type="text" name="student_id" id="student_id" value="{{ old('student_id') }}" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="e.g., 2021-12345">
                <p class="text-sm text-gray-600 mt-1">Enter the student's ID number</p>
            </div>

            <div class="mb-6">
                <label for="category" class="block text-gray-700 text-sm font-bold mb-2">Payment Category</label>
                <select name="category" id="category" required 
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
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
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="0.00">
            </div>

            <div class="mb-6">
                <label for="payment_method" class="block text-gray-700 text-sm font-bold mb-2">Payment Method</label>
                <select name="payment_method" id="payment_method" required 
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                    <option value="">Select Method</option>
                    <option value="manual" {{ old('payment_method') == 'manual' ? 'selected' : '' }}>Manual (Office)</option>
                </select>
                <p class="text-sm text-gray-600 mt-1">This payment will be marked as completed and a receipt will be generated</p>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-gray-700 text-sm font-bold mb-2">Notes (Optional)</label>
                <textarea name="notes" id="notes" rows="3"
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                    placeholder="Additional notes about this payment...">{{ old('notes') }}</textarea>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2"><i class="ph ph-info mr-2"></i>Manual Payment Process</h3>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• This will create a completed payment record for the student</li>
                    <li>• A PDF receipt will be automatically generated</li>
                    <li>• The student will be able to download the receipt from their account</li>
                    <li>• Payment will be marked as verified by you</li>
                </ul>
            </div>

            <div class="flex gap-4">
                <button type="submit" style="background-color: var(--primary-color);" class="flex-1  text-white font-bold py-3 px-4 rounded-lg hover:">
                    <i class="ph ph-money-wavy mr-2"></i>Process Payment & Generate Receipt
                </button>
                <a href="{{ route('admin.payments.index') }}" 
                   class="flex-1 bg-gray-300 text-gray-700 font-bold py-3 px-4 rounded-lg text-center hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('announcement_id').addEventListener('change', function() {
    const announcementSelect = this;
    const categorySelect = document.getElementById('category');
    const amountInput = document.getElementById('amount');
    
    if (announcementSelect.value) {
        // Find the selected announcement
        const selectedOption = announcementSelect.options[announcementSelect.selectedIndex];
        const announcementText = selectedOption.text;
        
        // Extract category and amount from the selected option text
        // Format: "Title - ₱Amount"
        if (announcementText.includes('₱')) {
            const amountMatch = announcementText.match(/₱([\d,]+\.?\d*)/);
            if (amountMatch) {
                amountInput.value = amountMatch[1].replace(',', '');
            }
        }
        
        // Set category based on announcement (you might need to adjust this logic)
        // For now, we'll keep the manual selection
    }
});
</script>
@endsection
