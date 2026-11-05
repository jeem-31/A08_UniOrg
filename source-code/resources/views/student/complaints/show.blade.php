@extends('layouts.app')

@section('title', 'View Submission')

@section('content')
<div class="student-neu-page max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-10">
    <div class="mb-4 sm:mb-6">
        <a href="{{ route('complaints.index') }}" style="color: var(--primary-color);" class="hover:opacity-80 text-sm sm:text-base">
            <i class="ph ph-arrow-left mr-2"></i>Back to My Submissions
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full 
                        {{ $complaint->type === 'complaint' ? 'bg-red-100 text-red-800' : '' }}
                        {{ $complaint->type === 'suggestion' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $complaint->type === 'inquiry' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $complaint->type === 'feedback' ? 'bg-green-100 text-green-800' : '' }}">
                        {{ ucfirst($complaint->type) }}
                    </span>
                    @if($complaint->is_anonymous)
                        <span class="px-3 py-1 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                            <i class="ph ph-user-focus mr-1"></i> Anonymous
                        </span>
                    @endif
                </div>
                <div class="sm:text-right">
                    @if($complaint->status === 'resolved')
                        <span class="px-3 py-1 inline-flex items-center text-xs sm:text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            <i class="ph ph-check mr-1"></i> Resolved
                        </span>
                    @elseif($complaint->status === 'reviewed')
                        <span class="px-3 py-1 inline-flex items-center text-xs sm:text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <i class="ph ph-eye mr-1"></i> Reviewed
                        </span>
                    @else
                        <span class="px-3 py-1 inline-flex items-center text-xs sm:text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            <i class="ph ph-clock mr-1"></i> Pending
                        </span>
                    @endif
                    <p class="text-xs sm:text-sm text-gray-500 mt-2">
                        Submitted on {{ $complaint->created_at->format('F d, Y \a\t g:i A') }}
                    </p>
                </div>
            </div>

            <div class="mb-6 sm:mb-8">
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-2 sm:mb-3 break-words">{{ $complaint->subject }}</h2>
                <div class="prose max-w-none">
                    <p class="text-sm sm:text-base text-gray-700 whitespace-pre-wrap break-words">{{ $complaint->message }}</p>
                </div>
            </div>

            @if($complaint->admin_response)
                <div class="border-t pt-4 sm:pt-6">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2 sm:mb-3">
                        <i style="color: var(--primary-color);" class="ph ph-arrow-bend-up-left mr-2"></i>Admin Response
                    </h3>
                    <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
                        <p class="text-sm sm:text-base text-gray-700 whitespace-pre-wrap break-words">{{ $complaint->admin_response }}</p>
                        @if($complaint->responded_by)
                            <p class="text-xs sm:text-sm text-gray-500 mt-3">
                                Responded by {{ $complaint->responder->full_name }} on {{ $complaint->updated_at->format('F d, Y \a\t g:i A') }}
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <div class="border-t pt-4 sm:pt-6">
                    <div class="text-center py-6 sm:py-8">
                        <i class="ph ph-hourglass-medium text-gray-400 text-4xl mb-3"></i>
                        <p class="text-sm sm:text-base text-gray-500">No response yet. Our team will review your submission and respond as soon as possible.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
