@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', $event->title . ' - Event Details')

@php
    $referer = request()->header('referer');
    $backText = 'Back to Proposals';
    $backRoute = route('osas.proposals.index');
    
    if ($referer && str_contains($referer, route('osas.calendar'))) {
        $backText = 'Back to Calendar';
        $backRoute = route('osas.calendar');
    }

    $org = $event->organization;
    $proposal = $event->proposal;
    $hasLogo = $org && $org->settings && $org->settings->getRawOriginal('logo_url');
    $primaryColor = $org?->settings?->primary_color ?? '#26488C';
@endphp

@section('content')
<div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-6" x-data="osasProposalDetail()">
    <!-- Top Navigation Bar / Breadcrumbs -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ $backRoute }}" data-osas-navigation
               class="h-9 px-3.5 rounded-lg bg-white border border-gray-200 shadow-sm flex items-center gap-2 text-gray-700 hover:text-[#26488C] hover:border-[#26488C]/30 text-xs font-black uppercase tracking-wider transition-all">
                <i class="ph ph-arrow-left text-sm"></i>
                <span>{{ $backText }}</span>
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-xs font-bold text-gray-500 truncate max-w-xs md:max-w-md">{{ $event->title }}</span>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('osas.calendar') }}" data-osas-navigation
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                <i class="ph ph-calendar text-[#26488C]"></i>
                <span>Master Calendar</span>
            </a>
            @if($org)
            <button type="button" onclick="window.openOsasChatWithOrg && window.openOsasChatWithOrg({{ $org->id }})"
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-[#26488C] text-white font-bold text-xs rounded-lg hover:bg-[#1a3461] transition-all shadow-sm">
                <i class="ph ph-paper-plane-tilt"></i>
                <span>Message Org</span>
            </button>
            @endif
        </div>
    </div>

    <!-- Organization & Status Header Banner -->
    <div class="bg-white rounded-xl border border-gray-100 p-5 mb-6 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-4">
                @if($hasLogo)
                    <div class="h-14 w-14 shrink-0 rounded-xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden shadow-sm">
                        <img src="{{ $org->settings->logo_url }}" alt="{{ $org->name }} Logo" class="w-full h-full object-cover">
                    </div>
                @else
                    <div class="h-14 w-14 shrink-0 rounded-xl flex items-center justify-center text-white font-black text-xl shadow-sm"
                         style="background: linear-gradient(135deg, {{ $primaryColor }}, #1a3461);">
                        {{ substr($org->name ?? 'U', 0, 1) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight leading-tight">{{ $event->title }}</h1>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap mt-1 text-xs font-semibold text-gray-500">
                        @if($org)
                            <a href="{{ route('osas.organizations.show', $org) }}" data-osas-navigation
                               class="text-[#26488C] hover:underline font-bold">
                                {{ $org->name }} ({{ $org->org_acronym ?? 'ORG' }})
                            </a>
                            <span class="text-gray-300">•</span>
                            <span>{{ $org->type ?? 'Organization' }}</span>
                            @if($org->department)
                                <span class="text-gray-300">•</span>
                                <span>{{ $org->department->name }}</span>
                            @endif
                        @else
                            <span>Unknown Organization</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Status Badges -->
            <div class="flex items-center gap-2 flex-wrap lg:self-center">
                @if($event->osas_status === 'pending')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="ph ph-clock font-bold"></i> OSAS Pending Review
                    </span>
                @elseif($event->osas_status === 'approved' && !$event->hardcopy_received_at)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Approved (Awaiting Hardcopy)
                    </span>
                @elseif($event->osas_status === 'approved' && $event->hardcopy_received_at)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <i class="ph ph-check-circle-fill text-emerald-600"></i> OSAS Approved & Hardcopy Received
                    </span>
                @elseif($event->osas_status === 'rejected')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                        <i class="ph ph-x-circle-fill text-red-600"></i> OSAS Rejected
                    </span>
                @endif

                @if($proposal?->reschedule_status === 'pending')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="ph ph-calendar-blank font-bold"></i> Reschedule Requested
                    </span>
                @elseif($proposal?->reschedule_status === 'approved')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-blue-100 text-blue-800 border border-blue-200">
                        <i class="ph ph-calendar-check-fill text-blue-600"></i> Reschedule Approved
                    </span>
                @elseif($proposal?->reschedule_status === 'rejected')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                        <i class="ph ph-calendar-x-fill text-red-600"></i> Reschedule Rejected
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Main 2-Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <!-- Left Column: Media & Documents (5 cols) -->
        <div class="lg:col-span-5 space-y-5">
            <!-- Event Image Carousel Card -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm"
                 x-data="{ 
                    currentImage: 0, 
                    images: {{ json_encode($event->images ?? []) }} 
                 }">
                <div class="px-5 py-3 border-b border-gray-50 bg-gray-50/40 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-image text-[#26488C]"></i> Event Media
                    </h3>
                    <template x-if="images && images.length > 1">
                        <span class="text-[11px] font-bold text-gray-500 bg-white px-2 py-0.5 rounded-full border border-gray-200"
                              x-text="`${currentImage + 1} of ${images.length}`"></span>
                    </template>
                </div>

                <div class="relative w-full aspect-video bg-gray-950 overflow-hidden group flex items-center justify-center cursor-pointer" @click="openLightbox(currentImage)">
                    @if($event->images && count($event->images) > 0)
                        @foreach($event->images as $index => $image)
                            <div x-show="currentImage === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="w-full h-full flex items-center justify-center">
                                <img src="{{ storage_url($image) }}" alt="{{ $event->title }}" class="w-full h-full object-contain">
                            </div>
                        @endforeach

                        <!-- Hover overlay -->
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-300 flex items-center justify-center pointer-events-none">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/60 text-white px-3 py-1.5 rounded-full text-[11px] font-bold flex items-center gap-1.5 backdrop-blur-sm">
                                <i class="ph ph-arrows-out"></i> View Full Image
                            </div>
                        </div>

                        <!-- Carousel Controls -->
                        @if(count($event->images) > 1)
                            <button @click.stop="currentImage = currentImage > 0 ? currentImage - 1 : images.length - 1"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center backdrop-blur-sm transition-all shadow-md">
                                <i class="ph ph-caret-left text-lg"></i>
                            </button>
                            <button @click.stop="currentImage = currentImage < images.length - 1 ? currentImage + 1 : 0"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center backdrop-blur-sm transition-all shadow-md">
                                <i class="ph ph-caret-right text-lg"></i>
                            </button>
                        @endif
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center p-8 text-center"
                             style="background: linear-gradient(135deg, #1a3461 0%, #26488C 100%);">
                            <div class="h-16 w-16 rounded-2xl bg-white/10 flex items-center justify-center mb-3 backdrop-blur-md">
                                <i class="ph ph-calendar-blank text-3xl text-amber-300"></i>
                            </div>
                            <p class="text-sm font-bold text-white">No promotional image uploaded</p>
                            <p class="text-xs text-white/70 mt-0.5">Organization did not attach cover graphics</p>
                        </div>
                    @endif
                </div>

                <!-- Thumbnail strip if multiple images -->
                @if($event->images && count($event->images) > 1)
                    <div class="p-3 bg-gray-50/50 border-t border-gray-100 flex items-center gap-2 overflow-x-auto">
                        @foreach($event->images as $index => $image)
                            <button @click="currentImage = {{ $index }}"
                                    :class="currentImage === {{ $index }} ? 'ring-2 ring-[#26488C] border-transparent' : 'opacity-60 hover:opacity-100 border-gray-200'"
                                    class="h-12 w-16 shrink-0 rounded-md overflow-hidden border transition-all">
                                <img src="{{ storage_url($image) }}" alt="Thumbnail {{ $index + 1 }}" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Request Letter Card -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
                <div class="px-5 py-3 border-b border-gray-50 bg-gray-50/40 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-file-text text-[#26488C]"></i> Request Letter & Documentation
                    </h3>
                    <div class="flex items-center gap-2">
                        @if($proposal?->request_letter)
                            <button @click="openDocViewer('{{ storage_url($proposal->request_letter) }}', 'Request Letter')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#26488C] hover:bg-[#1a3461] text-white font-black text-[10px] uppercase tracking-wider rounded-md transition-all shadow-sm">
                                <i class="ph ph-eye font-bold"></i> View
                            </button>
                            <a href="{{ route('osas.proposals.download-letter', $event) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-[#26488C] font-black text-[10px] uppercase tracking-wider rounded-md transition-all border border-blue-100">
                                <i class="ph ph-download-simple font-bold"></i> Download
                            </a>
                        @endif
                    </div>
                </div>

                <div class="p-5">
                    @if($proposal?->request_letter)
                        @php
                            $isPdf = str_ends_with(strtolower($proposal->request_letter), '.pdf');
                        @endphp
                        
                        @if($isPdf)
                            <div class="rounded-lg overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer hover:border-[#26488C]/30 transition-all"
                                 @click="openDocViewer('{{ storage_url($proposal->request_letter) }}', 'Request Letter')">
                                <iframe src="{{ storage_url($proposal->request_letter) }}#toolbar=0" class="w-full h-72 pointer-events-none" frameborder="0"></iframe>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span class="flex items-center gap-1.5">
                                    <i class="ph ph-file-pdf text-red-500 text-base"></i>
                                    <span>PDF Document</span>
                                </span>
                                <button @click="openDocViewer('{{ storage_url($proposal->request_letter) }}', 'Request Letter')"
                                        class="text-[#26488C] hover:underline font-semibold flex items-center gap-1">
                                    <i class="ph ph-arrows-out"></i>
                                    <span>Open in Viewer</span>
                                </button>
                            </div>
                        @else
                            <div class="text-center py-8 px-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                                <div class="h-14 w-14 rounded-full bg-blue-50 text-[#26488C] flex items-center justify-center mx-auto mb-3">
                                    <i class="ph ph-file-doc text-3xl"></i>
                                </div>
                                <h4 class="text-sm font-bold text-gray-900">Document Attached</h4>
                                <p class="text-xs text-gray-500 mt-1 mb-4">This file format cannot be directly embedded. Please download to view.</p>
                                <a href="{{ route('osas.proposals.download-letter', $event) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-[#26488C] text-white text-xs font-bold rounded-lg hover:bg-[#1a3461] transition-all shadow-sm">
                                    <i class="ph ph-download-simple"></i> Download Document
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <i class="ph ph-file-x text-4xl mb-2 text-gray-300"></i>
                            <h4 class="text-sm font-bold text-gray-700">No Request Letter Attached</h4>
                            <p class="text-xs text-gray-400 mt-1">The organization did not upload a digital request letter with this proposal.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column: Details & Actions (7 cols) -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Event Key Information Grid -->
            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="ph ph-info text-[#26488C]"></i> Event Specifications
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Schedule -->
                    <div class="p-3.5 rounded-lg bg-gray-50/70 border border-gray-100 flex items-start gap-3">
                        <div class="h-10 w-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center shrink-0">
                            <i class="ph ph-calendar text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Start Date & Time</span>
                            <span class="text-xs font-bold text-gray-900 block mt-0.5">{{ $event->start_date->format('F d, Y') }}</span>
                            <span class="text-[11px] font-semibold text-[#26488C] block">{{ $event->start_date->format('h:i A') }}</span>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-lg bg-gray-50/70 border border-gray-100 flex items-start gap-3">
                        <div class="h-10 w-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center shrink-0">
                            <i class="ph ph-clock text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">End Date & Time</span>
                            <span class="text-xs font-bold text-gray-900 block mt-0.5">
                                {{ $event->end_date ? $event->end_date->format('F d, Y') : 'Same Day' }}
                            </span>
                            <span class="text-[11px] font-semibold text-[#26488C] block">
                                {{ $event->end_date ? $event->end_date->format('h:i A') : 'End time not set' }}
                            </span>
                        </div>
                    </div>

                    <!-- Venue -->
                    <div class="p-3.5 rounded-lg bg-gray-50/70 border border-gray-100 flex items-start gap-3">
                        <div class="h-10 w-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <i class="ph ph-map-pin text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Venue / Location</span>
                            <span class="text-xs font-bold text-gray-900 block mt-0.5">{{ $event->venue }}</span>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="p-3.5 rounded-lg bg-gray-50/70 border border-gray-100 flex items-start gap-3">
                        <div class="h-10 w-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                            <i class="ph ph-tag text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Category</span>
                            <span class="text-xs font-bold text-gray-900 block mt-0.5">{{ $event->category_label }}</span>
                        </div>
                    </div>

                    <!-- Planned Budget -->
                    <div class="p-3.5 rounded-lg bg-gray-50/70 border border-gray-100 flex items-start gap-3">
                        <div class="h-10 w-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <i class="ph ph-money-wavy text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Planned Budget</span>
                            <span class="text-sm font-black text-emerald-700 block mt-0.5">
                                ₱{{ number_format($event->planned_budget, 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Submitter & Date Metadata -->
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-user-circle text-base text-gray-400"></i>
                        <span>Submitted by: <strong>{{ $event->user?->name ?? $proposal?->submitter?->name ?? 'Organization Officer' }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ph ph-calendar-check text-base text-gray-400"></i>
                        <span>Date Filed: <strong>{{ $event->created_at->format('M d, Y h:i A') }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Description Card -->
            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="ph ph-text-align-left text-[#26488C]"></i> Event Description & Details
                </h3>

                <div class="prose max-w-none text-gray-700 text-sm leading-relaxed break-words bg-gray-50/40 p-4 rounded-lg border border-gray-100">
                    {!! $event->description !!}
                </div>
            </div>

            <!-- Alerts & History (Rejection, Reschedule, Approval info) -->
            @if($event->osas_status === 'rejected' && $proposal?->osas_rejection_reason)
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="h-9 w-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="ph ph-warning-circle text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-black uppercase tracking-wider text-red-900">Reason for OSAS Rejection</h4>
                            <p class="text-xs text-red-700 mt-1 leading-relaxed">{{ $proposal->osas_rejection_reason }}</p>
                            @if($proposal->osasReviewer)
                                <p class="text-[11px] font-semibold text-red-500 mt-2">
                                    Reviewed by {{ $proposal->osasReviewer->name }} on {{ $proposal->osas_reviewed_at?->format('M d, Y h:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($proposal?->reschedule_status === 'pending')
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="h-9 w-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="ph ph-calendar-blank text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-black uppercase tracking-wider text-amber-900">Reschedule Request Pending Review</h4>
                            <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                                <strong>Reason:</strong> {{ $proposal->reschedule_reason }}
                            </p>
                            @if($proposal->rescheduleRequester)
                                <p class="text-[11px] font-semibold text-amber-600 mt-2">
                                    Requested by {{ $proposal->rescheduleRequester->name }} on {{ $proposal->reschedule_requested_at?->format('M d, Y h:i A') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Action Controls Card -->
            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i class="ph ph-sliders-horizontal text-[#26488C]"></i> Administrative Actions
                </h3>

                <div class="flex flex-wrap gap-3">
                    @if($event->osas_status === 'pending')
                        <button @click="showApproveModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition-all shadow-sm">
                            <i class="ph ph-check-circle text-base"></i>
                            <span>Approve Proposal</span>
                        </button>
                        <button @click="showRejectModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg transition-all shadow-sm">
                            <i class="ph ph-x-circle text-base"></i>
                            <span>Reject Proposal</span>
                        </button>
                    @endif

                    @if($event->osas_status === 'approved' && !$event->hardcopy_received_at)
                        <button @click="showHardcopyModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#26488C] hover:bg-[#1a3461] text-white font-bold text-xs rounded-lg transition-all shadow-sm">
                            <i class="ph ph-file-text text-base"></i>
                            <span>Confirm Hardcopy Received</span>
                        </button>
                    @endif

                    @if($proposal?->reschedule_status === 'pending')
                        <button @click="showRescheduleApproveModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition-all shadow-sm">
                            <i class="ph ph-calendar-check text-base"></i>
                            <span>Approve Reschedule</span>
                        </button>
                        <button @click="showRescheduleRejectModal = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg transition-all shadow-sm">
                            <i class="ph ph-calendar-x text-base"></i>
                            <span>Reject Reschedule</span>
                        </button>
                    @endif

                    @if($org)
                    <button type="button" onclick="window.openOsasChatWithOrg && window.openOsasChatWithOrg({{ $org->id }})"
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-lg transition-all">
                        <i class="ph ph-envelope-simple text-base"></i>
                        <span>Contact Organization</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Image Lightbox -->
    <div x-show="showLightbox" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="showLightbox = false" @keydown.escape.window="showLightbox = false">
        <div class="absolute inset-0 bg-gray-900/85 backdrop-blur-sm"></div>
        <button @click.stop="showLightbox = false"
                class="absolute top-4 right-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
            <i class="ph ph-x text-xl"></i>
        </button>
        <div class="relative flex items-center justify-center h-full px-4" @click.stop>
            <button @click.stop="lightboxIndex = lightboxIndex > 0 ? lightboxIndex - 1 : lightboxImages.length - 1"
                    x-show="lightboxImages.length > 1"
                    class="absolute left-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
                <i class="ph ph-caret-left text-lg"></i>
            </button>
            <img :src="lightboxImages[lightboxIndex]" alt="Full Image"
                 class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
            <button @click.stop="lightboxIndex = lightboxIndex < lightboxImages.length - 1 ? lightboxIndex + 1 : 0"
                    x-show="lightboxImages.length > 1"
                    class="absolute right-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
                <i class="ph ph-caret-right text-lg"></i>
            </button>
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-black/60 text-white text-xs font-bold rounded-full backdrop-blur-sm"
                 x-show="lightboxImages.length > 1">
                <span x-text="`${lightboxIndex + 1} / ${lightboxImages.length}`"></span>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div x-show="showDocViewer" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center p-4 lg:p-10"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="showDocViewer = false" @keydown.escape.window="showDocViewer = false">
        <div class="absolute inset-0 bg-gray-900/85 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full h-full max-w-5xl overflow-hidden flex flex-col"
             x-transition:enter="transition ease-out duration-300 delay-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.stop>
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center">
                        <i class="ph ph-file-text"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900" x-text="docViewerTitle">Document Preview</h3>
                        <p class="text-[10px] text-gray-400 font-semibold">UniOrg Document Viewer</p>
                    </div>
                </div>
                <button @click="showDocViewer = false"
                        class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="flex-1 bg-gray-100 relative">
                <iframe :src="docViewerUrl" class="w-full h-full border-none" onload="this.nextElementSibling.classList.add('hidden')"></iframe>
                <div class="absolute inset-0 flex items-center justify-center bg-gray-50 doc-loader">
                    <div class="w-12 h-12 border-4 border-[#26488C] border-t-transparent rounded-full animate-spin"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Approve Modal -->
    <div x-show="showApproveModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showApproveModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-emerald-100 mb-4">
                    <i class="ph ph-check-circle text-3xl text-emerald-600"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Approve Proposal</h3>
                <p class="text-xs text-gray-600 mb-6 leading-relaxed">
                    Are you sure you want to approve "<strong>{{ $event->title }}</strong>"? The event will be published to the Master Calendar.
                </p>
                <div class="flex space-x-3">
                    <button @click="showApproveModal = false" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-bold text-xs hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button @click="executeAction('approve')" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs transition flex items-center justify-center gap-2">
                        <i class="ph" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                        <span x-text="isProcessing ? 'Approving...' : 'Yes, Approve'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div x-show="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRejectModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4">
                    <i class="ph ph-x-circle text-3xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Reject Proposal</h3>
                <p class="text-xs text-gray-600 mb-4">
                    Please provide a clear reason for rejecting "<strong>{{ $event->title }}</strong>".
                </p>
                <div class="text-left mb-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea x-model="rejectionReason" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500"
                              placeholder="Describe reasons or corrective actions..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button @click="showRejectModal = false" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-bold text-xs hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button @click="executeAction('reject')" :disabled="isProcessing || !rejectionReason.trim()"
                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-xs transition flex items-center justify-center gap-2"
                            :class="{ 'opacity-50 cursor-not-allowed': isProcessing || !rejectionReason.trim() }">
                        <i class="ph" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-x'"></i>
                        <span x-text="isProcessing ? 'Rejecting...' : 'Reject Proposal'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hardcopy Received Modal -->
    <div x-show="showHardcopyModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showHardcopyModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-blue-100 mb-4">
                    <i class="ph ph-file-text text-3xl text-[#26488C]"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Confirm Hardcopy Received</h3>
                <p class="text-xs text-gray-600 mb-6">
                    Have you received and validated the physical document packet for "<strong>{{ $event->title }}</strong>"?
                </p>
                <div class="flex space-x-3">
                    <button @click="showHardcopyModal = false" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-bold text-xs hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button @click="executeAction('hardcopy')" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 bg-[#26488C] hover:bg-[#1a3461] text-white rounded-lg font-bold text-xs transition flex items-center justify-center gap-2">
                        <i class="ph" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                        <span x-text="isProcessing ? 'Saving...' : 'Yes, Hardcopy Received'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Approve Modal -->
    <div x-show="showRescheduleApproveModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRescheduleApproveModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-emerald-100 mb-4">
                    <i class="ph ph-calendar-check text-3xl text-emerald-600"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Approve Reschedule Request</h3>
                <p class="text-xs text-gray-600 mb-6">
                    Approving will grant the organization permission to edit the schedule, venue, or budget.
                </p>
                <div class="flex space-x-3">
                    <button @click="showRescheduleApproveModal = false" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-bold text-xs hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button @click="executeAction('reschedule-approve')" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs transition flex items-center justify-center gap-2">
                        <i class="ph" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                        <span x-text="isProcessing ? 'Approving...' : 'Approve Reschedule'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Reject Modal -->
    <div x-show="showRescheduleRejectModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRescheduleRejectModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4">
                    <i class="ph ph-calendar-x text-3xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">Reject Reschedule Request</h3>
                <div class="text-left mb-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Reason for Rejection <span class="text-red-500">*</span></label>
                    <textarea x-model="rescheduleRejectionReason" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-red-500 focus:border-red-500"
                              placeholder="Explain why reschedule cannot be granted..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button @click="showRescheduleRejectModal = false" :disabled="isProcessing"
                            class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-bold text-xs hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button @click="executeAction('reschedule-reject')" :disabled="isProcessing || !rescheduleRejectionReason.trim()"
                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-xs transition flex items-center justify-center gap-2"
                            :class="{ 'opacity-50 cursor-not-allowed': isProcessing || !rescheduleRejectionReason.trim() }">
                        <i class="ph" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-x'"></i>
                        <span x-text="isProcessing ? 'Rejecting...' : 'Reject Reschedule'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('osasProposalDetail', () => ({
            showApproveModal: false,
            showRejectModal: false,
            showHardcopyModal: false,
            showRescheduleApproveModal: false,
            showRescheduleRejectModal: false,
            isProcessing: false,
            rejectionReason: '',
            rescheduleRejectionReason: '',
            eventId: {{ $event->id }},

            // Lightbox state
            showLightbox: false,
            lightboxImages: {{ json_encode(array_map(fn($img) => storage_url($img), is_array($event->images) ? $event->images : [])) }},
            lightboxIndex: 0,

            // Document viewer state
            showDocViewer: false,
            docViewerUrl: '',
            docViewerTitle: 'Document Preview',

            openLightbox(index) {
                this.lightboxIndex = index;
                this.showLightbox = true;
            },

            openDocViewer(url, title) {
                this.docViewerUrl = url;
                this.docViewerTitle = title || 'Document Preview';
                this.showDocViewer = true;
            },

            async executeAction(actionType) {
                this.isProcessing = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                let url = '';
                let payload = {};

                if (actionType === 'approve') {
                    url = `{{ url('osas/proposals') }}/${this.eventId}/approve`;
                } else if (actionType === 'reject') {
                    url = `{{ url('osas/proposals') }}/${this.eventId}/reject`;
                    payload = { rejection_reason: this.rejectionReason };
                } else if (actionType === 'hardcopy') {
                    url = `{{ url('osas/proposals') }}/${this.eventId}/hardcopy-received`;
                } else if (actionType === 'reschedule-approve') {
                    url = `{{ url('osas/proposals') }}/${this.eventId}/reschedule/approve`;
                } else if (actionType === 'reschedule-reject') {
                    url = `{{ url('osas/proposals') }}/${this.eventId}/reschedule/reject`;
                    payload = { rejection_reason: this.rescheduleRejectionReason };
                }

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: Object.keys(payload).length > 0 ? JSON.stringify(payload) : null,
                    });

                    const result = await response.json();
                    if (result.success) {
                        if (typeof window.showToast === 'function') {
                            window.showToast(result.message || 'Action completed successfully.', 'success');
                        }
                        this.showApproveModal = false;
                        this.showRejectModal = false;
                        this.showHardcopyModal = false;
                        this.showRescheduleApproveModal = false;
                        this.showRescheduleRejectModal = false;

                        if (typeof window.refreshCurrentOsasPage === 'function') {
                            await window.refreshCurrentOsasPage();
                        } else {
                            window.location.reload();
                        }
                    } else {
                        if (typeof window.showToast === 'function') {
                            window.showToast(result.message || 'Action failed.', 'error');
                        } else {
                            alert(result.message || 'Action failed.');
                        }
                        this.isProcessing = false;
                    }
                } catch (error) {
                    console.error('Action error:', error);
                    if (typeof window.showToast === 'function') {
                        window.showToast('An unexpected network error occurred.', 'error');
                    } else {
                        alert('An unexpected network error occurred.');
                    }
                    this.isProcessing = false;
                }
            }
        }));
    });
</script>
@endpush
