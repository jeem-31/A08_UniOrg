@extends('layouts.dean')

@section('title', 'Pending Approvals')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Pending Approvals</h1>
    
    <!-- Pending Payments -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Pending Payments</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($pendingPayments as $payment)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $payment->transaction_id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $payment->user->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $payment->category)) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">₱{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $payment->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="text-green-600 hover:text-green-900 mr-2">Approve</button>
                                <button class="text-red-600 hover:text-red-900">Reject</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No pending payments</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $pendingPayments->links() }}
        </div>
    </div>

    <!-- Pending Complaints -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Pending Complaints</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($pendingComplaints as $complaint)
                        <tr>
                            <td class="px-6 py-4">{{ $complaint->subject }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">@if($complaint->is_anonymous) Anonymous @else{{ $complaint->user?->short_name ?? 'Unknown' }}@endif</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $complaint->type)) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $complaint->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('dean.content-overview', ['tab' => 'complaints']) }}" class="text-blue-600 hover:text-blue-900">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No pending complaints</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $pendingComplaints->links() }}
        </div>
    </div>
</div>
@endsection
