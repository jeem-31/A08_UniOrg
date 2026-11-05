@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="ph ph-tray mr-2" style="color: var(--primary-color);"></i>
            System Requests
        </h1>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-700 text-sm font-semibold">Pending</p>
                    <p class="text-3xl font-bold text-yellow-800">{{ $requests->where('status', 'Pending')->count() }}</p>
                </div>
                <i style="color: var(--complementary-color);" class="ph ph-clock text-4xl "></i>
            </div>
        </div>

        <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-700 text-sm font-semibold">Approved</p>
                    <p class="text-3xl font-bold text-green-800">{{ $requests->where('status', 'Approved')->count() }}</p>
                </div>
                <i class="ph ph-check-circle text-4xl text-green-500"></i>
            </div>
        </div>

        <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-700 text-sm font-semibold">Rejected</p>
                    <p class="text-3xl font-bold text-red-800">{{ $requests->where('status', 'Rejected')->count() }}</p>
                </div>
                <i class="ph ph-x-circle text-4xl text-red-500"></i>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Organization
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Requester
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($requests as $request)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $request->organization_name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $request->requester_name }}</div>
                            <div class="text-sm text-gray-500">{{ $request->requester_position ?: 'Not specified' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                {{ $request->organization_type === 'Academic' ? 'bg-blue-100 text-blue-800' : '' }}"
                                @if($request->organization_type !== 'Academic') style="background-color: rgba(130, 56, 179, 0.1); color: var(--primary-color);" @endif>
                                {{ $request->organization_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($request->status === 'Pending')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    <i class="ph ph-clock mr-1"></i> Pending
                                </span>
                            @elseif($request->status === 'Approved')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <i class="ph ph-check mr-1"></i> Approved
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    <i class="ph ph-x mr-1"></i> Rejected
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $request->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="{{ route('admin.system-requests.show', $request) }}" 
                               style="color: var(--primary-color);"
                               onmouseover="this.style.opacity='0.7'" 
                               onmouseout="this.style.opacity='1'">
                                <i class="ph ph-eye mr-1"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="ph ph-tray text-4xl mb-2"></i>
                            <p>No system requests yet</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
