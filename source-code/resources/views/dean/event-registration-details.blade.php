@extends('layouts.dean')

@section('title', 'Registration Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('dean.registration-reports') }}" class="text-blue-600 hover:text-blue-800">
            <i class="ph ph-arrow-left mr-2"></i>Back to Registration Reports
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h1 class="text-3xl font-bold mb-4">{{ $event->title }}</h1>
        <p class="text-gray-600 mb-4">{{ $event->start_date->format('M d, Y h:i A') }} - {{ $event->end_date->format('M d, Y h:i A') }}</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-blue-50 rounded p-4">
                <p class="text-gray-600 text-sm mb-1">Total Registrations</p>
                <p class="text-3xl font-bold text-blue-600">{{ $stats['total_registrations'] }}</p>
            </div>
            <div class="bg-green-50 rounded p-4">
                <p class="text-gray-600 text-sm mb-1">Paid Registrations</p>
                <p class="text-3xl font-bold text-green-600">{{ $stats['paid_registrations'] }}</p>
            </div>
            <div class="bg-purple-50 rounded p-4">
                <p class="text-gray-600 text-sm mb-1">Total Payments</p>
                <p style="color: var(--primary-color);" class="text-3xl font-bold ">₱{{ number_format($stats['total_payments'], 2) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h2 class="text-xl font-bold">Registered Participants</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered At</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($registrations as $registration)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ $registration->user->name }}</td>
                        <td class="px-6 py-4">{{ $registration->user->email }}</td>
                        <td class="px-6 py-4">
                            @if($registration->status === 'paid')
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Paid</span>
                            @elseif($registration->status === 'registered')
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Registered</span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Cancelled</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($registration->payment)
                                ₱{{ number_format($registration->payment->amount, 2) }}
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">{{ $registration->registered_at->format('M d, Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No registrations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $registrations->links() }}
    </div>
</div>
@endsection
