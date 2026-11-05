@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Event Proposals')

@section('content')
<div class="max-w-[1600px] mx-auto px-6 py-5" x-data="proposalsPage()">
    <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">Event Proposals</h1>
            <p class="text-gray-500 text-xs font-medium mt-1">Review and approve event submissions from organizations.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
        <!-- Status Filter -->
        <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/30">
            <div class="flex flex-wrap gap-1.5">
                <button @click="activeStatus = 'all'"
                        :class="activeStatus === 'all' ? 'bg-[#26488C] text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-list mr-1.5"></i>All
                </button>
                <button @click="activeStatus = 'pending'"
                        :class="activeStatus === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-clock mr-1.5"></i>Pending
                </button>
                <button @click="activeStatus = 'approved'"
                        :class="activeStatus === 'approved' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-check-circle mr-1.5"></i>Approved
                </button>
                <button @click="activeStatus = 'rejected'"
                        :class="activeStatus === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-x-circle mr-1.5"></i>Rejected
                </button>
                <button @click="activeStatus = 'hardcopy_received'"
                        :class="activeStatus === 'hardcopy_received' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-file-text mr-1.5"></i>Received
                </button>
                <button @click="activeStatus = 'reschedule'"
                        :class="activeStatus === 'reschedule' ? 'bg-amber-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition border border-gray-200">
                    <i class="ph ph-calendar-blank mr-1.5"></i>Reschedule Requests
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-50">
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Event</th>
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Organization</th>
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Venue</th>
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Category</th>
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                        <th class="text-left px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($proposals as $event)
                    <tr class="hover:bg-gray-50/50 transition-colors"
                            x-show="activeStatus === 'all' || (activeStatus === 'pending' && '{{ $event->osas_status }}' === 'pending') || (activeStatus === 'approved' && '{{ $event->osas_status }}' === 'approved' && {{ $event->hardcopy_received_at ? 'true' : 'false' }} === false) || (activeStatus === 'rejected' && '{{ $event->osas_status }}' === 'rejected') || (activeStatus === 'hardcopy_received' && '{{ $event->osas_status }}' === 'approved' && {{ $event->hardcopy_received_at ? 'true' : 'false' }} === true) || (activeStatus === 'reschedule' && '{{ $event->proposal?->reschedule_status }}' !== '')">
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    @if($event->images && count($event->images) > 0)
                                    <img src="{{ \App\Helpers\StorageHelper::url($event->images[0]) }}" alt="{{ $event->title }}" class="w-8 h-8 flex-shrink-0 rounded-lg object-cover">
                                    @else
                                    <div class="w-8 h-8 flex-shrink-0 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <i class="ph ph-calendar text-gray-400 text-xs"></i>
                                    </div>
                                    @endif
                                    <p class="text-xs font-bold text-gray-900 truncate max-w-[200px]">{{ $event->title }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <span class="text-xs font-bold text-gray-700">{{ $event->organization->org_acronym ?? $event->organization->name ?? 'N/A' }}</span>
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <span class="text-xs text-gray-500 font-medium">{{ $event->venue }}</span>
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <span class="text-xs text-gray-500 font-medium">{{ $event->category_label }}</span>
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                @if($event->osas_status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Pending</span>
                                @elseif($event->osas_status === 'approved' && !$event->hardcopy_received_at)
                                    <span class="relative group inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <svg class="animate-spin h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Approved
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                                            Waiting for hardcopy submission
                                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </span>
                                @elseif($event->osas_status === 'approved' && $event->hardcopy_received_at)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Approved</span>
                                @elseif($event->osas_status === 'rejected')
                                    <span class="relative group inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 cursor-help">
                                        Rejected
                                        @if($event->proposal?->osas_rejection_reason)
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-normal max-w-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-left">
                                            <p class="font-semibold mb-1">Reason:</p>
                                            <p>{{ $event->proposal->osas_rejection_reason }}</p>
                                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                        @endif
                                    </span>
                                @endif
                                @if($event->proposal?->reschedule_status === 'pending')
                                    <span class="relative group inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 cursor-help ml-1">
                                        <i class="ph ph-calendar-blank mr-1"></i> Reschedule Requested
                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-normal max-w-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 text-left">
                                            <p class="font-semibold mb-1">Reason:</p>
                                            <p>{{ $event->proposal->reschedule_reason }}</p>
                                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                                        </div>
                                    </span>
                                @elseif($event->proposal?->reschedule_status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 ml-1">
                                        <i class="ph ph-calendar-check mr-1"></i> Reschedule Approved
                                    </span>
                                @elseif($event->proposal?->reschedule_status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 ml-1">
                                        <i class="ph ph-calendar-x mr-1"></i> Reschedule Rejected
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5 flex-nowrap">
                                    <button @click="viewEvent = {{ $event->toJson() }}; currentImage = 0; activeTab = 'details'; showViewModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-[#26488C] hover:text-[#1a3461]">
                                        <i class="ph ph-eye mr-1"></i> View
                                    </button>
                                    @if($event->osas_status === 'pending')
                                    <button @click="selectedEvent = {{ $event->toJson() }}; showApproveModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-800">
                                        <i class="ph ph-check mr-1"></i> Approve
                                    </button>
                                    <button @click="selectedEvent = {{ $event->toJson() }}; showRejectModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-red-600 hover:text-red-800">
                                        <i class="ph ph-x mr-1"></i> Reject
                                    </button>
                                    @endif
                                    @if($event->osas_status === 'approved' && !$event->hardcopy_received_at)
                                    <button @click="selectedEvent = {{ $event->toJson() }}; showHardcopyModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-[#26488C] hover:text-[#1a3461]">
                                        <i class="ph ph-file-text mr-1"></i> Received
                                    </button>
                                    @endif
                                    @if($event->proposal?->reschedule_status === 'pending')
                                    <button @click="selectedEvent = {{ $event->toJson() }}; showRescheduleApproveModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-800">
                                        <i class="ph ph-check mr-1"></i> Approve Resched.
                                    </button>
                                    <button @click="selectedEvent = {{ $event->toJson() }}; showRescheduleRejectModal = true" class="inline-flex items-center whitespace-nowrap text-[10px] font-black uppercase tracking-widest text-red-600 hover:text-red-800">
                                        <i class="ph ph-x mr-1"></i> Reject Resched.
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        @endforelse
                        <!-- Empty state per filter -->
                        <tr x-show="(activeStatus === 'all' && {{ $proposals->total() }} === 0) || (activeStatus === 'pending' && {{ $pendingCount }} === 0) || (activeStatus === 'approved' && {{ $approvedCount }} === 0) || (activeStatus === 'rejected' && {{ $rejectedCount }} === 0) || (activeStatus === 'hardcopy_received' && {{ $hardcopyReceivedCount }} === 0)">
                            <td colspan="5" class="px-8 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                        <i class="ph ph-tray text-2xl text-gray-200"></i>
                                    </div>
                                    <p class="text-sm font-black text-gray-900" x-text="
                                        activeStatus === 'pending' ? 'No Pending Proposals' :
                                        activeStatus === 'approved' ? 'No Approved Proposals' :
                                        activeStatus === 'rejected' ? 'No Rejected Proposals' :
                                        activeStatus === 'hardcopy_received' ? 'No Received Proposals' :
                                        'No Proposals Yet'
                                    "></p>
                                    <p class="text-xs text-gray-400 mt-1" x-text="
                                        activeStatus === 'pending' ? 'No event proposals are currently pending review.' :
                                        activeStatus === 'approved' ? 'No event proposals are currently approved.' :
                                        activeStatus === 'rejected' ? 'No event proposals have been rejected.' :
                                        activeStatus === 'hardcopy_received' ? 'No hardcopy documents have been received yet.' :
                                        'Submitted events from organizations will appear here.'
                                    "></p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 bg-gray-50/30 border-t border-gray-50 premium-pagination">
                {{ $proposals->links() }}
            </div>
    </div>

    <!-- View Event Details Modal -->
    <div x-show="showViewModal" @click.self="showViewModal = false" class="fixed inset-0 bg-gray-600 bg-opacity-50 modal-backdrop z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="relative max-w-6xl w-full shadow-2xl rounded-lg bg-white overflow-hidden" x-show="viewEvent">
            <button @click="showViewModal = false" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 text-gray-600 hover:text-gray-800 shadow-lg">
                <i class="ph ph-x text-xl"></i>
            </button>
            <!-- Tabs -->
            <div class="flex border-b border-gray-200 px-8 pt-4">
                <button @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-b-2 text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-semibold text-sm border-blue-600 mr-6 transition">
                    <i class="ph ph-calendar mr-1"></i> Proposed Event
                </button>
                <button @click="activeTab = 'letter'" :class="activeTab === 'letter' ? 'border-b-2 text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2 font-semibold text-sm border-blue-600 transition">
                    <i class="ph ph-file-text mr-1"></i> Request Letter
                </button>
            </div>
            <!-- Tab 1: Proposed Event -->
            <div x-show="activeTab === 'details'" class="grid grid-cols-1 md:grid-cols-2">
                <div class="relative aspect-square w-full overflow-hidden group bg-gray-100">
                    <template x-if="viewEvent && viewEvent.images && viewEvent.images.length > 0">
                        <div>
                            <template x-for="(image, index) in viewEvent.images" :key="index">
                                <div x-show="currentImage === index" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="w-full h-full absolute inset-0">
                                    <img :src="window.storageUrl(image)" :alt="viewEvent.title" class="w-full h-full object-contain">
                                </div>
                            </template>
                            <template x-if="viewEvent.images.length > 1">
                                <div>
                                    <button @click="currentImage = currentImage > 0 ? currentImage - 1 : viewEvent.images.length - 1" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                                        <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                                    </button>
                                    <button @click="currentImage = currentImage < viewEvent.images.length - 1 ? currentImage + 1 : 0" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 transition p-2 opacity-0 group-hover:opacity-100">
                                        <svg class="w-8 h-8 drop-shadow-lg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                    </button>
                                    <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-gray-900 bg-opacity-80 text-white text-base font-bold px-4 py-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition">
                                        <span x-text="currentImage + 1"></span> / <span x-text="viewEvent.images.length"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!viewEvent?.images || viewEvent.images.length === 0">
                        <div class="bg-gradient-to-br from-blue-400 to-blue-600 h-full flex items-center justify-center">
                            <i class="ph ph-calendar-blank text-white text-8xl"></i>
                        </div>
                    </template>
                </div>
                <div class="p-8 overflow-y-auto max-h-[600px]">
                    <div class="flex items-center text-sm text-gray-500 mb-6">
                        <i class="ph ph-clock mr-2 text-blue-600"></i>
                        <span x-text="viewEvent?.start_date ? new Date(viewEvent.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : ''"></span>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-6" x-text="viewEvent?.title"></h2>
                    <div class="grid grid-cols-1 gap-4 mb-6 pb-6 border-b border-gray-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-3 rounded-lg mr-3"><i class="ph ph-calendar text-xl text-blue-600"></i></div>
                                <div><p class="text-sm text-gray-600">Start Date</p><p class="font-semibold text-gray-900" x-text="viewEvent?.start_date ? new Date(viewEvent.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : ''"></p></div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-3 rounded-lg mr-3"><i class="ph ph-calendar text-xl text-blue-600"></i></div>
                                <div><p class="text-sm text-gray-600">End Date</p><p class="font-semibold text-gray-900" x-text="viewEvent?.end_date ? new Date(viewEvent.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : ''"></p></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <div class="bg-emerald-100 p-3 rounded-lg mr-3"><i class="ph ph-money-wavy text-xl text-emerald-600"></i></div>
                                <div><p class="text-sm text-gray-600">Planned Budget</p><p class="font-semibold text-gray-900" x-text="viewEvent?.planned_budget ? '₱' + parseFloat(viewEvent.planned_budget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : ''"></p></div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-emerald-100 p-3 rounded-lg mr-3"><i class="ph ph-map-pin text-xl text-emerald-600"></i></div>
                                <div><p class="text-sm text-gray-600">Venue</p><p class="font-semibold text-gray-900" x-text="viewEvent?.venue"></p></div>
                            </div>
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg mr-3"><i class="ph ph-tag text-xl text-purple-600"></i></div>
                                <div><p class="text-sm text-gray-600">Category</p><p class="font-semibold text-gray-900" x-text="viewEvent?.category_label || viewEvent?.category || '—'"></p></div>
                            </div>
                        </div>
                    </div>
                    <div class="prose max-w-none text-gray-700">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Event Details</h3>
                        <div x-html="viewEvent?.description" style="white-space: pre-line;" class="break-words"></div>
                    </div>
                </div>
            </div>
            <!-- Tab 2: Request Letter -->
            <div x-show="activeTab === 'letter'" class="p-8">
                <template x-if="viewEvent?.proposal?.request_letter">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-900">Request Letter</h3>
                            <a :href="`{{ url('osas/proposals') }}/${viewEvent.id}/download-letter`"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg font-semibold hover:bg-blue-700 transition">
                                <i class="ph ph-download-simple mr-2"></i> Download
                            </a>
                        </div>
                        <template x-if="window.storageUrl(viewEvent.proposal.request_letter).toLowerCase().endsWith('.pdf')">
                            <embed :src="window.storageUrl(viewEvent.proposal.request_letter)"
                                   class="w-full h-[500px] rounded-lg border border-gray-200"
                                   type="application/pdf">
                        </template>
                        <template x-if="!window.storageUrl(viewEvent.proposal.request_letter).toLowerCase().endsWith('.pdf')">
                            <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                                <i class="ph ph-file-doc text-7xl text-blue-500 mb-4"></i>
                                <p class="text-gray-600 text-sm mb-4">This document cannot be previewed. Please download to view.</p>
                                <a :href="`{{ url('osas/proposals') }}/${viewEvent.id}/download-letter`"
                                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">
                                    <i class="ph ph-download-simple mr-2"></i> Download Document
                                </a>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!viewEvent?.proposal?.request_letter">
                    <div class="text-center py-12 text-gray-400">
                        <i class="ph ph-file-text text-7xl mb-6"></i>
                        <h3 class="text-xl font-semibold mb-2">No Request Letter</h3>
                        <p class="text-sm">This event does not have a request letter attached.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div x-show="showApproveModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showApproveModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-4">
                        <i class="ph ph-check-circle text-3xl text-emerald-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Approve Proposal</h3>
                    <p class="text-gray-600 mb-6">
                        Are you sure you want to approve "<span x-text="selectedEvent?.title" class="font-semibold"></span>"? The event will appear on the Master Calendar and the admin can post it.
                    </p>
                    <div class="flex space-x-3" x-data="{ isProcessing: false }">
                        <button @click="showApproveModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">Cancel</button>
                        <button @click="
                            isProcessing = true;
                            fetch(`{{ url('osas/proposals') }}/${selectedEvent.id}/approve`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showApproveModal = false; window.refreshCurrentOsasPage().then(refreshed => { if (!refreshed) isProcessing = false; }); }
                                else { showToast(d.message || 'Failed', 'error'); isProcessing = false; }
                            }).catch(() => { showToast('Error', 'error'); isProcessing = false; });
                        " :disabled="isProcessing" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">
                            <i class="ph mr-2" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                            <span x-text="isProcessing ? 'Approving...' : 'Approve'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div x-show="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRejectModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-data="{ rejectionText: '', isProcessing: false }">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-x-circle text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Proposal</h3>
                    <p class="text-gray-600 mb-4">
                        Are you sure you want to reject "<span x-text="selectedEvent?.title" class="font-semibold"></span>"?
                    </p>
                    <div class="text-left mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                        <textarea x-model="rejectionText" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showRejectModal = false; rejectionText = ''" :disabled="isProcessing" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">Cancel</button>
                        <button @click="
                            if (!rejectionText.trim()) { showToast('Please provide a rejection reason.', 'error'); return; }
                            isProcessing = true;
                            fetch(`{{ url('osas/proposals') }}/${selectedEvent.id}/reject`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({ rejection_reason: rejectionText })
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showRejectModal = false; rejectionText = ''; window.refreshCurrentOsasPage().then(refreshed => { if (!refreshed) isProcessing = false; }); }
                                else { showToast(d.message || 'Failed', 'error'); isProcessing = false; }
                            }).catch(() => { showToast('Error', 'error'); isProcessing = false; });
                        " :disabled="isProcessing || !rejectionText.trim()" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing || !rejectionText.trim() }">
                            <i class="ph mr-2" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-x'"></i>
                            <span x-text="isProcessing ? 'Rejecting...' : 'Reject'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hardcopy Received Modal -->
    <div x-show="showHardcopyModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showHardcopyModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                        <i class="ph ph-file-text text-3xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Received</h3>
                    <p class="text-gray-600 mb-6">
                        Have you received the hardcopy documents for "<span x-text="selectedEvent?.title" class="font-semibold"></span>"?
                    </p>
                    <div class="flex space-x-3" x-data="{ isProcessing: false }">
                        <button @click="showHardcopyModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">Cancel</button>
                        <button @click="
                            isProcessing = true;
                            fetch(`{{ url('osas/proposals') }}/${selectedEvent.id}/hardcopy-received`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showHardcopyModal = false; window.refreshCurrentOsasPage().then(refreshed => { if (!refreshed) isProcessing = false; }); }
                                else { showToast(d.message || 'Failed', 'error'); isProcessing = false; }
                            }).catch(() => { showToast('Error', 'error'); isProcessing = false; });
                        " :disabled="isProcessing" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">
                            <i class="ph mr-2" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                            <span x-text="isProcessing ? 'Confirming...' : 'Yes, Received'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Reschedule Modal -->
    <div x-show="showRescheduleApproveModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRescheduleApproveModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-4">
                        <i class="ph ph-calendar-check text-3xl text-emerald-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Approve Reschedule Request</h3>
                    <p class="text-sm font-medium text-gray-900 mb-3" x-text="selectedEvent?.title"></p>
                    <div class="text-left bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Reason from organization</p>
                        <p class="text-sm text-gray-800" x-text="selectedEvent?.proposal?.reschedule_reason"></p>
                    </div>
                    <p class="text-gray-600 mb-6 text-sm">
                        Approving will let the organization update the event's date, time, venue, and budget.
                    </p>
                    <div class="flex space-x-3" x-data="{ isProcessing: false }">
                        <button @click="showRescheduleApproveModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">Cancel</button>
                        <button @click="
                            isProcessing = true;
                            fetch(`{{ url('osas/proposals') }}/${selectedEvent.id}/reschedule/approve`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showRescheduleApproveModal = false; window.refreshCurrentOsasPage().then(refreshed => { if (!refreshed) isProcessing = false; }); }
                                else { showToast(d.message || 'Failed', 'error'); isProcessing = false; }
                            }).catch(() => { showToast('Error', 'error'); isProcessing = false; });
                        " :disabled="isProcessing" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">
                            <i class="ph mr-2" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-check'"></i>
                            <span x-text="isProcessing ? 'Approving...' : 'Approve'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Reschedule Modal -->
    <div x-show="showRescheduleRejectModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showRescheduleRejectModal = false" class="fixed inset-0 bg-black opacity-50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-data="{ rescheduleRejectionText: '', isProcessing: false }">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="ph ph-calendar-x text-3xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Reschedule Request</h3>
                    <p class="text-sm font-medium text-gray-900 mb-3" x-text="selectedEvent?.title"></p>
                    <div class="text-left bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Reason from organization</p>
                        <p class="text-sm text-gray-800" x-text="selectedEvent?.proposal?.reschedule_reason"></p>
                    </div>
                    <div class="text-left mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                        <textarea x-model="rescheduleRejectionText" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Explain why the reschedule request is denied..."></textarea>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showRescheduleRejectModal = false; rescheduleRejectionText = ''" :disabled="isProcessing" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing }">Cancel</button>
                        <button @click="
                            if (!rescheduleRejectionText.trim()) { showToast('Please provide a rejection reason.', 'error'); return; }
                            isProcessing = true;
                            fetch(`{{ url('osas/proposals') }}/${selectedEvent.id}/reschedule/reject`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({ rejection_reason: rescheduleRejectionText })
                            }).then(r => r.json()).then(d => {
                                if (d.success) { showToast(d.message, 'success'); showRescheduleRejectModal = false; rescheduleRejectionText = ''; window.refreshCurrentOsasPage().then(refreshed => { if (!refreshed) isProcessing = false; }); }
                                else { showToast(d.message || 'Failed', 'error'); isProcessing = false; }
                            }).catch(() => { showToast('Error', 'error'); isProcessing = false; });
                        " :disabled="isProcessing || !rescheduleRejectionText.trim()" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition" :class="{ 'opacity-50 cursor-not-allowed': isProcessing || !rescheduleRejectionText.trim() }">
                            <i class="ph mr-2" :class="isProcessing ? 'ph-spinner ph-spin' : 'ph-x'"></i>
                            <span x-text="isProcessing ? 'Rejecting...' : 'Reject'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    .max-w-\[1600px\] { font-family: 'Plus Jakarta Sans', sans-serif; }

    .premium-pagination nav > div:first-child { display: none !important; }
    .premium-pagination nav > div:last-child { justify-content: center !important; gap: 0.4rem !important; }
    .premium-pagination span[aria-current="page"] > span {
        background-color: #26488C !important; color: white !important; border: none !important;
        border-radius: 6px !important; padding: 6px 12px !important; font-weight: 800 !important;
    }
    .premium-pagination a, .premium-pagination span > span, .premium-pagination span > a {
        border-radius: 6px !important; border: 1px solid rgba(0,0,0,0.05) !important; padding: 6px 12px !important;
        font-size: 10px !important; font-weight: 700 !important; text-transform: uppercase !important;
        letter-spacing: 0.05em !important; transition: all 0.2s ease !important;
    }
    .premium-pagination a:hover { background-color: #F8FAFC !important; border-color: #26488C !important; color: #26488C !important; }
</style>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('proposalsPage', () => ({
            showViewModal: false,
            showApproveModal: false,
            showRejectModal: false,
            showHardcopyModal: false,
            showRescheduleApproveModal: false,
            showRescheduleRejectModal: false,
            viewEvent: null,
            selectedEvent: null,
            currentImage: 0,
            activeTab: 'details',
            activeStatus: 'all',
        }));
    });
</script>
@endpush
