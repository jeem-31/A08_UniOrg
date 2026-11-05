@extends('layouts.admin')

@section('content')
<div class="min-h-screen py-4 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.documents.student-documents') }}" style="color: var(--primary-color);" class="hover: transition mb-2 inline-block font-semibold">
                    <i class="ph ph-arrow-left mr-2"></i>Back to Documents
                </a>
                <h1 class="text-3xl font-bold text-gray-800 mb-1">{{ name_with_suffix($student->first_name, null, $student->last_name, $student->suffix ?? '') }}</h1>
                <p class="text-gray-600 text-sm">Student ID: {{ $student->student_id }} | {{ $student->year_level }} - Section {{ $student->section }}</p>
            </div>
        </div>

        <!-- Shared Documents Grid -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4" style="background-color: var(--primary-color);">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i style="color: var(--complementary-color);" class="ph ph-share-network mr-3 "></i>Shared Documents
                </h2>
            </div>

            <div class="p-6">
                @forelse($sharedDocuments as $shared)
                    @php
                        $document = $shared->document;
                        $categories = [
                            'cor' => ['name' => 'Certificate of Registration', 'icon' => 'ph-file-text', 'color' => 'blue'],
                            'parent_consent' => ['name' => 'Parent Consent', 'icon' => 'ph-signature', 'color' => 'green'],
                            'medical_certificate' => ['name' => 'Medical Certificate', 'icon' => 'ph-file-plus', 'color' => 'red']
                        ];
                        $category = $categories[$document->document_type];
                    @endphp
                    
                    <div class="border-2 border-gray-200 rounded-xl p-4 mb-4 hover:shadow-lg transition-all"
                         style="transition: all 0.3s;"
                         @mouseenter="$el.style.borderColor = 'var(--primary-color)'"
                         @mouseleave="$el.style.borderColor = ''">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-14 w-14 bg-gradient-to-br from-{{ $category['color'] }}-500 to-{{ $category['color'] }}-700 rounded-xl flex items-center justify-center text-white mr-4 shadow-lg">
                                <i style="color: var(--complementary-color);" class="ph {{ $category['icon'] }}  text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-bold text-gray-900">{{ $category['name'] }}</h3>
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
                                    <span class="px-3 py-1 text-xs font-bold rounded-full shadow-sm" style="background-color: rgba(var(--primary-color-rgb), 0.1); color: var(--primary-color);">
                                        <i class="ph ph-share mr-1"></i>Shared {{ $shared->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <a href="{{ storage_url($document->file_path) }}" target="_blank" style="background-color: var(--primary-color);" class="hover: text-white px-4 py-2 rounded-lg text-sm font-semibold transition transform hover:scale-105 shadow-md">
                                    <i class="ph ph-eye mr-1"></i>View
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                <div class="text-center py-16">
                    <i class="ph ph-share-network text-gray-300 text-6xl mb-4"></i>
                    <p class="text-lg font-bold text-gray-600">No Shared Documents</p>
                    <p class="text-sm text-gray-400 mt-2">This student hasn't shared any documents yet</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
