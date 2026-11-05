@extends('layouts.admin')

@section('content')
<div class="min-h-screen py-4 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.documents.student-documents') }}" style="color: var(--primary-color);" class="hover: transition mb-2 inline-block font-semibold">
                    <i class="ph ph-arrow-left mr-2"></i>Back to Students
                </a>
                <h1 class="text-3xl font-bold text-gray-800 mb-1">{{ name_with_suffix($student->first_name, null, $student->last_name, $student->suffix ?? '') }}</h1>
                <p class="text-gray-600 text-sm">Student ID: {{ $student->student_id }} | {{ $student->year_level }} - Section {{ $student->section }}</p>
            </div>
        </div>

        @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="ph ph-check-circle mr-2"></i>
                <p class="text-sm font-semibold">{{ session('success') }}</p>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4" style="background-color: var(--primary-color);">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i style="color: var(--complementary-color);" class="ph ph-folder-open mr-3 "></i>Student Documents
                </h2>
            </div>

            <div class="p-6">
                @forelse($documents as $document)
                <div class="border-2 border-gray-200 rounded-xl p-5 mb-4 hover:shadow-xl transition-all"
                     style="transition: all 0.3s;"
                     @mouseenter="$el.style.borderColor = 'var(--primary-color)'"
                     @mouseleave="$el.style.borderColor = ''">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center flex-1">
                            <div class="flex-shrink-0 h-14 w-14 rounded-xl flex items-center justify-center text-white mr-4 shadow-lg" style="background-color: var(--primary-color);">
                                <i style="color: var(--complementary-color);" class="ph ph-file-text  text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-bold text-gray-900">
                                    @if($document->document_type === 'cor')
                                        Certificate of Registration
                                    @elseif($document->document_type === 'parent_consent')
                                        Parent Consent
                                    @elseif($document->document_type === 'medical_certificate')
                                        Medical Certificate
                                    @endif
                                </h3>
                                <div class="flex items-center flex-wrap gap-3 mt-2">
                                    <p class="text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded-full">
                                        <i class="ph ph-calendar mr-1" style="color: var(--primary-color);"></i>{{ $document->issue_date ? $document->issue_date->format('M d, Y') : 'N/A' }}
                                    </p>
                                    @if($document->expiry_date)
                                    <p class="text-xs {{ $document->isExpired() ? 'text-red-600 bg-red-100' : 'text-gray-600 bg-gray-100' }} px-2 py-1 rounded-full font-semibold">
                                        <i class="ph ph-clock mr-1"></i>
                                        {{ $document->isExpired() ? 'Expired' : 'Expires' }}: {{ $document->expiry_date->format('M d, Y') }}
                                    </p>
                                    @endif
                                    @if($document->is_verified)
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800 shadow-sm">
                                        <i class="ph ph-check-circle mr-1"></i>Verified
                                    </span>
                                    @else
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-yellow-100 text-yellow-800 shadow-sm">
                                        <i class="ph ph-clock mr-1"></i>Pending
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="{{ storage_url($document->file_path) }}" target="_blank" class="text-white px-4 py-2 rounded-lg text-sm font-semibold transition transform hover:scale-105 shadow-md" style="background-color: var(--primary-color);">
                                <i class="ph ph-eye mr-1"></i>View
                            </a>
                            @if(!$document->is_verified)
                            <form action="{{ route('admin.documents.verify', $document) }}" method="POST" data-admin-inplace-form>
                                @csrf
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition transform hover:scale-105 shadow-md">
                                    <i class="ph ph-check mr-1"></i>Verify
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-16">
                    <i class="ph ph-folder-open text-gray-300 text-6xl mb-4"></i>
                    <p class="text-lg font-bold text-gray-600">No Documents Yet</p>
                    <p class="text-sm text-gray-400 mt-2">This student hasn't uploaded any documents</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}
</style>
@endsection
