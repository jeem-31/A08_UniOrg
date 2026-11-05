@extends('layouts.osas')

@section('title', $request->organization_name . ' - Accreditation Detail')

@section('content')
<div class="max-w-[1400px] mx-auto py-6" x-data="{ activeTab: 'details' }">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <nav class="flex text-gray-500 text-[11px] font-semibold mb-2" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('osas.dashboard') }}" class="hover:text-[#26488C] transition-colors">OSAS</a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="ph ph-caret-right text-[10px] mx-2 text-gray-400"></i>
                            <a href="{{ route('osas.apply-org.index') }}" class="hover:text-[#26488C] transition-colors">Accreditation</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="ph ph-caret-right text-[10px] mx-2 text-gray-400"></i>
                            <span class="text-gray-400">{{ $request->organization_acronym ?? 'Detail' }}</span>
                        </div>
                    </li>
                </ol>
            </nav>
            <div class="flex items-center gap-3.5">
                <a href="{{ route('osas.apply-org.index') }}" class="w-10 h-10 rounded-lg bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-[#26488C] transition-all">
                    <i class="ph ph-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">
                        {{ $request->organization_name }}
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md {{ $request->organization_type === 'Academic' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $request->organization_type }}
                        </span>
                        <span class="text-gray-300 text-[11px] font-semibold">•</span>
                        <span class="text-gray-500 text-[11px] font-semibold">{{ $request->request_type === 'apply' ? 'New Recognition' : 'Renewal' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        @if($request->status === 'Pending' || $request->status === 'For-Checking')
            <div class="flex items-center gap-2.5">
                <button onclick="rejectRequest()" class="px-4 py-2.5 bg-white border border-red-200 text-red-600 font-semibold text-xs rounded-lg hover:bg-red-50 transition-all shadow-sm">
                    <i class="ph ph-x-circle mr-1.5"></i> Reject Request
                </button>
                <button onclick="approveRequest()" class="px-4 py-2.5 bg-[#26488C] text-white font-semibold text-xs rounded-lg hover:bg-[#1a3461] transition-all shadow-sm hover:shadow-[#26488C]/20">
                    <i class="ph ph-check-circle mr-1.5"></i> Approve Now
                </button>
            </div>
        @else
            <div class="flex items-center gap-2.5">
                <div class="px-3.5 py-2 rounded-lg border border-gray-100 bg-gray-50 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ $request->status === 'Approved' ? 'bg-green-500' : ($request->status === 'For-Checking' ? 'bg-blue-500' : 'bg-red-500') }}"></span>
                    <span class="text-xs font-semibold text-gray-700">{{ $request->status === 'For-Checking' ? 'For Checking' : $request->status }}</span>
                </div>
                <button onclick="window.print()" class="p-2.5 bg-white border border-gray-200 text-gray-500 rounded-lg hover:text-[#26488C] transition-all shadow-sm">
                    <i class="ph ph-printer"></i>
                </button>
            </div>
        @endif
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Column (Main Info & Checklist) -->
        <div class="lg:col-span-8 space-y-6">
            <!-- Requester Overview Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 bg-gray-50/30">
                    <h3 class="font-semibold text-gray-800 text-[11px] flex items-center gap-2">
                        <i class="ph ph-user-circle text-[#C4973B]"></i> Requester Overview
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-full bg-blue-50 text-[#26488C] flex items-center justify-center font-bold text-base">
                            {{ substr($request->requester_first_name, 0, 1) }}{{ substr($request->requester_last_name, 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-medium text-gray-400 mb-0.5">Requester Name</div>
                            <div class="text-base font-bold text-gray-900 truncate">{{ $request->requester_first_name }} {{ $request->requester_last_name }}</div>
                            <div class="text-xs font-medium text-gray-500">{{ $request->requester_position ?: 'Not specified' }}</div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400"><i class="ph ph-envelope"></i></div>
                            <div class="text-sm font-medium text-gray-700 truncate">{{ $request->requester_email }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400"><i class="ph ph-calendar-check"></i></div>
                            <div class="text-sm font-medium text-gray-700">Submitted on {{ $request->created_at->format('M d, Y • h:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Persisted Document Analysis Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 bg-gray-50/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-[11px] flex items-center gap-2">
                            <i class="ph ph-file-lock text-[#26488C]"></i> Document Requirements
                        </h3>
                        <p class="text-[11px] text-gray-400 mt-1">Documents are processed automatically after upload.</p>
                    </div>
                    <span class="text-[11px] font-semibold text-[#26488C]" id="analysisAggregateLabel">
                        {{ $analysisState['label'] }}
                    </span>
                </div>

                @php
                    $filterTypes = collect($documentAnalyses)->unique('document_field')->sortBy('requirement_label')->values();
                @endphp
                @if($filterTypes->isNotEmpty())
                    <div class="px-5 py-3 border-b border-gray-50 bg-white flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <input
                                type="search"
                                id="documentSearchInput"
                                placeholder="Search filename, document type, or auto-tag…"
                                autocomplete="off"
                                class="w-full pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 placeholder:text-gray-400 focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all"
                            >
                            <button type="button" id="documentSearchClear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 items-center justify-center" aria-label="Clear search">
                                <i class="ph ph-x text-xs"></i>
                            </button>
                        </div>
                        <div class="relative sm:w-60 sm:shrink-0">
                            <i class="ph ph-funnel-simple absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <select id="documentTypeFilter" class="w-full appearance-none pl-9 pr-8 py-2 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 bg-white focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                                <option value="">All document types</option>
                                @foreach($filterTypes as $type)
                                    <option value="{{ $type['document_field'] }}">{{ $type['requirement_label'] }}</option>
                                @endforeach
                            </select>
                            <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        </div>
                    </div>
                    <div class="px-5 py-2 border-b border-gray-50 bg-gray-50/50 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-medium text-gray-500" id="documentFilterCount" aria-live="polite"></p>
                        <button type="button" id="documentFilterReset" class="hidden text-[11px] font-semibold text-[#26488C] hover:underline">Reset</button>
                    </div>
                @endif

                <div class="divide-y divide-gray-100" id="documentAnalysisList">
                    @forelse($documentAnalyses as $analysis)
                        @php
                            $statusClasses = match ($analysis['status']) {
                                'completed' => 'bg-green-50 text-green-700 border-green-100',
                                'failed' => 'bg-red-50 text-red-700 border-red-100',
                                'processing' => 'bg-blue-50 text-blue-700 border-blue-100',
                                default => 'bg-amber-50 text-amber-700 border-amber-100',
                            };
                            $refresh = $analysis['summary_refresh'];
                            $searchTags = ($analysis['output_available'] && isset($analysis['details']['tags']) && is_array($analysis['details']['tags']))
                                ? implode(' ', $analysis['details']['tags'])
                                : '';
                            $searchDetected = ($analysis['output_available'] && isset($analysis['details']['detected_document_type']))
                                ? (string) $analysis['details']['detected_document_type']
                                : '';
                            $searchText = mb_strtolower(
                                $analysis['requirement_label'] . ' ' . $analysis['original_filename'] . ' ' . $searchDetected . ' ' . $searchTags,
                                'UTF-8'
                            );
                        @endphp
                        <article class="p-5" id="analysis-card-{{ $analysis['analysis_id'] }}" data-analysis-id="{{ $analysis['analysis_id'] }}" data-doc-field="{{ $analysis['document_field'] }}" data-search-text="{{ e($searchText) }}">
                            @php
                                $hasSecondAction = $analysis['allowed_actions']['view_summary'] || $analysis['allowed_actions']['retry'];
                            @endphp
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="w-9 h-9 shrink-0 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center">
                                    <i class="ph ph-file-text"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-bold text-gray-800">{{ $analysis['requirement_label'] }}</h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-semibold {{ $statusClasses }}" data-role="status">
                                            @if($analysis['status'] === 'processing')<i class="ph ph-spinner ph-spin mr-1"></i>@endif
                                            {{ $analysis['status_label'] }}
                                        </span>
                                    </div>
                                    <p class="text-xs font-medium text-gray-500 truncate mt-1" title="{{ $analysis['original_filename'] }}">
                                        {{ $analysis['original_filename'] }}
                                    </p>
                                    @if($analysis['status'] === 'pending' || $analysis['status'] === 'processing')
                                        <p class="mt-1.5 text-[11px] font-medium text-gray-500" data-role="state-message">
                                            {{ $analysis['status'] === 'pending' ? 'Pending automatic processing.' : 'Automatic processing is in progress.' }}
                                        </p>
                                    @elseif($analysis['status'] === 'failed')
                                        <p class="mt-1.5 text-xs font-medium text-red-700" data-role="failure-message">{{ $analysis['failure_text'] }}</p>
                                    @elseif($analysis['output_available'])
                                        @if($refresh['in_progress'])
                                            <p class="mt-1.5 text-[11px] font-medium text-blue-700" data-role="refresh-message">
                                                <i class="ph ph-spinner ph-spin mr-1"></i> Re-summarization is in progress. The current summary remains available.
                                            </p>
                                        @elseif($refresh['status'] === 'failed')
                                            <p class="mt-1.5 text-xs font-medium text-red-700" data-role="refresh-message">{{ $refresh['failure_text'] ?: 'Summary refresh failed. The current summary was retained.' }}</p>
                                        @elseif(!is_null($refresh['last_result_changed']))
                                            <p class="mt-1.5 text-[11px] font-medium text-gray-500" data-role="refresh-message">
                                                Latest re-summarization {{ $refresh['last_result_changed'] ? 'changed' : 'did not change' }} the current summary.
                                                @if($refresh['summary_processed_at']) Completed {{ \Illuminate\Support\Carbon::parse($refresh['summary_processed_at'])->format('M d, Y • h:i A') }}.@endif
                                            </p>
                                        @endif
                                    @endif
                                    <p class="hidden mt-2 text-xs" data-role="action-message" aria-live="polite"></p>
                                </div>
                            </div>
                            <div class="w-full lg:w-[280px] lg:shrink-0">
                                @if($hasSecondAction)
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" onclick="openFilePreview({{ $analysis['analysis_id'] }})" class="w-full justify-center px-2 py-2 bg-white border border-gray-200 text-gray-600 hover:text-[#26488C] hover:border-[#26488C] rounded-md text-[11px] font-semibold text-center transition-all">
                                            <i class="ph ph-eye mr-1"></i> View Document
                                        </button>
                                        @if($analysis['allowed_actions']['view_summary'])
                                            <button type="button" onclick="openAnalysisSurface({{ $analysis['analysis_id'] }}, 'summary')" class="w-full justify-center px-2 py-2 bg-blue-50 border border-blue-100 text-[#26488C] rounded-md text-[11px] font-semibold text-center hover:bg-blue-100 transition-all">
                                                View Summary
                                            </button>
                                        @elseif($analysis['allowed_actions']['retry'])
                                            <button type="button" onclick="retryDocument({{ $analysis['analysis_id'] }}, this)" class="w-full justify-center px-2 py-2 bg-red-600 text-white rounded-md text-[11px] font-semibold text-center hover:bg-red-700 transition-all">
                                                <i class="ph ph-arrow-clockwise mr-1"></i> Retry Processing
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <button type="button" onclick="openFilePreview({{ $analysis['analysis_id'] }})" class="w-full justify-center px-2 py-2 bg-white border border-gray-200 text-gray-600 hover:text-[#26488C] hover:border-[#26488C] rounded-md text-[11px] font-semibold text-center transition-all">
                                        <i class="ph ph-eye mr-1"></i> View Document
                                    </button>
                                @endif
                                @if($analysis['output_available'])
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button type="button" onclick="resummarizeDocument({{ $analysis['analysis_id'] }}, this)" @disabled($refresh['in_progress']) class="justify-center px-2 py-2 bg-[#26488C] text-white rounded-md text-[11px] font-semibold text-center hover:bg-[#1a3461] disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                                            @if($refresh['in_progress'])<i class="ph ph-spinner ph-spin mr-1"></i> Refreshing @else<i class="ph ph-magic-wand mr-1"></i> Re-summarize @endif
                                        </button>
                                        <button type="button" onclick="retagDocument({{ $analysis['analysis_id'] }}, this)" class="justify-center px-2 py-2 bg-[#26488C] text-white rounded-md text-[11px] font-semibold text-center hover:bg-[#1a3461] transition-all">
                                            <i class="ph ph-tag mr-1"></i> Re-tag
                                        </button>
                                    </div>
                                @endif
                            </div>
                            </div>
                        </article>
                    @empty
                        <div class="p-10 text-center text-gray-400">
                            <i class="ph ph-folder-open text-2xl mb-3"></i>
                            <p class="text-sm font-medium">No supported documents are associated with this application.</p>
                        </div>
                    @endforelse
                    <div id="documentFilterEmpty" class="hidden p-10 text-center text-gray-400">
                        <i class="ph ph-magnifying-glass text-2xl mb-3"></i>
                        <p class="text-sm font-semibold text-gray-500">No documents match your filter.</p>
                        <p class="text-[11px] mt-1">Try a different keyword or document type.</p>
                    </div>
                </div>
                <div id="documentPagination" class="hidden px-5 py-3 border-t border-gray-50 bg-white flex-col sm:flex-row items-center justify-between gap-2">
                    <p id="documentPageInfo" class="text-[11px] font-medium text-gray-500" aria-live="polite"></p>
                    <div id="documentPageButtons" class="flex items-center gap-1.5"></div>
                </div>
            </div>

            <!-- Officers List Card (Simplified for Tracking) -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 bg-gray-50/30">
                    <h3 class="font-semibold text-gray-800 text-[11px] flex items-center gap-2">
                        <i class="ph ph-users-three text-[#26488C]"></i> Registered Officers
                    </h3>
                </div>
                <div class="p-5 overflow-x-auto">
                    <table class="w-full">
                        <tbody id="officersTableBody" class="divide-y divide-gray-50">
                            @forelse($request->officers as $officer)
                                @php
                                    $position = $officer->temp_position;
                                    if (!$position && $request->organization_id) {
                                        $position = $officer->getPositionInOrganization($request->organization_id);
                                    }
                                    $position = $position ?? 'Officer';
                                @endphp
                                <tr data-officer-row>
                                    <td class="py-3 px-2 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-[10px] font-bold">
                                                {{ substr($officer->first_name, 0, 1) }}{{ substr($officer->last_name, 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-700 leading-none mb-1">{{ $officer->full_name }}</div>
                                                <div class="text-[11px] font-medium text-gray-400">{{ $officer->student_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-[#26488C]">
                                            {{ $position }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 whitespace-nowrap">
                                        <span class="text-[11px] font-medium text-gray-400">{{ $officer->email }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-400 italic text-sm">No officers registered in the system yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div id="officersPagination" class="hidden px-5 py-3 border-t border-gray-50 bg-white flex-col sm:flex-row items-center justify-between gap-2">
                    <p id="officersPageInfo" class="text-[11px] font-medium text-gray-500" aria-live="polite"></p>
                    <div id="officersPageButtons" class="flex items-center gap-1.5"></div>
                </div>
            </div>
        </div>

        <!-- Right Column (AI Insights & Actions) -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Persisted AI Status Panel -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden lg:sticky lg:top-6">
                <div class="p-5 bg-gradient-to-br from-[#26488C] to-[#1a3461] text-white">
                    <h3 class="font-semibold text-xs flex items-center gap-2 mb-2">
                        <i class="ph ph-brain text-[#C4973B]"></i> Document Intelligence
                    </h3>
                    <p class="text-[11px] text-white/75 leading-relaxed">
                        Persisted automatic-processing state for every uploaded physical file.
                    </p>
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="bg-white/10 rounded-lg p-3.5 border border-white/5">
                            <div class="text-[10px] font-medium text-white/50 mb-1">Current State</div>
                            <div class="text-sm font-bold">{{ $analysisState['label'] }}</div>
                        </div>
                        <div class="bg-white/10 rounded-lg p-3.5 border border-white/5">
                            <div class="text-[10px] font-medium text-white/50 mb-1">Output Ready</div>
                            <div class="text-sm font-bold">{{ $analysisState['output_available'] }}/{{ $analysisState['total'] }} Files</div>
                        </div>
                    </div>
                </div>

                <div class="p-5 bg-gray-50/30">
                    <div class="grid grid-cols-2 gap-3 text-center">
                        @foreach(['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed'] as $state => $label)
                            <div class="bg-white border border-gray-100 rounded-lg p-3">
                                <div class="text-base font-bold text-gray-800">{{ $analysisState['counts'][$state] }}</div>
                                <div class="text-[10px] font-medium text-gray-400 mt-0.5">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($request->status === 'Pending' || $request->status === 'For-Checking')
                    <div class="p-5 border-t border-gray-100">
                        <button onclick="approveRequest()" class="w-full py-3 bg-[#C4973B] text-white font-semibold text-xs rounded-lg hover:bg-[#a67d2d] transition-all shadow-sm hover:shadow-[#C4973B]/20 mb-2.5">
                            Approve Organization
                        </button>
                        <button onclick="rejectRequest()" class="w-full py-2.5 bg-white border border-red-100 text-red-500 font-semibold text-[11px] rounded-lg hover:bg-red-50 transition-all">
                            Decline & Request Correction
                        </button>
                    </div>
                @elseif($request->status === 'Rejected')
                    <div class="p-5 border-t border-gray-100">
                        <a href="{{ route('admin.renew.index') }}" class="w-full inline-flex justify-center items-center gap-2 py-3 bg-[#26488C] text-white font-semibold text-xs rounded-lg hover:bg-[#1a3461] transition-all shadow-sm">
                            <i class="ph ph-arrow-clockwise"></i> Resubmit Renewal
                        </a>
                        <p class="mt-2 text-[11px] text-center text-gray-400">Org can resubmit after correction</p>
                    </div>
                @endif
            </div>

            <!-- Admin Notes History -->
            @if(!empty($request->admin_notes))
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-800 text-[11px] mb-3 flex items-center gap-2">
                    <i class="ph ph-note text-[#C4973B]"></i> Reviewer Remarks
                </h3>
                <div class="bg-amber-50 p-4 rounded-lg border border-amber-100 italic text-sm text-amber-900 leading-relaxed">
                    "{{ $request->admin_notes }}"
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 modal-backdrop flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 transform scale-95 opacity-0 transition-all duration-300">
        <div class="p-6 bg-gradient-to-r from-[#26488C] to-[#1a3461] text-white">
            <h2 class="text-xl font-bold mb-1.5">Final Approval</h2>
            <p class="text-white/70 text-[11px] font-semibold">You are about to activate this organization</p>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-[11px] font-semibold text-gray-400 mb-1.5">Final Remarks (Optional)</label>
                <textarea id="approveNotes" class="w-full p-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all text-sm h-32" placeholder="Write any final comments for the organization..."></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeApproveModal()" class="flex-1 py-3 bg-gray-100 text-gray-500 font-semibold text-xs rounded-lg hover:bg-gray-200 transition-all">Cancel</button>
                <button onclick="confirmApprove()" id="confirmApproveBtn" class="flex-1 py-3 bg-[#26488C] text-white font-semibold text-xs rounded-lg hover:bg-[#1a3461] transition-all shadow-sm shadow-[#26488C]/20">Confirm Activation</button>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 modal-backdrop flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 transform scale-95 opacity-0 transition-all duration-300">
        <div class="p-6 bg-gradient-to-r from-red-600 to-red-800 text-white">
            <h2 class="text-xl font-bold mb-1.5">Decline Application</h2>
            <p class="text-white/70 text-[11px] font-semibold">Provide feedback to the organization</p>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-[11px] font-semibold text-gray-400 mb-1.5">Reason for Decline (Required)</label>
                <textarea id="rejectNotes" class="w-full p-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-500/10 focus:border-red-500 transition-all text-sm h-32" placeholder="Explain why the application is being declined or what needs to be corrected..."></textarea>
            </div>
            <div class="flex gap-3">
                <button onclick="closeRejectModal()" class="flex-1 py-3 bg-gray-100 text-gray-500 font-semibold text-xs rounded-lg hover:bg-gray-200 transition-all">Cancel</button>
                <button onclick="confirmReject()" id="confirmRejectBtn" class="flex-1 py-3 bg-red-600 text-white font-semibold text-xs rounded-lg hover:bg-red-700 transition-all shadow-sm shadow-red-600/20">Submit Rejection</button>
            </div>
        </div>
    </div>
</div>

@include('components.ai-progress-card')

<!-- File Preview Modal -->
<div id="filePreviewModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/80 modal-backdrop flex items-center justify-center p-4 lg:p-10">
    <div class="bg-white rounded-lg shadow-2xl w-full h-full max-w-6xl overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center">
                    <i class="ph ph-file-pdf"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900" id="previewFileName">Document Preview</h3>
                    <p class="text-[10px] text-gray-400 font-semibold">UniOrg Document Viewer</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="closeFilePreview()" class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-1 border-b border-gray-100 px-4 pt-2 bg-white">
            <button type="button" id="previewTabDoc" onclick="switchPreviewTab('doc')" class="rounded-t-md border-b-2 border-[#26488C] px-3 py-1.5 text-[11px] font-bold text-[#26488C]">Document</button>
            <button type="button" id="previewTabTags" onclick="switchPreviewTab('tags')" class="rounded-t-md border-b-2 border-transparent px-3 py-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600">Tags <span id="previewTagCount" class="ml-0.5 rounded-full bg-slate-100 px-1.5 text-[10px]">0</span></button>
        </div>
        <div class="flex-1 bg-gray-100 relative" id="previewDocPane">
            <iframe id="previewFrame" src="" class="w-full h-full border-none" onload="const loader = document.getElementById('previewLoader'); if (loader) loader.classList.add('hidden');"></iframe>
            <!-- Loading state -->
            <div id="previewLoader" class="absolute inset-0 flex items-center justify-center bg-gray-50">
                 <div class="w-12 h-12 border-4 border-[#26488C] border-t-transparent rounded-full animate-spin"></div>
            </div>
        </div>
        <div id="previewTagsPane" class="hidden flex-1 overflow-y-auto bg-white p-5">
            <div id="previewTagsContent"></div>
        </div>
    </div>
</div>

<!-- Persisted AI Output Modal -->
<div id="aiAnalysisModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 modal-backdrop flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="aiModalTitle">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300">
        <div class="p-5 bg-gradient-to-br from-[#26488C] to-[#1a3461] text-white flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <i class="ph ph-brain text-[#C4973B]"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold" id="aiModalTitle">AI-generated Document Information</h3>
                </div>
            </div>
            <button type="button" onclick="closeAIModal()" class="text-white/60 hover:text-white transition-colors" aria-label="Close">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <div class="p-5 max-h-[70vh] overflow-y-auto" id="aiModalContent" aria-live="polite"></div>
        <div class="p-5 border-t border-gray-50 bg-gray-50/50 flex justify-end">
            <button type="button" onclick="closeAIModal()" class="px-5 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                Close
            </button>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .modal-open {
        overflow: hidden;
    }

    #approveModal > div, #rejectModal > div {
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
</style>

<script>
const documentAnalyses = new Map(
    @json($documentAnalyses).map(analysis => [Number(analysis.analysis_id), analysis])
);
const csrfToken = @js(csrf_token());
const pollingAttempts = new Map();
let activeAnalysisId = null;
let activeAnalysisSurface = 'summary';
let activePreviewAnalysisId = null;

const documentActionToast = typeof window.showToast === 'function'
    ? window.showToast.bind(window)
    : () => {};
const showToast = (message, type = 'info', title = 'Document Processing') => {
    documentActionToast(message, type, title);
};

// Document Requirements filter + search (client-side; display only).
function buildCardSearchText(analysis) {
    const tags = (analysis && analysis.details && Array.isArray(analysis.details.tags))
        ? analysis.details.tags.join(' ')
        : '';
    const detected = (analysis && analysis.details && analysis.details.detected_document_type)
        ? String(analysis.details.detected_document_type)
        : '';
    return `${analysis?.requirement_label ?? ''} ${analysis?.original_filename ?? ''} ${detected} ${tags}`.toLowerCase();
}

function syncCardSearchText(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    const card = document.getElementById(`analysis-card-${Number(analysisId)}`);
    if (!analysis || !card) return;
    card.dataset.searchText = buildCardSearchText(analysis);
}

const DOCS_PER_PAGE = 10;
let docCurrentPage = 1;
let docFilteredCards = [];

function paginationButtonClasses(active, disabled) {
    const base = 'min-w-[28px] h-7 px-2 inline-flex items-center justify-center rounded-md border text-[11px] font-semibold transition-all ';
    if (disabled) return base + 'bg-gray-50 border-gray-100 text-gray-300 cursor-not-allowed';
    if (active) return base + 'bg-[#26488C] border-[#26488C] text-white shadow-sm';
    return base + 'bg-white border-gray-200 text-gray-600 hover:text-[#26488C] hover:border-[#26488C]';
}

function pageWindow(current, total, maxButtons = 5) {
    if (total <= maxButtons) return Array.from({ length: total }, (_, i) => i + 1);
    let start = Math.max(1, Math.min(current - Math.floor(maxButtons / 2), total - maxButtons + 1));
    return Array.from({ length: maxButtons }, (_, i) => start + i);
}

function renderDocumentPaginationControls(totalFiltered) {
    const wrap = document.getElementById('documentPagination');
    const btns = document.getElementById('documentPageButtons');
    if (!wrap || !btns) return;
    const totalPages = Math.ceil(totalFiltered / DOCS_PER_PAGE);

    if (totalPages <= 1) {
        wrap.classList.add('hidden');
        wrap.classList.remove('flex');
        btns.innerHTML = '';
        return;
    }

    wrap.classList.remove('hidden');
    wrap.classList.add('flex');
    btns.innerHTML = '';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.innerHTML = '<i class="ph ph-caret-left text-xs"></i>';
    prev.setAttribute('aria-label', 'Previous documents page');
    prev.className = paginationButtonClasses(false, docCurrentPage === 1);
    prev.disabled = docCurrentPage === 1;
    prev.onclick = () => goToDocumentPage(docCurrentPage - 1);
    btns.appendChild(prev);

    pageWindow(docCurrentPage, totalPages).forEach((page) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = page;
        b.className = paginationButtonClasses(page === docCurrentPage, false);
        b.onclick = () => goToDocumentPage(page);
        btns.appendChild(b);
    });

    const next = document.createElement('button');
    next.type = 'button';
    next.innerHTML = '<i class="ph ph-caret-right text-xs"></i>';
    next.setAttribute('aria-label', 'Next documents page');
    next.className = paginationButtonClasses(false, docCurrentPage === totalPages);
    next.disabled = docCurrentPage === totalPages;
    next.onclick = () => goToDocumentPage(docCurrentPage + 1);
    btns.appendChild(next);
}

function renderDocumentPage() {
    const cards = document.querySelectorAll('#documentAnalysisList article[data-analysis-id]');
    const totalFiltered = docFilteredCards.length;
    const totalPages = Math.max(1, Math.ceil(totalFiltered / DOCS_PER_PAGE));
    if (docCurrentPage > totalPages) docCurrentPage = totalPages;
    if (docCurrentPage < 1) docCurrentPage = 1;

    cards.forEach((card) => card.classList.add('hidden'));

    const start = (docCurrentPage - 1) * DOCS_PER_PAGE;
    const pageCards = docFilteredCards.slice(start, start + DOCS_PER_PAGE);
    pageCards.forEach((card) => card.classList.remove('hidden'));

    const emptyState = document.getElementById('documentFilterEmpty');
    if (emptyState) emptyState.classList.toggle('hidden', totalFiltered !== 0 || cards.length === 0);

    const countEl = document.getElementById('documentFilterCount');
    if (countEl) {
        if (totalFiltered === 0) {
            countEl.textContent = cards.length === 0 ? 'No documents' : 'No documents match your filter.';
        } else {
            const from = start + 1;
            const to = Math.min(start + DOCS_PER_PAGE, totalFiltered);
            countEl.textContent = `Showing ${from}–${to} of ${totalFiltered} documents`;
        }
    }

    const infoEl = document.getElementById('documentPageInfo');
    if (infoEl) {
        infoEl.textContent = totalFiltered === 0
            ? ''
            : `Page ${docCurrentPage} of ${totalPages}`;
    }

    renderDocumentPaginationControls(totalFiltered);
}

function goToDocumentPage(page) {
    const totalPages = Math.max(1, Math.ceil(docFilteredCards.length / DOCS_PER_PAGE));
    docCurrentPage = Math.min(Math.max(1, page), totalPages);
    renderDocumentPage();
}

function applyDocumentFilters(resetPage = true) {
    const searchInput = document.getElementById('documentSearchInput');
    const typeFilter = document.getElementById('documentTypeFilter');
    if (!searchInput && !typeFilter) {
        docFilteredCards = Array.from(document.querySelectorAll('#documentAnalysisList article[data-analysis-id]'));
        docCurrentPage = 1;
        renderDocumentPage();
        return;
    }

    const query = (searchInput?.value ?? '').trim().toLowerCase();
    const type = typeFilter?.value ?? '';
    const cards = Array.from(document.querySelectorAll('#documentAnalysisList article[data-analysis-id]'));

    docFilteredCards = cards.filter((card) => {
        const matchesType = !type || card.dataset.docField === type;
        const matchesQuery = !query || (card.dataset.searchText || '').toLowerCase().includes(query);
        return matchesType && matchesQuery;
    });

    if (resetPage) {
        docCurrentPage = 1;
    } else {
        const totalPages = Math.max(1, Math.ceil(docFilteredCards.length / DOCS_PER_PAGE));
        if (docCurrentPage > totalPages) docCurrentPage = totalPages;
    }

    renderDocumentPage();

    const clearBtn = document.getElementById('documentSearchClear');
    if (clearBtn) {
        const hasQuery = (searchInput?.value ?? '').length > 0;
        clearBtn.classList.toggle('hidden', !hasQuery);
        clearBtn.classList.toggle('flex', hasQuery);
    }

    const resetBtn = document.getElementById('documentFilterReset');
    if (resetBtn) resetBtn.classList.toggle('hidden', !(query || type));
}

function resetDocumentFilters() {
    const searchInput = document.getElementById('documentSearchInput');
    const typeFilter = document.getElementById('documentTypeFilter');
    if (searchInput) searchInput.value = '';
    if (typeFilter) typeFilter.value = '';
    applyDocumentFilters(true);
}

// Registered Officers pagination (client-side, 10 per page, independent from documents).
const OFFICERS_PER_PAGE = 10;
let officersCurrentPage = 1;

function renderOfficersPaginationControls(totalRows) {
    const wrap = document.getElementById('officersPagination');
    const btns = document.getElementById('officersPageButtons');
    if (!wrap || !btns) return;
    const totalPages = Math.ceil(totalRows / OFFICERS_PER_PAGE);

    if (totalPages <= 1) {
        wrap.classList.add('hidden');
        wrap.classList.remove('flex');
        btns.innerHTML = '';
        return;
    }

    wrap.classList.remove('hidden');
    wrap.classList.add('flex');
    btns.innerHTML = '';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.innerHTML = '<i class="ph ph-caret-left text-xs"></i>';
    prev.setAttribute('aria-label', 'Previous officers page');
    prev.className = paginationButtonClasses(false, officersCurrentPage === 1);
    prev.disabled = officersCurrentPage === 1;
    prev.onclick = () => goToOfficersPage(officersCurrentPage - 1);
    btns.appendChild(prev);

    pageWindow(officersCurrentPage, totalPages).forEach((page) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = page;
        b.className = paginationButtonClasses(page === officersCurrentPage, false);
        b.onclick = () => goToOfficersPage(page);
        btns.appendChild(b);
    });

    const next = document.createElement('button');
    next.type = 'button';
    next.innerHTML = '<i class="ph ph-caret-right text-xs"></i>';
    next.setAttribute('aria-label', 'Next officers page');
    next.className = paginationButtonClasses(false, officersCurrentPage === totalPages);
    next.disabled = officersCurrentPage === totalPages;
    next.onclick = () => goToOfficersPage(officersCurrentPage + 1);
    btns.appendChild(next);
}

function renderOfficersPage() {
    const rows = Array.from(document.querySelectorAll('#officersTableBody tr[data-officer-row]'));
    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / OFFICERS_PER_PAGE));
    if (officersCurrentPage > totalPages) officersCurrentPage = totalPages;
    if (officersCurrentPage < 1) officersCurrentPage = 1;

    rows.forEach((row) => row.classList.add('hidden'));

    const start = (officersCurrentPage - 1) * OFFICERS_PER_PAGE;
    rows.slice(start, start + OFFICERS_PER_PAGE).forEach((row) => row.classList.remove('hidden'));

    const infoEl = document.getElementById('officersPageInfo');
    if (infoEl) {
        infoEl.textContent = total === 0
            ? ''
            : `Showing ${start + 1}–${Math.min(start + OFFICERS_PER_PAGE, total)} of ${total} officers • Page ${officersCurrentPage} of ${totalPages}`;
    }

    renderOfficersPaginationControls(total);
}

function goToOfficersPage(page) {
    const rows = document.querySelectorAll('#officersTableBody tr[data-officer-row]');
    const totalPages = Math.max(1, Math.ceil(rows.length / OFFICERS_PER_PAGE));
    officersCurrentPage = Math.min(Math.max(1, page), totalPages);
    renderOfficersPage();
}

let documentFilterDebounce = null;
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('documentSearchInput');
    const typeFilter = document.getElementById('documentTypeFilter');

    searchInput?.addEventListener('input', () => {
        clearTimeout(documentFilterDebounce);
        documentFilterDebounce = setTimeout(() => applyDocumentFilters(true), 150);
    });
    typeFilter?.addEventListener('change', () => applyDocumentFilters(true));
    document.getElementById('documentSearchClear')?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        applyDocumentFilters(true);
        searchInput?.focus();
    });
    document.getElementById('documentFilterReset')?.addEventListener('click', resetDocumentFilters);
    applyDocumentFilters(true);
    renderOfficersPage();
});

// Modal Handlers
function approveRequest() {
    const modal = document.getElementById('approveModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.querySelector('div').classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeApproveModal() {
    const modal = document.getElementById('approveModal');
    modal.querySelector('div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function rejectRequest() {
    const modal = document.getElementById('rejectModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.querySelector('div').classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    modal.querySelector('div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// File Preview Handlers
function openFilePreview(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis) return;
    activePreviewAnalysisId = Number(analysisId);
    const modal = document.getElementById('filePreviewModal');
    const frame = document.getElementById('previewFrame');
    const loader = document.getElementById('previewLoader');
    const nameEl = document.getElementById('previewFileName');

    nameEl.innerText = analysis.original_filename;
    renderPreviewTags(analysis);
    switchPreviewTab('doc');
    loader.classList.remove('hidden');
    frame.src = analysis.urls.source;

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.querySelector('div').classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function renderPreviewTags(analysis) {
    const tags = (analysis.details && Array.isArray(analysis.details.tags)) ? analysis.details.tags : [];
    document.getElementById('previewTagCount').textContent = tags.length;
    const content = document.getElementById('previewTagsContent');
    if (!analysis.output_available || tags.length === 0) {
        content.innerHTML = '<div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-8 text-center"><i class="ph ph-tag text-2xl text-slate-300"></i><p class="mt-2 text-xs font-semibold text-slate-500">No tags yet.</p><p class="mt-0.5 text-[11px] text-slate-400">Tags will appear once automatic processing finishes.</p></div>';
        return;
    }
    const detected = analysis.details && analysis.details.detected_document_type
        ? `<p class="mb-3 text-[11px] text-slate-500">Detected type: <span class="font-bold text-slate-700">${escapeHtml(analysis.details.detected_document_type)}</span></p>`
        : '';
    content.innerHTML = detected + '<div class="flex flex-wrap gap-1.5">' + tags.map(tag =>
        `<span class="inline-flex items-center gap-1 rounded-md border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-[#26488C]"><i class="ph ph-tag text-[10px]"></i>${escapeHtml(tag)}</span>`
    ).join('') + '</div>';
}

function switchPreviewTab(which) {
    const docBtn = document.getElementById('previewTabDoc');
    const tagsBtn = document.getElementById('previewTabTags');
    const active = 'rounded-t-md border-b-2 border-[#26488C] px-3 py-1.5 text-[11px] font-bold text-[#26488C]';
    const idle = 'rounded-t-md border-b-2 border-transparent px-3 py-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600';
    docBtn.className = which === 'doc' ? active : idle;
    tagsBtn.className = which === 'tags' ? active : idle;
    document.getElementById('previewDocPane').classList.toggle('hidden', which !== 'doc');
    document.getElementById('previewTagsPane').classList.toggle('hidden', which !== 'tags');
}

function closeFilePreview() {
    const modal = document.getElementById('filePreviewModal');
    const frame = document.getElementById('previewFrame');
    
    modal.querySelector('div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        frame.src = '';
    }, 300);
}

// Persisted analysis surfaces and lifecycle actions
function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatTimestamp(value) {
    if (!value) return 'Not available';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

function openAnalysisSurface(analysisId, surface) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis || !analysis.output_available) return;

    activeAnalysisId = Number(analysisId);
    activeAnalysisSurface = surface;
    renderAnalysisSurface(analysis, surface);

    const modal = document.getElementById('aiAnalysisModal');
    modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
    setTimeout(() => modal.querySelector(':scope > div').classList.remove('scale-95', 'opacity-0'), 10);
}

function renderAnalysisSurface(analysis, surface) {
    const title = document.getElementById('aiModalTitle');
    const content = document.getElementById('aiModalContent');
    title.textContent = `${analysis.requirement_label} — Summary`;

    const output = analysis.advisory_output || {};
    const refresh = analysis.summary_refresh || {};
    let refreshHtml = '';
    if (refresh.in_progress) {
        refreshHtml = '<div class="px-3.5 py-2.5 mb-4 rounded-lg bg-blue-50 border border-blue-100 text-blue-800 text-xs font-medium"><i class="ph ph-spinner ph-spin mr-2"></i>Re-summarization is in progress. The current summary remains available.</div>';
    } else if (refresh.status === 'failed') {
        refreshHtml = `<div class="px-3.5 py-2.5 mb-4 rounded-lg bg-red-50 border border-red-100 text-red-700 text-xs font-medium">${escapeHtml(refresh.failure_text || 'Summary refresh failed. The current summary was retained.')}</div>`;
    } else if (refresh.summary_processed_at) {
        refreshHtml = `<div class="px-3.5 py-2.5 mb-4 rounded-lg bg-gray-50 border border-gray-100 text-gray-600 text-xs font-medium"><i class="ph ph-clock mr-1.5"></i>Summary updated on ${escapeHtml(formatTimestamp(refresh.summary_processed_at))}.</div>`;
    }

    content.innerHTML = `
        ${refreshHtml}
        <section class="p-5 bg-blue-50 rounded-lg border border-blue-100">
            <h4 class="text-[11px] font-semibold text-blue-900 mb-3"><i class="ph ph-quotes text-blue-300 mr-2"></i>AI-generated Summary</h4>
            <p class="text-sm text-blue-900 leading-relaxed whitespace-pre-wrap">${escapeHtml(output.summary || 'No summary is available.')}</p>
        </section>`;
}

function closeAIModal() {
    const modal = document.getElementById('aiAnalysisModal');
    modal.querySelector(':scope > div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
        activeAnalysisId = null;
    }, 300);
}

function setActionMessage(analysisId, message, isError = false) {
    const element = document.querySelector(`#analysis-card-${analysisId} [data-role="action-message"]`);
    if (!element) return;
    element.textContent = message;
    element.classList.remove('hidden', 'text-red-700', 'text-blue-700', 'text-green-700');
    element.classList.add(isError ? 'text-red-700' : 'text-blue-700');
}

async function postAnalysisAction(analysisId, action, button) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis) return null;
    const url = analysis.urls[action];
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Requesting...';

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '{}'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = data.message || `The request failed (${response.status}).`;
            setActionMessage(analysisId, message, true);
            showToast(message, response.status === 409 ? 'warning' : 'error', response.status === 409 ? 'Request Conflict' : 'Request Failed');
            await refreshPersistedStatus(analysisId).catch(() => null);
            return null;
        }
        return data;
    } catch (error) {
        const message = 'The processing request could not be sent. Please check your connection and try again.';
        setActionMessage(analysisId, message, true);
        showToast(message, 'error', 'Connection Error');
        return null;
    } finally {
        if (!pollingAttempts.has(Number(analysisId))) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }
}

async function retryDocument(analysisId, button) {
    const data = await postAnalysisAction(analysisId, 'retry', button);
    if (!data) return;
    pollingAttempts.set(Number(analysisId), 0);
    button.disabled = true;
    button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Processing...';
    setActionMessage(analysisId, 'Retry queued. Waiting for persisted processing status...');
    showToast(data.message || 'Document processing retry was queued.', 'success');
    pollPersistedStatus(analysisId, 'retry');
}

async function resummarizeDocument(analysisId, button) {
    const data = await postAnalysisAction(analysisId, 'resummarize', button);
    if (!data) { failAiProgress(); return; }
    const analysis = documentAnalyses.get(Number(analysisId));
    analysis.summary_refresh = {
        ...(analysis.summary_refresh || {}),
        status: 'processing',
        in_progress: true,
        failure_text: null,
        last_result_changed: null
    };
    pollingAttempts.set(Number(analysisId), 0);
    button.disabled = true;
    button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Refreshing Summary';
    setActionMessage(analysisId, 'Re-summarization queued. The current summary remains available.');
    if (activeAnalysisId === Number(analysisId)) renderAnalysisSurface(analysis, activeAnalysisSurface);
    showToast(data.message || 'Document re-summarization was queued.', 'success');
    startAiProgress('Re-summarizing document', analysis.requirement_label || '');
    pollPersistedStatus(analysisId, 'resummarize');
}

async function retagDocument(analysisId, button) {
    const data = await postAnalysisAction(analysisId, 'retag', button);
    if (!data) return;
    setActionMessage(analysisId, 'Tag refresh queued. Previous tags stay until the new tags arrive.');
    showToast(data.message || 'Document tag refresh was queued.', 'success');
}

async function refreshPersistedStatus(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    const response = await fetch(analysis.urls.status, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
    });
    if (!response.ok) throw new Error(`Status refresh failed (${response.status}).`);
    return response.json();
}

async function pollPersistedStatus(analysisId, operation) {
    const id = Number(analysisId);
    const attempt = (pollingAttempts.get(id) || 0) + 1;
    pollingAttempts.set(id, attempt);

    try {
        const status = await refreshPersistedStatus(id);
        const stillActive = operation === 'retry'
            ? ['pending', 'processing'].includes(status.status)
            : Boolean(status.resummarization?.in_progress);

        if (!stillActive) {
            pollingAttempts.delete(id);
            if (operation === 'retry') {
                showToast(status.status === 'completed' ? 'Document processing completed.' : (status.failure_message || 'Document processing failed.'), status.status === 'completed' ? 'success' : 'error');
                await window.refreshCurrentOsasPage?.();
                return;
            }

            const analysis = documentAnalyses.get(id);
            const refresh = status.resummarization || {};
            const changed = refresh.last_result_changed;
            const failed = refresh.status === 'failed';
            const message = refresh.failure_message
                || (changed ? 'The current summary changed.' : 'The current summary did not change.');

            analysis.summary_refresh = {
                ...(analysis.summary_refresh || {}),
                status: refresh.status,
                in_progress: false,
                failure_text: refresh.failure_message || null,
                last_result_changed: changed,
                summary_version: refresh.summary_version,
                summary_processed_at: refresh.summary_processed_at
            };

            try {
                const response = await fetch(analysis.urls.summary, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (response.ok) {
                    const persisted = await response.json();
                    analysis.advisory_output = {
                        ai_generated: true,
                        advisory: persisted.advisory,
                        summary: persisted.summary,
                        context: persisted.context || {}
                    };
                }
            } catch (error) {
                // The existing persisted summary remains in memory and visible.
            }

            setActionMessage(id, message, failed);
            if (failed) failAiProgress(message);
            else finishAiProgress('Summary refreshed.');
            const actionButton = Array.from(document.querySelectorAll(`#analysis-card-${id} button`))
                .find(candidate => candidate.getAttribute('onclick')?.startsWith('resummarizeDocument'));
            if (actionButton) {
                actionButton.disabled = false;
                actionButton.innerHTML = '<i class="ph ph-magic-wand mr-1"></i> Re-summarize';
            }
            if (activeAnalysisId === id) renderAnalysisSurface(analysis, activeAnalysisSurface);
            showToast(message, failed ? 'error' : 'success', 'Summary Refresh');
            return;
        }

        if (attempt >= 90) {
            pollingAttempts.delete(id);
            setActionMessage(id, 'Processing is still active. Refresh the page later to see the persisted result.', true);
            showToast('Processing is still active. Refresh the page later for the persisted result.', 'warning');
            waitAiProgress('Taking longer than usual — it will finish automatically in the background.');
            return;
        }
        setTimeout(() => pollPersistedStatus(id, operation), 2000);
    } catch (error) {
        pollingAttempts.delete(id);
        setActionMessage(id, 'Could not refresh the persisted processing status. Your request may still be running.', true);
        showToast('Could not refresh processing status. Refresh the page later.', 'error', 'Status Refresh Failed');
        failAiProgress('Could not reach the server. Your request may still be running.');
    }
}

const tagPollers = {};
async function retagDocument(analysisId, button) {
    const data = await postAnalysisAction(analysisId, 'retag', button);
    if (!data) { failAiProgress(); return; }
    const analysis = documentAnalyses.get(Number(analysisId));
    startAiProgress('Refreshing tags', analysis ? analysis.requirement_label : '');
    setActionMessage(analysisId, 'Tag refresh queued. Previous tags stay until the new tags arrive.');
    showToast(data.message || 'Document tag refresh was queued.', 'success');
    pollTagRefresh(analysisId, data.tags_updated_at || null);
}

function pollTagRefresh(analysisId, baseline) {
    const id = Number(analysisId);
    const key = 'retag:' + id;
    if (tagPollers[key]) clearInterval(tagPollers[key]);
    let tries = 0;
    tagPollers[key] = setInterval(async () => {
        tries++;
        try {
            const status = await refreshPersistedStatus(id);
            if (!!status.updated_at && !!baseline && status.updated_at !== baseline) {
                clearInterval(tagPollers[key]);
                await refreshAnalysisTags(id);
                finishAiProgress('Tags refreshed.');
                setActionMessage(id, 'Tags refreshed and updated below.');
                return;
            }
        } catch (error) { /* keep polling until the timeout below */ }
        if (tries >= 60) {
            clearInterval(tagPollers[key]);
            waitAiProgress('Taking longer than usual — it will finish automatically in the background.');
        }
    }, 3000);
}

async function refreshAnalysisTags(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis || !analysis.urls.details) return;
    try {
        const response = await fetch(analysis.urls.details, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const details = await response.json().catch(() => null);
        if (!details || !Array.isArray(details.tags)) return;
        analysis.details = { ...(analysis.details || {}), tags: details.tags, detected_document_type: details.detected_document_type ?? (analysis.details || {}).detected_document_type };
        if (activePreviewAnalysisId === Number(analysisId)) renderPreviewTags(analysis);
        syncCardSearchText(analysisId);
        applyDocumentFilters(false);
    } catch (error) { /* old tags stay; the card message already confirms the refresh */ }
}

async function confirmApprove() {
    const btn = document.getElementById('confirmApproveBtn');
    const notes = document.getElementById('approveNotes').value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Activating...';
    
    try {
        const response = await fetch('{{ route("osas.apply-org.approve", $request) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ admin_notes: notes })
        });

        const data = await response.json();
        if (data.success) {
            window.showToast('Organization has been successfully activated!', 'success', 'Approval Complete');
            const pageRefreshed = await window.refreshCurrentOsasPage();
            if (!pageRefreshed) {
                btn.disabled = false;
                btn.innerHTML = 'Confirm Activation';
            }
        } else {
            window.showToast(data.message, 'error', 'Approval Error');
            btn.disabled = false;
            btn.innerHTML = 'Confirm Activation';
        }
    } catch (error) {
        console.error(error);
        btn.disabled = false;
        btn.innerHTML = 'Confirm Activation';
    }
}

async function confirmReject() {
    const btn = document.getElementById('confirmRejectBtn');
    const notes = document.getElementById('rejectNotes').value;
    
    if (!notes.trim()) {
        window.showToast('Please provide a reason for rejection', 'error');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Rejecting...';
    
    try {
        const response = await fetch('{{ route("osas.apply-org.reject", $request) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: notes })
        });

        const data = await response.json();
        if (data.success) {
            window.showToast('Application has been declined and feedback sent.', 'success', 'Review Submitted');
            const pageRefreshed = await window.refreshCurrentOsasPage();
            if (!pageRefreshed) {
                btn.disabled = false;
                btn.innerHTML = 'Submit Rejection';
            }
        } else {
            window.showToast(data.message, 'error', 'Submission Error');
            btn.disabled = false;
            btn.innerHTML = 'Submit Rejection';
        }
    } catch (error) {
        console.error(error);
        btn.disabled = false;
        btn.innerHTML = 'Submit Rejection';
    }
}

// Real-time document processing status via Pusher/Echo
if (typeof window.Echo !== 'undefined') {
    const applyOrgId = @js($request->id);

    window.Echo.channel(`osas-application.${applyOrgId}`)
        .listen('.document-analysis.status-updated', (e) => {
            console.log('📡 Document status update received:', e);

            const analysisId = Number(e.analysis_id);
            const analysis = documentAnalyses.get(analysisId);
            if (!analysis) return;

            // Update the in-memory map
            analysis.status = e.status;
            if (e.error_message) analysis.failure_text = e.error_message;

            // Update the status badge on the card
            const card = document.getElementById(`analysis-card-${analysisId}`);
            if (!card) return;

            const badge = card.querySelector('[data-role="status-badge"]');
            const actionMsg = card.querySelector('[data-role="action-message"]');

            if (badge) {
                const labels = {
                    pending:    { text: 'Pending',    cls: 'bg-yellow-100 text-yellow-800' },
                    processing: { text: 'Processing', cls: 'bg-blue-100 text-blue-800 animate-pulse' },
                    completed:  { text: 'Completed',  cls: 'bg-green-100 text-green-800' },
                    failed:     { text: 'Failed',     cls: 'bg-red-100 text-red-800' },
                };
                const info = labels[e.status] || { text: e.status, cls: 'bg-gray-100 text-gray-700' };
                badge.textContent = info.text;
                badge.className = badge.className.replace(/bg-\S+|text-\S+|animate-pulse/g, '').trim();
                badge.classList.add(...info.cls.split(' '));
            }

            if (e.status === 'completed') {
                if (actionMsg) {
                    actionMsg.textContent = 'Processing complete.';
                    actionMsg.classList.remove('hidden', 'text-red-700');
                    actionMsg.classList.add('text-green-700');
                }
                showToast('Document processed successfully.', 'success', 'Processing Complete');
                // Stop any active poller for this analysis
                pollingAttempts.delete(analysisId);
            } else if (e.status === 'failed') {
                if (actionMsg) {
                    actionMsg.textContent = e.error_message || 'Processing failed.';
                    actionMsg.classList.remove('hidden', 'text-green-700');
                    actionMsg.classList.add('text-red-700');
                }
                showToast(e.error_message || 'Document processing failed.', 'error', 'Processing Failed');
                pollingAttempts.delete(analysisId);
            } else if (e.status === 'processing') {
                if (actionMsg) {
                    actionMsg.textContent = 'Processing…';
                    actionMsg.classList.remove('hidden', 'text-red-700', 'text-green-700');
                    actionMsg.classList.add('text-blue-700');
                }
            }
        });

    console.log(`✅ Listening on osas-application.${applyOrgId} for document analysis updates`);
} else {
    console.warn('Echo not available — document status updates will require manual refresh.');
}

</script>
@endsection