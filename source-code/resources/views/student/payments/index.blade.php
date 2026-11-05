@extends('layouts.app')

@section('title', 'My Payments')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="student-payments-page min-h-screen">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
        <header class="payments-hero">
            <div class="payments-hero__copy">
                <p class="payments-eyebrow">Student finance</p>
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">Payments</h1>
                    <button type="button" onclick="openHistoryModal()" class="payment-history-trigger payment-history-trigger--mobile">
                        <span class="payment-button-icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span>
                        <span>Payment history</span>
                    </button>
                </div>
            </div>
            <div class="payments-hero__actions">
                <button type="button" onclick="openHistoryModal()" class="payment-history-trigger payment-history-trigger--desktop">
                    <span class="payment-button-icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span>
                    <span>Payment history</span>
                </button>
            </div>
        </header>

        <!-- Payment Cards Section -->
        @if($announcements->count() > 0)
        <section class="payments-section" aria-label="Current dues">
            <div class="payments-section__heading">
                <div>
                    <p class="payments-eyebrow">Current dues</p>
                    <p class="payments-section__description">Choose a payment below to view the GCash details and submit your receipt.</p>
                </div>
            </div>
            <div class="payments-grid grid gap-4 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($announcements as $announcement)
                    @php
                        // Check if student has already paid for this announcement
                        // Get the LATEST payment record for this announcement
                        $existingPayment = auth()->user()->studentPayments()
                            ->where('payment_id', $announcement->id)
                            ->latest()
                            ->first();

                        $isPaid = $existingPayment
                            && $existingPayment->status === 'completed'
                            && $existingPayment->verification_status === 'approved';
                        $isPending = $existingPayment && $existingPayment->verification_status === 'pending';
                        $isRejected = $existingPayment && $existingPayment->verification_status === 'rejected';
                        $isProtectedMembershipFee = $announcement->isProtectedMembershipFee();
                        $isPaymentSetupIncomplete = $isProtectedMembershipFee && trim((string) $announcement->gcash_number) === '';
                        $categoryLabel = ucfirst(str_replace('_', ' ', $announcement->category ?? 'Payment'));
                        $dueDate = $announcement->due_date ? \Carbon\Carbon::parse($announcement->due_date) : null;
                    @endphp
                    <article class="payment-card bg-white rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden group" style="--card-index: {{ $loop->index }};">
                        <!-- Card Header with primary color gradient -->
                        <div class="payment-card__header p-3 sm:p-4" style="background: linear-gradient(to right, var(--primary-color), rgba(var(--primary-color-rgb, 130, 56, 179), 0.8));">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                <h3 class="payment-card__title text-base sm:text-lg font-black tracking-tight text-white break-words">{{ $announcement->title }}</h3>
                                <span style="color: var(--primary-color);" class="payment-card__category px-2 sm:px-3 py-1 text-xs font-semibold rounded-full bg-white self-start whitespace-nowrap">
                                    {{ $categoryLabel }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="payment-card__body p-4 sm:p-6">
                            <!-- Amount and Due Date with gradient -->
                            <div class="payment-card__amount rounded-lg p-3 sm:p-4 mb-4 sm:mb-6" style="background: linear-gradient(to right, rgba(var(--primary-color-rgb, 130, 56, 179), 0.1), rgba(var(--complementary-color-rgb, 218, 165, 32), 0.1));">
                                <div class="flex justify-between items-center mb-2 sm:mb-3 gap-2">
                                    <span class="text-gray-600 font-semibold text-sm sm:text-base">Amount:</span>
                                    <span style="color: var(--primary-color);" class="text-xl sm:text-2xl md:text-3xl font-bold">₱{{ number_format($announcement->amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-gray-600 font-semibold text-sm sm:text-base">Due Date:</span>
                                    <span class="text-xs sm:text-sm font-semibold text-gray-700">{{ $dueDate ? $dueDate->format('M d, Y') : 'No due date' }}</span>
                                </div>
                            </div>

                            <!-- Pay Now / Status / Resubmit Button -->
                            @if($isPaymentSetupIncomplete)
                                <button type="button" disabled class="w-full px-4 py-3 bg-gray-400 text-white text-sm font-bold rounded-lg cursor-not-allowed shadow-md">
                                    <i class="ph ph-gear-six mr-2"></i>Payment Details Being Set Up
                                </button>
                                <p class="mt-2 text-xs text-gray-500 text-center">Contact your Mother Organization officer for Membership Fee payment details.</p>
                            @elseif($isPaid)
                                <button type="button" disabled class="w-full px-4 py-3 bg-green-500 text-white text-sm font-bold rounded-lg cursor-not-allowed shadow-md">
                                    <i class="ph ph-check-circle mr-2"></i>Paid
                                </button>
                            @elseif($isPending)
                                <button type="button" disabled class="w-full px-4 py-3 bg-yellow-400 text-white text-sm font-bold rounded-lg cursor-not-allowed shadow-md">
                                    <i class="ph ph-clock mr-2"></i>Verification Pending
                                </button>
                            @elseif($isRejected)
                                <div class="flex items-center gap-2">
                                    <button type="button" class="flex-1 px-4 py-3 bg-gradient-to-r from-orange-500 to-orange-600 text-white text-sm font-bold rounded-lg hover:shadow-lg transition-all duration-300 resubmit-btn"
                                            data-id="{{ $announcement->id }}"
                                            data-amount="{{ $announcement->amount }}"
                                            data-title="{{ $announcement->title }}"
                                            data-receipt="{{ $existingPayment->gcash_receipt_path ? storage_url($existingPayment->gcash_receipt_path) : '' }}"
                                            data-reference="{{ $existingPayment->reference_number ?? '' }}">
                                        <i class="ph ph-upload-simple mr-2"></i>Resubmit
                                    </button>
                                    <button type="button" onclick="openRejectionModal('{{ addslashes($existingPayment->rejection_reason ?? 'Please upload your receipt properly.') }}')" class="px-3 py-3 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition flex-shrink-0" title="View rejection reason">
                                        <i class="ph ph-info"></i>
                                    </button>
                                </div>
                            @else
                                <button type="button" class="w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-bold rounded-lg hover:shadow-lg transition-all duration-300 gcash-pay-btn"
                                        data-id="{{ $announcement->id }}"
                                        data-amount="{{ $announcement->amount }}"
                                        data-title="{{ $announcement->title }}"
                                        data-action="{{ route('payments.gcash', $announcement) }}"
                                        data-gcash="{{ $announcement->gcash_number }}">
                                    <i class="ph ph-device-mobile mr-2"></i>Pay Now
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        @else
            <section class="payments-empty" aria-labelledby="no-payments-heading">
                <span><i class="ph ph-check-circle" aria-hidden="true"></i></span>
                <p class="payments-eyebrow">All caught up</p>
                <h2 id="no-payments-heading">No active payments.</h2>
                <p>There are no payment announcements for your organization right now. Check back later for new dues.</p>
            </section>
        @endif

    <!-- Payment History Modal -->
<div id="historyModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex justify-center z-50 hidden overflow-y-auto p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="payment-history-title">
    <div class="payment-modal__surface bg-white rounded-xl shadow-xl p-4 sm:p-6 md:p-8 max-w-6xl w-full my-auto max-h-[95vh] sm:max-h-[90vh] overflow-y-auto">
        <div class="payment-modal__header flex justify-between items-center mb-4 sm:mb-6">
            <div><p class="payments-eyebrow">Your records</p><h2 id="payment-history-title" class="text-xl sm:text-2xl font-black tracking-tight text-gray-900">Payment history</h2></div>
            <button type="button" onclick="closeHistoryModal()" class="payment-modal__close text-gray-400 hover:text-gray-600" aria-label="Close payment history">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <!-- Mobile: stacked payment cards (no horizontal scrolling) -->
        <div class="payment-history-cards md:hidden space-y-3">
            @forelse($payments as $payment)
                <div class="payment-history-card">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="payment-history-badge
                            {{ $payment->category === 'membership_fee' ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ $payment->category === 'event_fee' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $payment->category === 'tour_fee' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $payment->category === 'document_fee' ? 'bg-indigo-100 text-indigo-800' : '' }}
                            {{ $payment->category === 'medical_fee' ? 'bg-pink-100 text-pink-800' : '' }}
                            {{ $payment->category === 'project_contribution' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $payment->category === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ ucfirst(str_replace('_', ' ', $payment->category)) }}
                        </span>
                        <span class="payment-history-badge
                            {{ $payment->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $payment->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                            <i class="ph {{ $payment->status === 'completed' ? 'ph-check' : ($payment->status === 'pending' ? 'ph-clock' : 'ph-x') }} mr-1"></i>{{ ucfirst($payment->status) }}
                        </span>
                    </div>
                    <p class="payment-history-label">Transaction ID</p>
                    <p class="payment-history-value font-mono mb-3">{{ $payment->transaction_id }}</p>
                    <div class="flex items-end justify-between gap-3 pt-3 border-t">
                        <div class="min-w-0">
                            <p class="payment-history-meta">{{ $payment->created_at->format('M d, Y') }} &middot; {{ strtoupper($payment->payment_method) }}</p>
                            <p class="payment-history-amount">&#8369;{{ number_format($payment->amount, 2) }}</p>
                        </div>
                        <div class="payment-history-actions flex-shrink-0">
                            @if($payment->status === 'completed')
                                <button type="button" onclick="showReceiptModal({{ $payment->id }})" class="payment-history-action">
                                    <i class="ph ph-eye"></i> View
                                </button>
                                @if($payment->receipt_path || $payment->gcash_receipt_path)
                                    <a href="{{ route('payments.download-receipt', $payment) }}" class="payment-history-action payment-history-action--solid">
                                        <i class="ph ph-download-simple"></i> Receipt
                                    </a>
                                @endif
                            @else
                                <span class="text-[10px] text-gray-400 text-right">Receipt only when paid</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="payment-history-empty text-center py-10">
                    <i class="ph ph-receipt text-gray-300 text-5xl mb-3"></i>
                    <p class="text-gray-500">No payment history yet</p>
                </div>
            @endforelse
        </div>

        <!-- Desktop: compact table that fits without horizontal scrolling -->
        <div class="payment-history-table hidden md:block overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Transaction ID</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Amount</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Method</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ $payment->transaction_id }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold
                                    {{ $payment->category === 'membership_fee' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $payment->category === 'event_fee' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $payment->category === 'tour_fee' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $payment->category === 'document_fee' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                    {{ $payment->category === 'medical_fee' ? 'bg-pink-100 text-pink-800' : '' }}
                                    {{ $payment->category === 'project_contribution' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $payment->category === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $payment->category)) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">&#8369;{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold
                                    {{ $payment->payment_method === 'gcash' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $payment->payment_method === 'manual' ? 'bg-purple-100 text-purple-800' : '' }}">
                                    {{ strtoupper($payment->payment_method) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full font-semibold
                                    {{ $payment->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $payment->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $payment->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">{{ $payment->created_at->format('M d, Y') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm space-x-2">
                                @if($payment->status === 'completed')
                                <button onclick="showReceiptModal({{ $payment->id }})" style="color: var(--primary-color);" class="hover:text-purple-900 font-medium">
                                    <i class="ph ph-eye"></i> View
                                </button>
                                @if($payment->receipt_path || $payment->gcash_receipt_path)
                                    <a href="{{ route('payments.download-receipt', $payment) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                        <i class="ph ph-download-simple"></i> Receipt
                                    </a>
                                @endif
                                @else
                                <span class="text-xs text-gray-400">Receipt only when paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="ph ph-receipt text-gray-300 text-6xl mb-4"></i>
                                <p class="text-lg">No payment history yet</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 pagination-container">
            {{ $payments->links() }}
        </div>
    </div>
</div>

<!-- Receipt View Modal -->
<div id="receiptViewModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] hidden overflow-y-auto p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="payment-receipt-title">
    <style>
        /* Override receipt heights for modal view only */
        #receiptViewModal .info-section {
            height: 210px !important;
        }
    </style>
    <div class="payment-modal__surface bg-white rounded-xl shadow-2xl max-w-4xl w-full my-4 sm:my-8 max-h-[90vh] flex flex-col">
        <div class="payment-modal__header flex justify-between items-center p-6 border-b flex-shrink-0">
            <div><p class="payments-eyebrow">Transaction details</p><h3 id="payment-receipt-title" class="text-xl font-black tracking-tight text-gray-900">Payment receipt</h3></div>
            <button type="button" onclick="closeReceiptViewModal()" class="payment-modal__close text-gray-400 hover:text-gray-600" aria-label="Close payment receipt">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <div id="receiptViewContent" class="overflow-x-auto overflow-y-auto flex-1 p-6">
            <!-- Receipt content will be loaded here -->
            <div class="text-center py-8">
                <i style="color: var(--primary-color);" class="ph ph-spinner ph-spin text-4xl  mb-4"></i>
                <p class="text-gray-600">Loading receipt...</p>
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
     class="fixed top-4 sm:top-6 right-4 sm:right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-4 sm:px-6 py-3 sm:py-4 flex items-center space-x-3 max-w-[calc(100vw-2rem)] sm:max-w-md"
     style="display: none;">
    <div class="flex-shrink-0">
        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
            <i class="ph ph-check-circle text-xl text-green-600"></i>
        </div>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Success!</p>
        <p class="text-xs sm:text-sm text-gray-600 break-words">{{ session('success') }}</p>
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
     class="fixed top-4 sm:top-6 right-4 sm:right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-4 sm:px-6 py-3 sm:py-4 flex items-center space-x-3 max-w-[calc(100vw-2rem)] sm:max-w-md"
     style="display: none;">
    <div class="flex-shrink-0">
        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
            <i class="ph ph-warning-circle text-xl text-red-600"></i>
        </div>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Error!</p>
        <p class="text-xs sm:text-sm text-gray-600 break-words">{{ session('error') }}</p>
    </div>
    <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
        <i class="ph ph-x text-lg"></i>
    </button>
</div>
@endif

</div>
<!-- GCash Payment Modal -->
<div id="gcashModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex items-start sm:items-center justify-center z-50 hidden p-3 sm:p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="gcash-modal-title">
    <div class="payment-modal__surface bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden my-4 sm:my-0">
        <!-- Modal Header -->
        <div class="payment-modal__brand bg-gradient-to-r from-blue-500 to-blue-600 p-4 sm:p-6 text-white">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2 sm:gap-3">
                    <i class="ph ph-device-mobile text-xl sm:text-2xl"></i>
                    <h2 id="gcash-modal-title" class="text-lg sm:text-2xl font-black tracking-tight">Pay with GCash</h2>
                </div>
                <button type="button" onclick="closeGcashModal()" class="payment-modal__close text-blue-100 hover:text-white transition" aria-label="Close GCash payment">
                    <i class="ph ph-x text-xl sm:text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-5 sm:p-8">
            <!-- Payment Form -->
            <form id="gcashPaymentForm" method="POST" class="gcash-form">
                <input type="hidden" name="_token" id="csrf_token" value="{{ csrf_token() }}">
                <input type="hidden" name="amount" id="gcash_amount">
                <input type="hidden" name="title" id="gcash_title">
                <input type="hidden" name="announcement_id" id="gcash_announcement_id">
                <input type="hidden" name="gcash_number" id="gcash_number">

                <!-- Amount Display -->
                <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4 mb-6">
                    <p class="text-gray-600 text-sm font-semibold mb-1">Payment Amount</p>
                    <p class="text-3xl font-bold text-blue-600" id="displayAmount">₱0.00</p>
                </div>

                <!-- GCash Number Display -->
                <div class="bg-white border-2 border-blue-500 rounded-lg p-4 mb-6">
                    <p class="text-gray-700 text-sm font-semibold mb-2 flex items-center">
                        <i class="ph ph-device-mobile text-blue-600 mr-2"></i>Send payment to this GCash number:
                    </p>
                    <p class="text-2xl font-bold text-gray-900 text-center py-2" id="displayGcashNumber">09XX-XXX-XXXX</p>
                    <button type="button" onclick="event.preventDefault(); openQRModal();" style="color: var(--primary-color);" class="w-full mt-2 text-sm  hover: font-semibold py-2 transition underline">
                        <i class="ph ph-qr-code mr-1"></i>View QR Code
                    </button>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                    <p class="text-xs text-blue-800 mb-2"><strong>Payment Instructions:</strong></p>
                    <ol class="text-xs text-blue-800 list-decimal list-inside space-y-1">
                        <li>Send the exact amount to the GCash number above</li>
                        <li>Take a screenshot of your payment confirmation</li>
                        <li>Click "Proceed to Payment" below</li>
                        <li>Your payment will be verified by SOCCS officers</li>
                    </ol>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col gap-3">
                    <button type="button" id="gcashSubmitBtn" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition-all duration-300 flex items-center justify-center gap-2">
                        <i class="ph ph-device-mobile"></i>
                        <span>Send GCash Payment Receipt</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Success Modal -->
<div id="paymentSuccessModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="payment-success-title">
    <div class="payment-modal__surface bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden">
        <!-- Success Content -->
        <div class="p-8 text-center">
            <div class="mb-6">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-4">
                    <i class="ph ph-check-circle text-5xl text-green-600"></i>
                </div>
                <h2 id="payment-success-title" class="text-2xl font-black tracking-tight text-gray-900 mb-2">Payment Successful!</h2>
                <p class="text-gray-600">Your payment has been processed successfully.</p>
            </div>

            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded text-left">
                <p class="text-sm text-green-800 mb-2"><strong>What's Next?</strong></p>
                <p class="text-sm text-green-800">Check your payment history for updates</p>
            </div>

            <button onclick="completePaymentAndClose()" id="doneBtn" class="w-full text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition" style="background: linear-gradient(to right, var(--primary-color), rgba(var(--primary-color-rgb, 130, 56, 179), 0.8));">
                <i style="color: var(--complementary-color);" class="ph ph-check mr-2" id="doneIcon"></i><span id="doneBtnText">Done</span>
            </button>
        </div>
    </div>
</div>

<!-- Receipt Upload Modal -->
<div id="receiptModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex items-start sm:items-center justify-center z-50 hidden p-3 sm:p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="receipt-upload-title">
    <div class="payment-modal__surface bg-white rounded-xl shadow-2xl w-full max-w-2xl my-4 sm:my-0 overflow-hidden">
        <!-- Modal Header -->
        <div class="payment-modal__brand bg-gradient-to-r from-blue-500 to-blue-600 p-4 sm:p-6 text-white">
            <div class="receipt-modal__title-row flex justify-between items-center">
                <div class="receipt-modal__title-group flex items-center min-w-0">
                    <span class="receipt-modal__title-icon"><i class="ph ph-receipt" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <p class="receipt-modal__context">Payment verification</p>
                        <h2 id="receipt-upload-title" class="text-lg sm:text-2xl font-black tracking-tight">Upload GCash Receipt</h2>
                    </div>
                </div>
                <button type="button" onclick="closeReceiptModal()" class="payment-modal__close text-blue-100 hover:text-white transition" aria-label="Close receipt upload">
                    <i class="ph ph-x text-xl sm:text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="receipt-modal__body p-4 sm:p-6">
            <form id="receiptUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="_token" id="receipt_csrf_token" value="{{ csrf_token() }}">
                <input type="hidden" name="payment_id" id="receipt_payment_id">
                <input type="hidden" name="receipt" id="receipt_hidden">

                <!-- Two Column Layout: Image Left, Fields Right -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-6 items-center">
                    <!-- LEFT COLUMN: Image Upload -->
                    <div class="flex flex-col items-center justify-center">
                        <label class="block text-gray-700 text-sm font-bold mb-3 w-full text-center">
                            GCash Receipt Screenshot <span class="text-red-500">*</span>
                        </label>
                        <div class="relative w-full mx-auto" style="max-width: 200px;">
                            <div class="relative w-full aspect-[9/16] border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 hover:border-blue-500 transition-all cursor-pointer group">
                                <input type="file" id="receipt_file" accept="image/*" required class="hidden" onchange="previewReceiptImage(event)">
                                <label for="receipt_file" class="absolute inset-0 flex flex-col items-center justify-center cursor-pointer">
                                    <!-- Placeholder (shown when no image) -->
                                    <div id="uploadPlaceholder" class="flex flex-col items-center justify-center text-gray-400 p-3">
                                        <i class="ph ph-cloud-arrow-up text-4xl mb-2 text-blue-400"></i>
                                        <p class="text-xs font-semibold text-gray-600 text-center">Upload Image</p>
                                        <p class="text-xs text-gray-500 mt-1 text-center">Click</p>
                                    </div>
                                    <!-- Preview Image -->
                                    <img id="previewImage" src="" alt="Receipt preview" class="hidden absolute inset-0 w-full h-full object-contain p-2">
                                    <!-- Hover Overlay -->
                                    <div id="changeImageOverlay" class="hidden absolute inset-0 bg-black bg-opacity-60 items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <div class="text-white text-center">
                                            <i class="ph ph-camera text-3xl mb-1"></i>
                                            <p class="text-xs font-semibold">Change</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Fields and Info -->
                    <div class="space-y-3 flex flex-col justify-center">
                        <!-- Reference Number Field -->
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                Reference Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="reference_number" id="reference_number" required
                                   class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                   placeholder="XXXX XXX XXXXXX"
                                   maxlength="16"
                                   oninput="formatReferenceNumber(this)">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="ph ph-info mr-1"></i>Ex: 1234 567 890123
                            </p>
                        </div>

                        <!-- Instructions Box -->
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-2.5 rounded-lg">
                            <p class="text-xs text-blue-900 font-bold mb-1.5"><i class="ph ph-warning mr-1"></i>Instructions:</p>
                            <ul class="text-xs text-blue-800 space-y-0.5">
                                <li class="flex items-start">
                                    <i class="ph ph-check text-blue-600 mr-1.5 mt-0.5 flex-shrink-0"></i>
                                    <span>Double-check reference</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="ph ph-check text-blue-600 mr-1.5 mt-0.5 flex-shrink-0"></i>
                                    <span>Clear screenshot</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="ph ph-check text-blue-600 mr-1.5 mt-0.5 flex-shrink-0"></i>
                                    <span>Details visible</span>
                                </li>
                            </ul>
                        </div>

                        <!-- File Requirements Box -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-2.5">
                            <p class="text-xs text-gray-700 font-bold mb-1.5">
                                <i class="ph ph-file-image text-blue-500 mr-1"></i>Requirements
                            </p>
                            <div class="text-xs text-gray-700 space-y-0.5">
                                <p><i class="ph ph-image text-blue-500 mr-1.5"></i>JPG, PNG</p>
                                <p><i class="ph ph-database text-blue-500 mr-1.5"></i>Max 5MB</p>
                                <p><i class="ph ph-device-mobile text-blue-500 mr-1.5"></i>Portrait</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeReceiptModal()" class="flex-1 bg-gray-300 text-gray-700 font-bold py-3 px-4 rounded-lg hover:bg-gray-400 transition">
                        <i class="ph ph-arrow-left mr-2"></i>Back
                    </button>
                    <button type="submit" id="receiptSubmitBtn" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:shadow-lg transition">
                        <i class="ph ph-upload-simple mr-2"></i>Submit Receipt
                    </button>
                </div>
            </form>

            <!-- Processing -->
            <div id="receiptProcessing" class="hidden text-center py-12">
                <div class="mb-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <i class="ph ph-spinner text-blue-600 text-4xl animate-spin"></i>
                    </div>
                </div>
                <h3 class="text-xl font-black tracking-tight text-gray-900 mb-2">Uploading Receipt...</h3>
                <p class="text-gray-600">Please wait while we process your receipt.</p>
            </div>
        </div>
    </div>
</div>

<script>
var currentPaymentData = {};

function openGcashModal(amount, title, announcementId, gcashNumber) {
    const modal = document.getElementById('gcashModal');

    currentPaymentData = {
        amount: amount,
        title: title,
        announcementId: announcementId,
        gcashNumber: gcashNumber
    };

    modal.classList.remove('hidden');
    document.getElementById('gcash_amount').value = amount;
    document.getElementById('gcash_title').value = title;
    document.getElementById('gcash_announcement_id').value = announcementId;
    document.getElementById('gcash_number').value = gcashNumber || '09XX-XXX-XXXX';
    document.getElementById('displayAmount').textContent = '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('displayGcashNumber').textContent = gcashNumber || '09XX-XXX-XXXX';
}

function closeGcashModal() {
    document.getElementById('gcashModal').classList.add('hidden');
    document.getElementById('gcashPaymentForm').reset();
}

function openReceiptModal(paymentId) {
    document.getElementById('receiptModal').classList.remove('hidden');
    document.getElementById('receipt_payment_id').value = paymentId;
}

function openReceiptModalWithData() {
    document.getElementById('receiptModal').classList.remove('hidden');

    // Show the currently submitted receipt (if any) so the student can see
    // what they are replacing, and prefill the reference number.
    const existingUrl = currentPaymentData && currentPaymentData.existing_receipt_url
        ? currentPaymentData.existing_receipt_url
        : '';
    if (existingUrl) {
        const previewImage = document.getElementById('previewImage');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const changeImageOverlay = document.getElementById('changeImageOverlay');
        if (previewImage && uploadPlaceholder) {
            previewImage.src = existingUrl;
            previewImage.classList.remove('hidden');
            uploadPlaceholder.classList.add('hidden');
        }
        if (changeImageOverlay) {
            changeImageOverlay.classList.remove('hidden');
            changeImageOverlay.classList.add('flex');
        }
    }

    const referenceInput = document.getElementById('reference_number');
    if (referenceInput && currentPaymentData && currentPaymentData.existing_reference) {
        referenceInput.value = currentPaymentData.existing_reference;
    }
}

function closeReceiptModal() {
    document.getElementById('receiptModal').classList.add('hidden');
    document.getElementById('receiptUploadForm').reset();
    document.getElementById('uploadPlaceholder').classList.remove('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('changeImageOverlay').classList.add('hidden');
}

function previewReceiptImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('uploadPlaceholder').classList.add('hidden');
            document.getElementById('previewImage').src = e.target.result;
            document.getElementById('previewImage').classList.remove('hidden');
            document.getElementById('changeImageOverlay').classList.remove('hidden');
            document.getElementById('changeImageOverlay').classList.add('flex');
        }
        reader.readAsDataURL(file);
    }
}

function formatReferenceNumber(input) {
    // Remove all non-digit characters
    let value = input.value.replace(/\D/g, '');
    
    // Limit to 13 digits
    value = value.substring(0, 13);
    
    // Format as XXXX XXX XXXXXX
    let formatted = '';
    if (value.length > 0) {
        formatted = value.substring(0, 4);
    }
    if (value.length > 4) {
        formatted += ' ' + value.substring(4, 7);
    }
    if (value.length > 7) {
        formatted += ' ' + value.substring(7, 13);
    }
    
    input.value = formatted;
}

function openHistoryModal() {
    document.getElementById('historyModal').classList.remove('hidden');
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
}

function showReceiptModal(paymentId) {
    // Hide history modal
    document.getElementById('historyModal').classList.add('hidden');
    
    const modal = document.getElementById('receiptViewModal');
    const content = document.getElementById('receiptViewContent');
    
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="text-center py-8"><i style="color: var(--primary-color);" class="ph ph-spinner ph-spin text-4xl  mb-4"></i><p class="text-gray-600">Loading receipt...</p></div>';
    
    fetch(`/payments/${paymentId}/receipt-html`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load receipt');
            return res.text();
        })
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading receipt:', error);
            content.innerHTML = '<div class="text-center text-red-600 py-8"><i class="ph ph-warning-circle text-3xl mb-2"></i><p>Failed to load receipt.</p></div>';
        });
}

function closeReceiptViewModal() {
    document.getElementById('receiptViewModal').classList.add('hidden');
    // Show history modal again
    document.getElementById('historyModal').classList.remove('hidden');
}

function openSuccessModal() {
    document.getElementById('paymentSuccessModal').classList.remove('hidden');
}

function closeSuccessModal() {
    document.getElementById('paymentSuccessModal').classList.add('hidden');
    window.clearStudentPageCache?.();
    window.refreshCurrentStudentPage?.();
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.setAttribute('x-data', '{ show: true }');
    toast.setAttribute('x-show', 'show');
    toast.setAttribute('x-init', 'setTimeout(() => show = false, 4000)');
    
    const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
    const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
    const textColor = type === 'success' ? 'text-green-600' : 'text-red-600';
    const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
    const title = type === 'success' ? 'Success!' : 'Error!';
    
    toast.className = `fixed top-6 right-6 z-50 bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md`;
    toast.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full ${bgColor} flex items-center justify-center">
                <i class="ph ${icon} text-xl ${textColor}"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">${title}</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4500);
}

function paymentRealtimeHeaders(headers) {
    const socketId = window.Echo && typeof window.Echo.socketId === 'function'
        ? window.Echo.socketId()
        : null;

    if (socketId) headers['X-Socket-ID'] = socketId;
    return headers;
}

function completePaymentAndClose() {
    @if(session('payment_success') && session('payment_data'))
        const doneBtn = document.getElementById('doneBtn');
        const doneIcon = document.getElementById('doneIcon');
        const doneBtnText = document.getElementById('doneBtnText');
        
        // Show loading
        doneBtn.disabled = true;
        doneIcon.className = 'ph ph-spinner ph-spin mr-2 text-yellow-400';
        doneBtnText.textContent = 'Processing...';

        const paymentData = @json(session('payment_data'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(`/payments/${paymentData.announcement_id}/complete`, {
            method: 'POST',
            headers: paymentRealtimeHeaders({
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }),
            body: JSON.stringify({
                amount: paymentData.amount,
                title: paymentData.title
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeSuccessModal();
            } else {
                throw new Error(data.message || 'Failed to complete payment');
            }
        })
        .catch(error => {
            // Reset button
            doneBtn.disabled = false;
            doneIcon.className = 'ph ph-check mr-2 text-yellow-400';
            doneBtnText.textContent = 'Done';
            alert('Error: ' + error.message);
        });
    @else
        closeSuccessModal();
    @endif
}

function openRejectionModal(reason) {
    document.getElementById('rejectionReasonText').textContent = reason;
    document.getElementById('rejectionModal').classList.remove('hidden');
}

function closeRejectionModal() {
    document.getElementById('rejectionModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    // Resubmit Button Listeners (for rejected payments)
    document.querySelectorAll('.resubmit-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const announcementId = this.dataset.id;
            const amount = this.dataset.amount;
            const title = this.dataset.title;
            const currentReceiptUrl = this.dataset.receipt || '';
            const currentReference = this.dataset.reference || '';

            // Open the modal immediately so the student can start uploading
            // right away. The payment record is prepared in the background.
            currentPaymentData = {
                amount: parseFloat(amount),
                title: title,
                payment_id: announcementId,
                student_payment_id: null,
                existing_receipt_url: currentReceiptUrl,
                existing_reference: currentReference
            };
            openReceiptModalWithData();

            // Prepare the payment record in the background. The upload form
            // waits for this before submitting so no duplicate is created.
            const creationPromise = fetch('{{ route("payments.gcash.create-pending") }}', {
                method: 'POST',
                headers: paymentRealtimeHeaders({
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }),
                body: JSON.stringify({
                    amount: amount,
                    title: title,
                    payment_id: announcementId
                })
            })
            .then(function(res) { return res.json(); });

            currentPaymentData.creationPromise = creationPromise;

            creationPromise.then(function(data) {
                if (data.success) {
                    currentPaymentData.student_payment_id = data.student_payment_id;
                } else {
                    showToast('error', data.message || 'Failed to resubmit. Please try again.');
                }
            })
            .catch(function() {
                showToast('error', 'Network error. Please try again.');
            });
        });
    });

    // GCash Payment Button Listeners
    document.querySelectorAll('.gcash-pay-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const announcementId = this.dataset.id;
            const gcashNumber = this.dataset.gcash || '09XX-XXX-XXXX';
            openGcashModal(this.dataset.amount, this.dataset.title, announcementId, gcashNumber);
        });
    });

    // GCash "Send Receipt" Button Handler
    var gcashSubmitBtn = document.getElementById('gcashSubmitBtn');
    if (gcashSubmitBtn) {
        gcashSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const amount = document.getElementById('gcash_amount').value;
            const title = document.getElementById('gcash_title').value;
            const announcementId = document.getElementById('gcash_announcement_id').value;

            if (!amount || !title || !announcementId) {
                alert('Please fill in all required fields');
                return;
            }

            // Store payment data temporarily and open receipt modal directly
            currentPaymentData = {
                amount: parseFloat(amount),
                title: title,
                payment_id: announcementId
            };

            // Close GCash modal
            closeGcashModal();
            // Open receipt upload modal
            openReceiptModalWithData();
        });
    }

    // Receipt file preview
    var receiptFile = document.getElementById('receipt_file');
    if (receiptFile) {
        receiptFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('receiptPreview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Receipt Upload Form Handler
    var receiptForm = document.getElementById('receiptUploadForm');
    if (receiptForm) {
        receiptForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData();
            const referenceNumber = document.getElementById('reference_number').value.replace(/\s/g, ''); // Remove spaces
            const receiptFile = document.getElementById('receipt_file').files[0];
            const csrfToken = document.getElementById('receipt_csrf_token').value;

            if (!referenceNumber) {
                alert('Please enter the GCash reference number');
                return;
            }

            // Validate 13 digits
            if (referenceNumber.length !== 13) {
                alert('Reference number must be exactly 13 digits');
                return;
            }

            if (!receiptFile) {
                alert('Please select a receipt file');
                return;
            }

            // If the payment record is still being prepared in the background
            // (resubmit flow), wait for it before submitting so no duplicate
            // payment is created.
            if (currentPaymentData.creationPromise) {
                const creationData = await currentPaymentData.creationPromise.catch(function() { return null; });
                if (!creationData || !creationData.success) {
                    showToast('error', (creationData && creationData.message) || 'Failed to prepare payment. Please try again.');
                    return;
                }
                currentPaymentData.student_payment_id = creationData.student_payment_id;
            }

            // Add payment data from currentPaymentData
            formData.append('reference_number', referenceNumber);
            formData.append('receipt', receiptFile);
            formData.append('amount', currentPaymentData.amount);
            formData.append('title', currentPaymentData.title);

            // Determine if this is a resubmit (student_payment_id exists) or new payment
            var uploadUrl;
            if (currentPaymentData.student_payment_id) {
                formData.append('payment_id', currentPaymentData.student_payment_id);
                uploadUrl = '{{ route("payments.gcash.upload-receipt") }}';
            } else {
                formData.append('payment_id', currentPaymentData.payment_id);
                uploadUrl = '{{ route("payments.gcash.submit-with-receipt") }}';
            }
            formData.append('_token', csrfToken);

            // Show processing
            document.getElementById('receiptUploadForm').classList.add('hidden');
            document.getElementById('receiptProcessing').classList.remove('hidden');

            fetch(uploadUrl, {
                method: 'POST',
                headers: paymentRealtimeHeaders({
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }),
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeReceiptModal();
                    showToast('success', data.message || 'Receipt uploaded successfully!');
                    window.clearStudentPageCache?.();
                    window.refreshCurrentStudentPage?.();
                } else {
                    document.getElementById('receiptProcessing').classList.add('hidden');
                    document.getElementById('receiptUploadForm').classList.remove('hidden');
                    showToast('error', data.message || 'Failed to upload receipt');
                }
            })
            .catch(error => {
                document.getElementById('receiptProcessing').classList.add('hidden');
                document.getElementById('receiptUploadForm').classList.remove('hidden');
                showToast('error', error.message || 'Failed to upload receipt');
            });
        });
        }

    // Check if returning from PayMongo payment - show success modal
    @if(session('payment_success'))
        setTimeout(function() {
            openSuccessModal();
        }, 500);
    @endif

    // Check for toast messages in sessionStorage
    var toastData = sessionStorage.getItem('paymentToast');
    if (toastData) {
        sessionStorage.removeItem('paymentToast');
        const data = JSON.parse(toastData);
        showToast(data.type, data.message);
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const gcashModal = document.getElementById('gcashModal');
        const historyModal = document.getElementById('historyModal');
        const rejectionModal = document.getElementById('rejectionModal');

        if (event.target === gcashModal) {
            closeGcashModal();
        }
        if (event.target === historyModal) {
            closeHistoryModal();
        }
        if (event.target === rejectionModal) {
            closeRejectionModal();
        }
    });
});
</script>



