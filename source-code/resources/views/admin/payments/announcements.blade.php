@extends('layouts.admin')

@section('title', 'Payment Announcements')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Payment Announcements</h1>
            <p class="text-gray-600 mt-2">Manage payment announcements and fees</p>
        </div>
        <a href="{{ route('admin.payments.announcements.create') }}" style="background-color: var(--primary-color);" class="px-4 py-2  text-white rounded-lg hover:">
            <i class="ph ph-plus mr-2"></i>New Announcement
        </a>
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
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md"
         style="display: none;">
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                <i class="ph ph-warning-circle text-xl text-red-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Error!</p>
            <p class="text-sm text-gray-600">{{ session('error') }}</p>
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Methods</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($announcements as $announcement)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $announcement->title }}</div>
                            <div class="text-sm text-gray-500">{{ Str::limit($announcement->description, 50) }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-black">
                                {{ ucfirst(str_replace('_', ' ', $announcement->category)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                            ₱{{ number_format($announcement->amount, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($announcement->due_date)->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded 
                                {{ $announcement->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $announcement->status === 'inactive' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $announcement->status === 'closed' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ ucfirst($announcement->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($announcement->allow_gcash)
                                <span class="inline-block px-2 py-1 text-xs bg-green-100 text-green-800 rounded mr-1">GCash</span>
                            @endif
                            @if($announcement->allow_manual)
                                <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Manual</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ name_with_suffix($announcement->user->first_name ?? '', null, $announcement->user->last_name ?? '', $announcement->user->suffix ?? '') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('admin.payments.announcements.edit', $announcement) }}" 
                               style="color: var(--primary-color);" class="hover:opacity-75 mr-2">Edit</a>
                            <form method="POST" action="{{ route('admin.payments.announcements.delete', $announcement) }}" data-admin-inplace-form
                                  class="inline" onsubmit="return confirm('Are you sure you want to delete this announcement?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:opacity-75">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="ph ph-megaphone text-gray-300 text-6xl mb-4"></i>
                            <p class="text-lg">No payment announcements yet</p>
                            <a href="{{ route('admin.payments.announcements.create') }}" 
                               style="color: var(--primary-color);" class="hover:opacity-75 mt-2 inline-block">Create first announcement</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $announcements->links() }}
    </div>
</div>
@endsection
