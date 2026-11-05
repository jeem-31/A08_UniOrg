@extends('layouts.dean')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('dean.proposals.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="ph ph-arrow-left mr-2"></i>Back to Proposals
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $proposal->event->title }}</h1>
                <p class="text-gray-600">Submitted by {{ $proposal->submitter->name }} on {{ $proposal->created_at->format('M d, Y') }}</p>
            </div>
            <div>
                @if($proposal->status === 'pending')
                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                @elseif($proposal->status === 'approved')
                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">Approved</span>
                @else
                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-800">Rejected</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold mb-2">Event Details</h3>
                <p class="text-gray-600 mb-1"><strong>Start:</strong> {{ $proposal->event->start_date->format('M d, Y h:i A') }}</p>
                <p class="text-gray-600 mb-1"><strong>End:</strong> {{ $proposal->event->end_date->format('M d, Y h:i A') }}</p>
                <p class="text-gray-600 mb-1"><strong>Venue:</strong> {{ $proposal->event->venue }}
                    @if(\App\Models\Event::venueOwnerAcronym($proposal->event->venue))
                        <span class="ml-1 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-purple-100 text-purple-800">Department-owned premise ({{ \App\Models\Event::venueOwnerAcronym($proposal->event->venue) }}) · Advisory</span>
                    @endif
                </p>
                <p class="text-gray-600 mb-1"><strong>Category:</strong> {{ $proposal->event->category_label }}</p>
            </div>
            <div>
                <h3 class="font-semibold mb-2">Planned Budget</h3>
                <p class="text-2xl font-bold text-green-600">₱{{ number_format($proposal->event->planned_budget, 2) }}</p>
            </div>
        </div>

        <div class="space-y-4 mb-6">
            @if($proposal->request_letter)
                <div>
                    <h3 class="font-semibold mb-2">Request Letter</h3>
                    <a href="{{ storage_url($proposal->request_letter) }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="ph ph-file-pdf mr-2"></i>View Document
                    </a>
                </div>
            @endif
        </div>

        @if($proposal->status === 'rejected' && $proposal->rejection_reason)
            <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-red-800 mb-2">Rejection Reason</h3>
                <p class="text-red-700">{{ $proposal->rejection_reason }}</p>
            </div>
        @endif

        @if($proposal->status === 'pending')
            <div class="flex gap-4">
                <form action="{{ route('dean.proposals.approve', $proposal) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded">
                        <i class="ph ph-check mr-2"></i>Approve
                    </button>
                </form>

                <button onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded">
                    <i class="ph ph-x mr-2"></i>Reject
                </button>
            </div>
        @endif
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Reject Proposal</h3>
        <form action="{{ route('dean.proposals.reject', $proposal) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Reason for Rejection</label>
                <textarea name="rejection_reason" rows="4" class="w-full border rounded px-3 py-2" required></textarea>
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Submit</button>
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
