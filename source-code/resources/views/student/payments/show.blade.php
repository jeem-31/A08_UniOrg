@extends('layouts.app')

@section('title', 'Payment Details')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('payments.index') }}" style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-arrow-left mr-2"></i>Back to Payments
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <!-- Payment Header -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 text-white p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-black tracking-tight mb-2">Payment Details</h1>
                    <p class="text-purple-200">Transaction ID: {{ $payment->transaction_id }}</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold">₱{{ number_format($payment->amount, 2) }}</div>
                    <span class="px-3 py-1 text-sm rounded-full 
                        {{ $payment->status === 'completed' ? 'bg-green-500 text-white' : '' }}
                        {{ $payment->status === 'pending' ? 'bg-yellow-400 text-yellow-900' : '' }}
                        {{ $payment->status === 'failed' ? 'bg-red-500 text-white' : '' }}">
                        {{ ucfirst($payment->status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Transaction Details -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i style="color: var(--primary-color);" class="ph ph-receipt  mr-2"></i>Transaction Details
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Category:</span>
                            <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $payment->category)) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-medium">{{ strtoupper($payment->payment_method) }}</span>
                        </div>
                        @if($payment->reference_number)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reference Number:</span>
                            <span class="font-medium">{{ $payment->reference_number }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date Submitted:</span>
                            <span class="font-medium">{{ $payment->created_at->format('M d, Y h:i A') }}</span>
                        </div>
                        @if($payment->paid_at)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date Paid:</span>
                            <span class="font-medium">{{ $payment->paid_at->format('M d, Y h:i A') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Student Information -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i style="color: var(--primary-color);" class="ph ph-user  mr-2"></i>Student Information
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Name:</span>
                            <span class="font-medium">{{ $payment->user->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Student ID:</span>
                            <span class="font-medium">{{ $payment->user->student_id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium">{{ $payment->user->email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Program:</span>
                            <span class="font-medium">{{ $payment->user->program ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($payment->announcement)
            <!-- Announcement Details -->
            <div class="mt-6 bg-purple-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-purple-900 mb-4">
                    <i style="color: var(--primary-color);" class="ph ph-megaphone  mr-2"></i>Announcement Details
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p style="color: var(--primary-color);" class="text-sm  font-medium">Title:</p>
                        <p class="font-semibold">{{ $payment->announcement->title }}</p>
                    </div>
                    <div>
                        <p style="color: var(--primary-color);" class="text-sm  font-medium">Due Date:</p>
                        <p class="font-semibold">{{ $payment->announcement->due_date->format('M d, Y') }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p style="color: var(--primary-color);" class="text-sm  font-medium">Description:</p>
                        <p>{{ $payment->announcement->description }}</p>
                    </div>
                </div>
            </div>
            @endif

            @if($payment->notes)
            <!-- Notes -->
            <div class="mt-6 bg-blue-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-900 mb-2">
                    <i class="ph ph-note text-blue-600 mr-2"></i>Notes
                </h3>
                <p class="text-blue-800">{{ $payment->notes }}</p>
            </div>
            @endif

            @if($payment->verifier)
            <!-- Verification Information -->
            <div class="mt-6 bg-green-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-green-900 mb-4">
                    <i class="ph ph-check-circle text-green-600 mr-2"></i>Verification Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-green-700 font-medium">Verified By:</p>
                        <p class="font-semibold">{{ $payment->verifier->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-green-700 font-medium">Verification Date:</p>
                        <p class="font-semibold">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y h:i A') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Actions -->
            <div class="mt-6 flex flex-wrap gap-3">
                @if($payment->status === 'completed' && $payment->receipt_path)
                    <a href="{{ route('payments.download-receipt', $payment) }}" 
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="ph ph-download-simple mr-2"></i>Download Receipt
                    </a>
                @endif
                
                @if($payment->status === 'pending')
                    <button class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700" disabled>
                        <i class="ph ph-clock mr-2"></i>Awaiting Verification
                    </button>
                @endif
                
                @if($payment->status === 'completed' && !$payment->receipt_path)
                    <button class="px-4 py-2 bg-gray-400 text-white rounded-lg" disabled>
                        <i class="ph ph-file-pdf mr-2"></i>Receipt Being Generated
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Timeline -->
    <div class="mt-6 bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i style="color: var(--primary-color);" class="ph ph-clock-counter-clockwise  mr-2"></i>Payment Timeline
        </h3>
        <div class="space-y-4">
            <div class="flex items-center">
                <div style="background-color: var(--primary-color);" class="w-4 h-4  rounded-full"></div>
                <div class="ml-4">
                    <p class="font-medium">Payment Submitted</p>
                    <p class="text-sm text-gray-600">{{ $payment->created_at->format('M d, Y h:i A') }}</p>
                </div>
            </div>
            
            @if($payment->paid_at)
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-600 rounded-full"></div>
                <div class="ml-4">
                    <p class="font-medium">Payment Completed</p>
                    <p class="text-sm text-gray-600">{{ $payment->paid_at->format('M d, Y h:i A') }}</p>
                </div>
            </div>
            @endif
            
            @if($payment->status === 'pending')
            <div class="flex items-center">
                <div class="w-4 h-4 bg-yellow-600 rounded-full animate-pulse"></div>
                <div class="ml-4">
                    <p class="font-medium">Awaiting Verification</p>
                    <p class="text-sm text-gray-600">Payment is being reviewed by SOCC officers</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