<!-- Rejection Reason Modal -->
<div id="rejectionModal" class="payment-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] hidden p-4" role="dialog" aria-modal="true" aria-labelledby="rejection-modal-title">
    <div class="payment-modal__surface bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden">
        <div class="payment-modal__brand bg-gradient-to-r from-red-500 to-red-600 p-4 text-white">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="ph ph-warning-circle text-xl"></i>
                    <h2 id="rejection-modal-title" class="text-lg font-black tracking-tight">Receipt Rejected</h2>
                </div>
                <button type="button" onclick="closeRejectionModal()" class="payment-modal__close text-red-100 hover:text-white transition" aria-label="Close rejection details">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600 mb-3">Your payment receipt was rejected for the following reason:</p>
            <div class="bg-red-50 p-4 rounded-lg">
                <p class="text-sm text-red-800 font-medium" id="rejectionReasonText">No reason provided.</p>
            </div>
            <p class="text-xs text-gray-500 mt-4">Please upload a new receipt with the correct details.</p>
            <div class="mt-4 flex justify-end">
                <button onclick="closeRejectionModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition">
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrCodeModal" class="payment-modal fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="payment-modal__surface relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                    <h3 id="qr-modal-title" class="text-xl font-black tracking-tight text-gray-900">GCash QR Code</h3>
                <button type="button" onclick="closeQRModal()" class="payment-modal__close text-gray-400 hover:text-gray-600" aria-label="Close QR code">
                    <i class="ph ph-x text-2xl"></i>
                </button>
            </div>
            <div class="flex justify-center mb-4">
                <img id="qrCodeImage" src="" alt="GCash QR Code" class="max-w-full h-auto rounded-lg border-4 border-green-500 shadow-lg">
            </div>
            <p class="text-center text-sm text-gray-600">Scan this QR code using your GCash app to pay</p>
        </div>
    </div>
