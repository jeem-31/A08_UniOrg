@extends('layouts.admin')

@section('title', 'View Complaint')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
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
             x-init="setTimeout(() => show = false, 5000)" 
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

    <div class="mb-6">
        <a href="{{ route('admin.complaints.index') }}" style="color: var(--primary-color);" class="hover:">
            <i class="ph ph-arrow-left mr-2"></i>Back to Complaints
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg">
        <!-- Complaint Header -->
        <div class="border-b border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <span class="px-3 py-1 text-sm rounded-full
                    {{ $complaint->type === 'complaint' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $complaint->type === 'suggestion' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $complaint->type === 'inquiry' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $complaint->type === 'feedback' ? 'bg-green-100 text-green-800' : '' }}">
                    {{ ucfirst($complaint->type) }}
                </span>
                <span style="color: var(--primary-color);" class="px-3 py-1 text-sm rounded-full
                    {{ $complaint->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $complaint->status === 'reviewed' ? 'bg-purple-100 ' : '' }}
                    {{ $complaint->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}">
                    {{ ucfirst($complaint->status) }}
                </span>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ $complaint->subject }}</h1>
            
            <div class="flex items-center text-sm text-gray-600">
                <div class="mr-6">
                    <i class="ph ph-user mr-2"></i>
                    @if($complaint->user)
                        {{ $complaint->user->full_name }}
                    @else
                        <span class="italic">Anonymous</span>
                    @endif
                </div>
                <div class="mr-6">
                    <i class="ph ph-calendar mr-2"></i>
                    {{ $complaint->created_at->format('M d, Y - g:i A') }}
                </div>
                @if($complaint->responded_by)
                    <div>
                        <i class="ph ph-arrow-bend-up-left mr-2"></i>
                        Responded by {{ $complaint->responder->full_name }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Complaint Content -->
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Message</h2>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $complaint->message }}</p>
                </div>
            </div>

            <!-- Admin Response Section -->
            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Admin Response</h2>
                
                @if($complaint->admin_response)
                    <div class="bg-purple-50 rounded-lg p-4 mb-4">
                        <p class="text-gray-700 whitespace-pre-wrap">{{ $complaint->admin_response }}</p>
                        @if($complaint->responded_at)
                            <p class="text-sm text-gray-500 mt-2">
                                <i class="ph ph-clock mr-1"></i>
                                Responded on {{ $complaint->responded_at->format('M d, Y - g:i A') }}
                            </p>
                        @endif
                    </div>
                @else
                    <div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">
                        <i class="ph ph-arrow-bend-up-left text-2xl mb-2"></i>
                        <p>No response submitted yet</p>
                    </div>
                @endif

                <!-- Response Form -->
                @hasFullAccess('complaints')
                <form method="POST" action="{{ route('admin.complaints.respond', $complaint) }}" class="space-y-4" data-admin-inplace-form>
                    @csrf
                    <div>
                        <label for="admin_response" class="block text-sm font-medium text-gray-700 mb-2">
                            Your Response
                        </label>
                        <textarea name="admin_response" id="admin_response" rows="6" required
                            style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:"
                            placeholder="Enter your response to the student...">{{ old('admin_response', $complaint->admin_response) }}</textarea>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Update Status
                        </label>
                        <select name="status" id="status" required
                            style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:">
                            <option value="pending" {{ $complaint->status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="reviewed" {{ $complaint->status === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                            <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('admin.complaints.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" style="background-color: var(--primary-color);" class="px-4 py-2  text-white rounded-lg hover:">
                            <i class="ph ph-paper-plane-tilt mr-2"></i>Submit Response
                        </button>
                    </div>
                </form>
                @endHasFullAccess
                @hasReadOnly('complaints')
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <i class="ph ph-eye text-yellow-600 text-2xl mb-2"></i>
                    <p class="text-yellow-700 font-medium">You have read-only access to complaints</p>
                    <p class="text-yellow-600 text-sm">You can view complaints but cannot respond to them</p>
                </div>
                @endHasReadOnly
            </div>
        </div>
    </div>
</div>
@endsection
