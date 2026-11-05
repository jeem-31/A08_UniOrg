@extends('layouts.dean')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('dean.financial-reports.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="ph ph-arrow-left mr-2"></i>Back to Financial Reports
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $report->event->title }} - Financial Report</h1>
                <p class="text-gray-600">Submitted by {{ $report->submitter->name }} on {{ $report->created_at->format('M d, Y') }}</p>
            </div>
            <div>
                @if($report->status === 'pending')
                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                @elseif($report->status === 'approved')
                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">Approved</span>
                @else
                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-800">Rejected</span>
                @endif
            </div>
        </div>

        <div class="mb-6">
            <h3 class="font-semibold mb-2">Total Expenses</h3>
            <p class="text-3xl font-bold text-red-600">₱{{ number_format($report->total_expenses, 2) }}</p>
        </div>

        <div class="mb-6">
            <h3 class="font-semibold mb-2">Narrative Report</h3>
            <div class="bg-gray-50 rounded p-4">
                <p class="whitespace-pre-wrap">{{ $report->narrative_report }}</p>
            </div>
        </div>

        @if($report->approved_letter)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Approved Letter</h3>
                <a href="{{ storage_url($report->approved_letter) }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="ph ph-file-pdf mr-2"></i>View Document
                </a>
            </div>
        @endif

        @if($report->expense_receipts && count($report->expense_receipts) > 0)
            <div class="mb-6">
                <h3 class="font-semibold mb-2">Expense Receipts</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($report->expense_receipts as $receipt)
                        <a href="{{ storage_url($receipt) }}" target="_blank" class="border rounded p-2 hover:bg-gray-50">
                            <img src="{{ storage_url($receipt) }}" alt="Receipt" class="w-full h-32 object-cover rounded mb-2">
                            <p class="text-xs text-center text-gray-600">View Receipt</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($report->status === 'rejected' && $report->remarks)
            <div class="bg-red-50 border border-red-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-red-800 mb-2">Remarks</h3>
                <p class="text-red-700">{{ $report->remarks }}</p>
            </div>
        @endif

        @if($report->status === 'pending')
            <div class="flex gap-4">
                <form action="{{ route('dean.financial-reports.approve', $report) }}" method="POST" class="inline">
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
        <h3 class="text-xl font-bold mb-4">Reject Financial Report</h3>
        <form action="{{ route('dean.financial-reports.reject', $report) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Remarks</label>
                <textarea name="remarks" rows="4" class="w-full border rounded px-3 py-2" required></textarea>
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Submit</button>
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