</div>

<script>
var currentQRCode = null;

function openQRModal() {
    console.log('openQRModal called');
    const announcementId = document.getElementById('gcash_announcement_id').value;
    console.log('Announcement ID:', announcementId);
    
    if (!announcementId) {
        console.error('No announcement ID found');
        alert('Payment information not available');
        return;
    }
    
    // Hide GCash modal
    document.getElementById('gcashModal').classList.add('hidden');
    
    // Fetch QR code from server
    const url = `/payments/${announcementId}/qr-code`;
    console.log('Fetching QR from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.qr_code) {
                currentQRCode = data.qr_code;
                document.getElementById('qrCodeImage').src = data.qr_code;
                document.getElementById('qrCodeModal').classList.remove('hidden');
            } else {
                alert('QR Code not available for this payment');
                document.getElementById('gcashModal').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error loading QR code:', error);
            alert('Failed to load QR code: ' + error.message);
            document.getElementById('gcashModal').classList.remove('hidden');
        });
}

function closeQRModal() {
    console.log('closeQRModal called');
    document.getElementById('qrCodeModal').classList.add('hidden');
    // Show GCash modal again
    document.getElementById('gcashModal').classList.remove('hidden');
}
</script>

<style>
    .student-payments-page {
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

    .student-payments-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

    .payments-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
    }

    .payments-hero__copy { max-width: 44rem; }
    .payments-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #0071e3);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .payments-hero h1,
    .payments-section__heading h2,
    .payments-empty h2 {
        margin: 0;
        color: var(--neu-text);
        font-weight: 700;
        letter-spacing: -.045em;
    }

    .payments-hero h1 { color: var(--neu-text); font-size: 1.5rem; font-weight: 900; letter-spacing: -.025em; line-height: 1.25; }
    .payments-hero__copy > p:last-child {
        max-width: 38rem;
        margin: 1rem 0 0;
        color: var(--neu-muted);
        font-size: clamp(1rem, 2vw, 1.125rem);
        line-height: 1.55;
    }

    .payment-history-trigger,
    .payment-action,
    .payment-icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        border: 0;
        font-weight: 600;
        letter-spacing: -.01em;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease, color .28s ease;
    }

    .payment-history-trigger {
        min-height: 2.9rem;
        padding: .42rem 1rem .42rem .5rem;
        border: 0;
        border-radius: 999px;
        background: var(--neu-bg);
        box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow);
        color: var(--neu-text);
        font-size: .9rem;
        white-space: nowrap;
    }

    .payment-button-icon {
        display: inline-flex;
        width: 2rem;
        height: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .13);
        color: var(--primary-color, #0071e3);
        font-size: 1.05rem;
        box-shadow: inset 2px 2px 4px rgba(var(--primary-color-rgb, 0, 113, 227), .12), inset -2px -2px 4px rgba(255, 255, 255, .6);
    }

    .payment-history-trigger:hover,
    .payment-history-trigger:focus-visible { box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow); }

    .payment-history-trigger--desktop { display: none; }
    @media (min-width: 640px) {
        .payment-history-trigger--desktop { display: inline-flex; }
        .payment-history-trigger--mobile { display: none; }
    }

    .payments-section {
        padding: clamp(1.25rem, 3vw, 2rem);
        border: 0;
        border-radius: 1.5rem;
        background: var(--neu-bg);
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow);
    }
    .payments-section__heading {
        margin-bottom: 1.5rem;
    }

    .payments-section__heading h2 { font-size: clamp(1.65rem, 3vw, 2rem); line-height: 1.08; }
    .payments-section__description { max-width: 37rem; margin: .65rem 0 0; color: var(--neu-muted); font-size: .92rem; line-height: 1.55; }
    .payments-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 21rem), 25rem)) !important; justify-content: start; }

    @media (min-width: 1024px) {
        .payments-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
    }

    .payment-card {
        display: flex;
        min-height: 18.5rem;
        flex-direction: column;
        border: 0;
        border-radius: 1.5rem;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        animation: payment-card-in .7s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--card-index, 0) * 55ms);
    }

    .payment-card:hover { transform: translateY(-3px); box-shadow: 12px 12px 24px var(--neu-dark-shadow), -12px -12px 24px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .22) !important; }
    .payment-card__header {
        padding: 1.35rem 1.45rem 0 !important;
        background: transparent !important;
    }

    .payment-card__header > div { align-items: flex-start; }
    .payment-card__title {
        color: var(--neu-text) !important;
        font-size: 1.25rem !important;
        font-weight: 700 !important;
        line-height: 1.2;
        letter-spacing: -.025em !important;
    }

    .payment-card__category {
        border: 0;
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .13) !important;
        color: var(--primary-color, #0071e3) !important;
        font-size: .68rem !important;
        font-weight: 700 !important;
        letter-spacing: .025em;
        text-transform: uppercase;
        box-shadow: 3px 3px 6px rgba(var(--primary-color-rgb, 0, 113, 227), .2), -3px -3px 6px rgba(255, 255, 255, .8);
    }

    .payment-card__body { display: flex; flex: 1; flex-direction: column; padding: 1.2rem 1.45rem 1.45rem !important; }
    .payment-card__amount {
        margin-bottom: auto !important;
        padding: 1rem !important;
        border: 0;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .45), inset -4px -4px 8px rgba(255, 255, 255, .8) !important;
    }

    .payment-card__amount span:first-child { color: var(--neu-muted) !important; font-size: .79rem !important; font-weight: 600 !important; }
    .payment-card__amount span:last-child { color: var(--neu-text) !important; font-weight: 700 !important; }
    .payment-card__amount > div:first-child span:last-child { font-size: 1.6rem !important; letter-spacing: -.035em; }
    .payment-card__amount > div:last-child { padding-top: .7rem; border-top: 1px solid var(--neu-divider); }

    .payment-card__body > button,
    .payment-card__body > .flex > button { min-height: 3.15rem; margin-top: 1.25rem; border-radius: .9rem !important; }
    .payment-card__body > .flex { margin-top: 1.25rem; }
    .payment-card__body > .flex > button { margin-top: 0; }
    .payment-card .gcash-pay-btn { width: 100%; background: var(--primary-color, #0071e3) !important; box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85); }
    .payment-card .resubmit-btn { background: #EA580C !important; box-shadow: 5px 5px 12px rgba(234, 88, 12, .3), -5px -5px 12px rgba(255, 255, 255, .85); }
    .payment-card .gcash-pay-btn:hover,
    .payment-card .resubmit-btn:hover { transform: translateY(-2px); filter: brightness(1.03); }
    .payment-card__body > button[disabled] { width: 100%; border: 0; background: var(--neu-bg) !important; color: var(--neu-muted) !important; box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .8) !important; }
    .payment-card__body > button.bg-green-500[disabled] { background: rgba(36, 138, 80, .10) !important; color: #248a50 !important; box-shadow: inset 3px 3px 6px rgba(36, 138, 80, .16), inset -3px -3px 6px rgba(255, 255, 255, .7) !important; }
    .payment-card__body > button.bg-yellow-400[disabled] { background: rgba(166, 106, 0, .10) !important; color: #a66a00 !important; box-shadow: inset 3px 3px 6px rgba(166, 106, 0, .16), inset -3px -3px 6px rgba(255, 255, 255, .7) !important; }
    .payment-card .flex.items-center.gap-2 > .resubmit-btn { flex: 1 1 auto; }
    .payment-card .flex.items-center.gap-2 > button:last-child { width: 3.15rem; padding: 0 !important; border: 0; border-radius: .9rem !important; background: rgba(220, 38, 38, .08) !important; color: #B91C1C !important; box-shadow: inset 3px 3px 6px rgba(220, 38, 38, .14), inset -3px -3px 6px rgba(255, 255, 255, .7) !important; }

    .payments-empty {
        max-width: 38rem;
        margin: 3.5rem auto;
        padding: 3.75rem 2rem;
        border: 0;
        border-radius: 1.75rem;
        background: var(--neu-bg);
        box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8);
        text-align: center;
    }

    .payments-empty > span { display: inline-flex; width: 3.7rem; height: 3.7rem; align-items: center; justify-content: center; margin-bottom: 1.25rem; border-radius: 50%; background: rgba(36, 138, 80, .12); color: #248a50; font-size: 2rem; box-shadow: 5px 5px 10px rgba(36, 138, 80, .2), -5px -5px 10px rgba(255, 255, 255, .9); }
    .payments-empty h2 { font-size: clamp(1.8rem, 4vw, 2.45rem); }
    .payments-empty > p:last-child { max-width: 28rem; margin: .8rem auto 0; color: var(--neu-muted); line-height: 1.55; }

    .payment-modal { background: rgba(17, 17, 19, .46) !important; backdrop-filter: blur(4px); }
    .payment-modal:not(.hidden) { animation: payment-backdrop-in .28s ease-out both; }
    .payment-modal:not(.hidden) .payment-modal__surface { animation: payment-modal-in .42s cubic-bezier(.2, .8, .2, 1) both; }
    .payment-modal__surface { border: 0; border-radius: 1.5rem !important; background: #ffffff !important; box-shadow: 0 24px 60px rgba(17, 17, 19, .30) !important; }
    .payment-modal__header { padding: 1.45rem 1.5rem !important; border-color: var(--neu-divider) !important; }
    .payment-modal__header h2, .payment-modal__header h3 { margin: 0; color: var(--neu-text); font-size: 1.4rem !important; font-weight: 700 !important; letter-spacing: -.03em !important; }
    .payment-modal__close { display: inline-flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 50%; background: var(--neu-bg); color: var(--neu-muted) !important; box-shadow: 3px 3px 6px var(--neu-dark-shadow), -3px -3px 6px var(--neu-light-shadow); transition: transform .2s ease, box-shadow .2s ease, color .2s ease; }
    .payment-modal__close:hover, .payment-modal__close:focus-visible { transform: rotate(90deg); background: var(--neu-bg); color: var(--primary-color) !important; box-shadow: inset 2px 2px 5px var(--neu-dark-shadow), inset -2px -2px 5px var(--neu-light-shadow); }
    .payment-modal__brand { padding: 1.45rem 1.5rem !important; border-bottom: 1px solid var(--neu-divider); background: transparent !important; color: var(--neu-text) !important; }
    .payment-modal__brand h2 { font-size: 1.4rem !important; font-weight: 700 !important; letter-spacing: -.03em !important; }
    #gcashModal .payment-modal__brand i:not(.ph-x), #receiptModal .payment-modal__brand i:not(.ph-x) { color: var(--primary-color, #0071e3); }
    #rejectionModal .payment-modal__brand i:not(.ph-x) { color: #d72d24; }

    .payment-history-table { border: 0; border-radius: 1rem !important; background: transparent !important; box-shadow: none !important; }
    .payment-history-table thead { background: #DDE4EE !important; }
    .payment-history-table th { color: var(--neu-muted) !important; font-size: .66rem !important; font-weight: 700 !important; letter-spacing: .07em; }
    .payment-history-table tbody tr { transition: background-color .2s ease; }
    .payment-history-table tbody tr:hover { background: rgba(var(--primary-color-rgb, 0, 113, 227), .045); }
    .payment-history-table td { border-color: var(--neu-divider) !important; }
    .payment-history-table .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: var(--neu-divider) !important; }

    .payment-history-cards { padding: 0; }
    .payment-history-card {
        padding: 1rem 1.1rem;
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 5px 5px 10px var(--neu-dark-shadow), -5px -5px 10px var(--neu-light-shadow) !important;
    }
    .payment-history-card .border-t { border-color: var(--neu-divider) !important; }
    .payment-history-badge {
        display: inline-flex;
        align-items: center;
        padding: .3rem .6rem;
        border-radius: 999px;
        font-size: .65rem !important;
        font-weight: 700 !important;
        letter-spacing: .02em;
        white-space: nowrap;
    }
    .payment-history-label {
        margin: 0 0 .15rem;
        color: var(--neu-muted) !important;
        font-size: .58rem !important;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
    }
    .payment-history-value {
        margin: 0;
        color: var(--neu-text) !important;
        font-size: .9rem;
        font-weight: 600;
    }
    .payment-history-meta {
        margin: 0 0 .15rem;
        color: var(--neu-muted) !important;
        font-size: .68rem !important;
    }
    .payment-history-amount {
        margin: 0;
        color: var(--primary-color) !important;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -.02em;
    }
    .payment-history-actions { display: flex; align-items: center; gap: .5rem; }
    .payment-history-action {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .5rem .75rem;
        border: 0;
        border-radius: .75rem;
        background: var(--neu-bg);
        color: var(--primary-color);
        font-size: .72rem;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 3px 3px 6px var(--neu-dark-shadow), -3px -3px 6px var(--neu-light-shadow);
        transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s ease, filter .25s ease;
    }
    .payment-history-action:hover {
        transform: translateY(-1px);
        box-shadow: inset 2px 2px 5px var(--neu-dark-shadow), inset -2px -2px 5px var(--neu-light-shadow);
    }
    .payment-history-action--solid {
        background: var(--primary-color);
        color: #fff;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85);
    }
    .payment-history-action--solid:hover { filter: brightness(1.05); }
    .payment-history-empty > i { color: rgba(var(--primary-color-rgb, 0, 113, 227), .25) !important; }
    .payment-history-empty p { color: var(--neu-muted) !important; }

    #gcashModal .p-5, #gcashModal .p-8 { padding: 1.5rem !important; }
    #gcashModal .gcash-form > div:nth-of-type(1) { border: 0; border-radius: 1rem !important; background: var(--neu-bg) !important; box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .4), inset -4px -4px 8px rgba(255, 255, 255, .8) !important; }
    #gcashModal #displayAmount { color: var(--primary-color) !important; letter-spacing: -.04em; }
    #gcashModal .border-2.border-blue-500 { border: 0 !important; border-radius: 1rem !important; background: var(--neu-bg) !important; box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .4), inset -4px -4px 8px rgba(255, 255, 255, .8) !important; }
    #gcashModal .bg-blue-50 { border: 0 !important; border-radius: 1rem !important; background: #E8F1FE !important; box-shadow: inset 3px 3px 7px rgba(37, 99, 235, .1), inset -3px -3px 7px rgba(255, 255, 255, .7) !important; }
    #gcashSubmitBtn, #receiptSubmitBtn, #doneBtn { border-radius: .9rem !important; background: var(--primary-color, #0071e3) !important; box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
    #gcashSubmitBtn:hover, #receiptSubmitBtn:hover, #doneBtn:hover { transform: translateY(-2px); filter: brightness(1.03); }

    #receiptModal .payment-modal__brand {
        min-height: 5rem;
        padding: .9rem 1.4rem !important;
    }
    #receiptModal .receipt-modal__title-row { width: 100%; gap: 1rem; }
    #receiptModal .receipt-modal__title-group { gap: .8rem; }
    #receiptModal .receipt-modal__title-icon {
        display: inline-flex;
        width: 2.45rem;
        height: 2.45rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: .78rem;
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .13);
        color: var(--primary-color, #0071e3);
        font-size: 1.25rem;
        box-shadow: 4px 4px 8px rgba(var(--primary-color-rgb, 0, 113, 227), .22), -4px -4px 8px rgba(255, 255, 255, .8);
    }
    #receiptModal .receipt-modal__context {
        margin: 0 0 .15rem;
        color: var(--neu-muted);
        font-size: .62rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1;
        text-transform: uppercase;
    }
    #receiptModal .payment-modal__brand h2 { font-size: clamp(1.08rem, 2.5vw, 1.35rem) !important; line-height: 1.15; }
    #receiptModal .payment-modal__close { width: 2.4rem; height: 2.4rem; flex: 0 0 auto; }
    #receiptModal .receipt-modal__body { padding: 1.5rem !important; }
    #receiptModal .border-dashed { border: 1px dashed rgba(var(--primary-color-rgb, 0, 113, 227), .4) !important; border-radius: 1rem !important; background: rgba(var(--primary-color-rgb, 0, 113, 227), .05) !important; box-shadow: inset 3px 3px 8px rgba(var(--primary-color-rgb, 0, 113, 227), .08), inset -3px -3px 8px rgba(255, 255, 255, .6) !important; }
    #receiptModal .bg-blue-50 { border-left: 0 !important; border-radius: .85rem !important; background: #E8F1FE !important; box-shadow: inset 3px 3px 7px rgba(37, 99, 235, .08), inset -3px -3px 7px rgba(255, 255, 255, .7) !important; }
    #receiptModal .bg-gray-50 { border-radius: .85rem !important; background: #DDE4EE !important; box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .35), inset -3px -3px 7px rgba(255, 255, 255, .8) !important; }
    #receiptModal input[type="text"] { border: 0 !important; background: var(--neu-bg) !important; color: var(--neu-text) !important; box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .8) !important; }
    #receiptModal input[type="text"]:focus { outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .3); outline-offset: 2px; }
    #receiptModal button.bg-gray-300 { border-radius: .9rem !important; background: var(--neu-bg) !important; color: var(--neu-text) !important; box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important; }
    #receiptModal button.bg-gray-300:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }

    #paymentSuccessModal .payment-modal__surface { border-radius: 1.75rem !important; }
    #paymentSuccessModal .bg-green-100 { background: rgba(36, 138, 80, .12) !important; box-shadow: 6px 6px 12px rgba(36, 138, 80, .2), -6px -6px 12px rgba(255, 255, 255, .9) !important; }
    #paymentSuccessModal .text-green-600 { color: #248a50 !important; }
    #paymentSuccessModal .bg-green-50 { border-left: 0 !important; border-radius: 1rem !important; background: rgba(36, 138, 80, .08) !important; box-shadow: inset 3px 3px 7px rgba(36, 138, 80, .12), inset -3px -3px 7px rgba(255, 255, 255, .7) !important; }
    #rejectionModal .bg-red-50 { border-radius: 1rem !important; background: rgba(220, 38, 38, .08) !important; box-shadow: inset 3px 3px 7px rgba(220, 38, 38, .12), inset -3px -3px 7px rgba(255, 255, 255, .7) !important; }
    #rejectionModal button.bg-gray-100 { border-radius: .9rem !important; background: var(--neu-bg) !important; color: var(--neu-text) !important; box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important; }
    #rejectionModal button.bg-gray-100:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }
    #qrCodeImage { border: 0 !important; border-radius: 1rem !important; background: #fff !important; box-shadow: inset 3px 3px 8px rgba(163, 177, 198, .4), inset -3px -3px 8px rgba(255, 255, 255, .85) !important; }

    .student-payments-page [x-data] { border-radius: 1rem !important; box-shadow: 4px 4px 10px rgba(163, 177, 198, .5), -4px -4px 10px rgba(255, 255, 255, .85) !important; }
    .student-payments-page button:focus-visible, .payment-modal button:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .35); outline-offset: 3px; }

    @keyframes payment-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes payment-backdrop-in { from { opacity: 0; } to { opacity: 1; } }
    @keyframes payment-modal-in { from { opacity: 0; transform: translateY(18px) scale(.975); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-payments-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-payments-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .payments-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
        .payments-hero__copy { width: 100%; }
        .payments-section { border-radius: 1.25rem; }
        .payments-section__description { max-width: none; }
        .payments-grid { justify-content: center; }
        .payment-card__header { padding: 1.15rem 1.2rem 0 !important; }
        .payment-card__body { padding: 1rem 1.2rem 1.2rem !important; }
        .payment-modal__surface { border-radius: 1.25rem !important; }
        .payment-modal__header, .payment-modal__brand { padding: 1.15rem 1.2rem !important; }
    }

    @media (min-width: 640px) {
        .payments-hero h1 { font-size: 1.875rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .payment-card, .payment-modal:not(.hidden), .payment-modal:not(.hidden) .payment-modal__surface { animation: none !important; }
        .payment-card, .payment-history-trigger, .payment-action, .payment-icon-action, .payment-modal__close,
        .payment-history-action, .payment-history-card { transition-duration: .01ms !important; }
    }

    /* Custom Pagination Styles */
    .pagination-container nav div:last-child a.relative, 
    .pagination-container nav div:last-child span.relative {
        background: var(--neu-bg) !important;
        border-color: transparent !important;
        color: var(--neu-muted) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        border-radius: .7rem !important;
        margin: 0 .2rem;
    }

    .pagination-container nav div:last-child span.relative.z-10 {
        background: var(--primary-color) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -4px -4px 10px rgba(255, 255, 255, .85) !important;
        font-weight: bold;
    }
    
    .pagination-container nav div:last-child a.relative:not(.z-10):hover {
        background: var(--neu-bg) !important;
        color: var(--primary-color) !important;
        box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
    }
    
    .pagination-container nav div:last-child a[rel="next"],
    .pagination-container nav div:last-child span[aria-label="Next &raquo;"],
    .pagination-container nav div:last-child a[rel="prev"],
    .pagination-container nav div:last-child span[aria-label="&laquo; Previous"] {
        color: var(--complementary-color) !important;
    }

    .pagination-container nav div:last-child a[rel="next"] svg,
    .pagination-container nav div:last-child a[rel="next"] i,
    .pagination-container nav div:last-child a[rel="prev"] svg,
    .pagination-container nav div:last-child a[rel="prev"] i {
        color: var(--complementary-color) !important;
    }
</style>

@endsection
