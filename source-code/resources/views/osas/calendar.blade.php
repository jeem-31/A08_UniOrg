@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Master Events Calendar')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<style>
    :root {
        --osas-primary: #26488C;
        --osas-secondary: #C4973B;
        --sidebar-bg: #F9FAFB;
    }
    
    .calendar-container {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 1rem;
        background: white;
        border-radius: 0.5rem;
        border: 1px solid #F1F5F9;
        padding: 1rem;
        min-height: 85vh;
    }

    /* Left Sidebar Styling */
    .calendar-layers {
        background: var(--sidebar-bg);
        border-radius: 0.5rem;
        padding: 0.9rem;
        border: 1px solid #E5E7EB;
        height: fit-content;
    }

    .layer-title {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #6B7280;
        margin-bottom: 0.6rem;
        letter-spacing: 0.05em;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .org-list {
        max-height: 280px;
        overflow-y: auto;
        padding-right: 0.5rem;
    }

    .org-list::-webkit-scrollbar {
        width: 4px;
    }

    .org-list::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 8px;
    }

    .org-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.35rem 0.4rem;
        border-radius: 0.4rem;
        transition: background 0.2s;
        cursor: pointer;
    }

    .org-item:hover {
        background: #F3F4F6;
    }

    .org-checkbox {
        width: 1rem;
        height: 1rem;
        border-radius: 0.25rem;
        cursor: pointer;
    }

    /* FullCalendar Customization */
    .fc {
        font-family: inherit;
        font-size: 0.85rem;
    }

    .fc-header-toolbar {
        padding: 0.25rem !important;
        margin-bottom: 0.75rem !important;
    }

    .fc-button-primary {
        background: white !important;
        border: 1px solid #E5E7EB !important;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        padding: 0.35rem 0.7rem !important;
        box-shadow: none !important;
    }

    .fc-button-primary:hover {
        background: #F9FAFB !important;
    }

    .fc-button-active {
        background: var(--osas-primary) !important;
        color: white !important;
        border-color: var(--osas-primary) !important;
    }

    .fc-event {
        border: none !important;
        padding: 2px 6px !important;
        border-radius: 6px !important;
        font-weight: 500 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
        margin-bottom: 2px !important;
    }

    .fc-daygrid-day-number {
        font-weight: 600;
        color: #374151;
        text-decoration: none !important;
    }

    .fc-col-header-cell-cushion {
        text-transform: uppercase;
        font-size: 0.65rem;
        font-weight: 800;
        color: #6B7280;
        padding: 6px 0 !important;
        text-decoration: none !important;
    }

    /* Modal Styling — compact, no outer scroll */
    .modal-premium {
        backdrop-filter: blur(4px);
    }

    #eventModal .modal-surface {
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease;
    }

    #eventModal.hidden .modal-surface {
        transform: scale(0.95);
        opacity: 0;
    }

    /* Only the description box scrolls, never the modal itself */
    #modalDescText::-webkit-scrollbar {
        width: 4px;
    }

    #modalDescText::-webkit-scrollbar-thumb {
        background: #D1D5DB;
        border-radius: 8px;
    }

    /* Image Lightbox */
    .image-lightbox {
        backdrop-filter: blur(6px);
    }

    .image-lightbox img {
        transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    /* Document Viewer */
    .doc-viewer-modal .doc-viewer-surface {
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease;
    }

    .doc-viewer-modal.hidden .doc-viewer-surface {
        transform: scale(0.95);
        opacity: 0;
    }
</style>
@endpush

@section('content')
<div class="max-w-[1700px] mx-auto">
    <div class="mb-4 flex flex-col md:flex-row md:items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">Master Calendar</h1>
            <p class="text-gray-500 text-xs font-medium mt-1">Manage and monitor all campus activities in one place.</p>
        </div>

    </div>

    <div class="calendar-container">
        <!-- Sidebar Layers -->
        <aside class="calendar-layers">
            <div class="mb-4">
                <label class="layer-title">Quick Search</label>
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    <input type="text" id="calendarSearch" placeholder="Find an event..." class="w-full pl-8 pr-3 py-2 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-xs">
                </div>
            </div>

            <div class="mb-4">
                <label class="layer-title">Venue Filter</label>
                <select id="venueFilter" class="w-full bg-white border border-gray-200 text-xs rounded-lg focus:ring-2 focus:ring-primary p-2">
                    <option value="all">All Locations</option>
                    @foreach($venues as $venue)
                        <option value="{{ $venue }}">{{ $venue }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="layer-title">
                    <span>Organizations</span>
                    <button onclick="toggleAllOrgs(true)" class="text-primary hover:underline lowercase text-[9px]">Select All</button>
                </div>
                <div class="org-list space-y-0.5">
                    @foreach($organizations as $org)
                        <label class="org-item" data-org-id="{{ $org->id }}">
                            <input type="checkbox" checked class="org-checkbox border-gray-300 text-primary focus:ring-primary" 
                                   onchange="filterEvents()" data-org-id="{{ $org->id }}">
                            <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $org->settings->primary_color ?? '#26488C' }}"></div>
                            <span class="text-xs font-medium text-gray-700 truncate" title="{{ $org->name }}">
                                {{ $org->org_acronym ?? $org->name }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

        </aside>

        <!-- Main Calendar -->
        <main class="relative">
            <div id="calendar"></div>
        </main>
    </div>
</div>

<!-- Premium Event Modal (compact 2-column, no outer scroll) -->
<div id="eventModal" class="fixed inset-0 z-50 hidden overflow-hidden modal-premium">
    <div class="flex items-center justify-center min-h-screen px-4 py-4">
        <div class="fixed inset-0 bg-gray-900/60 transition-opacity" onclick="closeModal()"></div>

        <div class="relative bg-white rounded-lg shadow-2xl overflow-hidden max-w-4xl w-full max-h-[92vh] flex flex-col modal-surface border border-gray-100">
            <!-- Clean white header: full org name + status/category, no color wash -->
            <div id="modalHeaderColor" class="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between gap-4 shrink-0">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="h-12 w-12 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-center justify-center shrink-0 overflow-hidden" id="modalOrgIcon">
                        <i class="ph ph-calendar-check text-[#26488C] text-2xl"></i>
                    </div>
                    <div class="min-w-0">
                        <p id="modalOrgName" class="text-[15px] font-extrabold text-slate-900 leading-tight truncate">
                            Organization
                        </p>
                        <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                            <span id="modalStatusBadge" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200">
                                STATUS
                            </span>
                            <span id="modalCategoryBadge" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200">
                                <i class="ph ph-tag text-[11px]"></i><span id="modalCategoryText">Category</span>
                            </span>
                        </div>
                    </div>
                </div>

                <button onclick="closeModal()" aria-label="Close details" class="h-10 w-10 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-700 flex items-center justify-center transition shrink-0">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Body: 2 columns on desktop, stacks with inner scroll on mobile -->
            <div class="p-6 grid gap-6 md:grid-cols-[1.12fr_0.88fr] flex-1 min-h-0 overflow-y-auto md:overflow-hidden">
                <!-- Left: title + image + description (only this scrolls) -->
                <div class="flex flex-col gap-4 min-h-0 md:overflow-hidden">
                    <h3 class="text-xl font-extrabold text-slate-900 leading-snug tracking-tight line-clamp-2" id="modalTitle">Event Title</h3>

                    <!-- Event Image Thumbnail Preview (clickable) -->
                    <div id="modalImageContainer" class="hidden relative h-60 rounded-2xl overflow-hidden bg-slate-900 border border-slate-100 cursor-pointer group shrink-0" onclick="openModalLightbox()">
                        <img id="modalImagePreview" src="" alt="Event Preview" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300 flex items-center justify-center">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/60 text-white px-3.5 py-2 rounded-full text-xs font-bold flex items-center gap-1.5 backdrop-blur-sm">
                                <i class="ph ph-arrows-out"></i> View Full Image
                            </div>
                        </div>
                        <div id="modalImageBadge" class="absolute bottom-2.5 right-2.5 px-2.5 py-1 bg-black/70 text-white text-[11px] font-bold rounded-lg backdrop-blur-sm">
                            <i class="ph ph-images mr-1"></i> <span id="modalImageCount">1 photo</span>
                        </div>
                    </div>

                    <!-- Description (inner scroll only) -->
                    <div id="modalDescContainer" class="px-4 py-3.5 bg-slate-50/70 rounded-2xl border border-slate-100 flex flex-col min-h-0 md:flex-1 md:overflow-hidden">
                        <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block mb-1.5 shrink-0">Description</span>
                        <p id="modalDescText" class="text-sm text-slate-600 leading-relaxed break-words overflow-y-auto min-h-0 pr-1.5 md:max-h-60"></p>
                    </div>
                </div>

                <!-- Right: details + actions -->
                <div class="flex flex-col gap-3 min-h-0 md:overflow-hidden">
                    <!-- Date & Time: separate boxes, side by side -->
                    <div class="grid grid-cols-2 gap-3 shrink-0">
                        <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block">Date</span>
                            <p class="text-sm font-bold text-slate-800 mt-1.5 flex items-start gap-1.5">
                                <i class="ph ph-calendar-blank text-[#26488C] mt-0.5 text-base shrink-0"></i>
                                <span id="modalDate" class="leading-snug"></span>
                            </p>
                        </div>
                        <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block">Time</span>
                            <p class="text-sm font-bold text-slate-800 mt-1.5 flex items-start gap-1.5">
                                <i class="ph ph-clock text-[#26488C] mt-0.5 text-base shrink-0"></i>
                                <span id="modalTime" class="leading-snug"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Venue & Category: side by side below -->
                    <div class="grid grid-cols-2 gap-3 shrink-0">
                        <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block">Venue</span>
                            <p class="text-sm font-bold text-slate-800 mt-1.5 flex items-start gap-1.5" id="modalVenue">
                                <i class="ph ph-map-pin text-red-500 mt-0.5 text-base shrink-0"></i>
                                <span id="modalVenueText" class="leading-snug break-words">Venue</span>
                            </p>
                        </div>
                        <div id="modalCategoryBox" class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block">Category</span>
                            <p class="text-sm font-bold text-slate-800 mt-1.5 flex items-start gap-1.5">
                                <i class="ph ph-tag text-[#26488C] mt-0.5 text-base shrink-0"></i>
                                <span id="modalVenueCategory" class="leading-snug break-words">Category</span>
                            </p>
                        </div>
                    </div>

                    <!-- Planned Budget (if available) -->
                    <div id="modalBudgetContainer" class="px-4 py-3 bg-emerald-50/70 rounded-2xl border border-emerald-100 flex items-center justify-between shrink-0">
                        <span class="text-sm font-bold text-emerald-800 flex items-center gap-2">
                            <i class="ph ph-money-wavy text-emerald-600 text-xl"></i> Planned Budget
                        </span>
                        <span id="modalBudgetText" class="text-sm font-extrabold text-emerald-700">₱0.00</span>
                    </div>

                    <!-- Request Letter Preview (if available) -->
                    <div id="modalDocContainer" class="hidden shrink-0">
                        <div class="px-4 py-3 bg-blue-50/70 rounded-2xl border border-blue-100 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-11 w-11 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                                    <i class="ph ph-file-text text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="text-[11px] font-extrabold text-blue-400 uppercase tracking-widest block">Request Letter</span>
                                    <span class="text-sm font-bold text-blue-900 block truncate">Document attached</span>
                                </div>
                            </div>
                            <button onclick="event.stopPropagation(); openModalDocViewer()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold uppercase tracking-widest rounded-xl transition-all shrink-0">
                                <i class="ph ph-eye mr-1"></i> View
                            </button>
                        </div>
                    </div>

                    <!-- Conflict Warning -->
                    <div id="conflictWarning" class="hidden p-4 bg-slate-900 rounded-2xl border-l-4 border-red-500 text-white shrink-0">
                        <div class="flex items-center text-xs font-bold text-red-400 mb-1">
                            <i class="ph ph-warning-diamond mr-1.5 text-base"></i>
                            Venue Conflict Detected
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">Another approved event is scheduled at this venue at the same time.</p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="mt-auto pt-2 flex flex-col gap-2.5 shrink-0">
                        <div id="modalActions" class="hidden flex gap-2.5">
                            <button onclick="handleEventAction('approve')" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-widest transition shadow-sm flex items-center justify-center gap-1.5">
                                <i class="ph ph-check text-base"></i> Approve
                            </button>
                            <button onclick="handleEventAction('reject')" class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-widest transition shadow-sm flex items-center justify-center gap-1.5">
                                <i class="ph ph-x text-base"></i> Reject
                            </button>
                        </div>
                        <button id="modalMessage" class="w-full py-3.5 bg-amber-50 hover:bg-amber-100 text-[#9B7427] border border-amber-200 rounded-xl text-xs font-extrabold uppercase tracking-widest transition flex items-center justify-center gap-1.5">
                            <i class="ph ph-paper-plane-tilt text-base"></i> Message
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Lightbox for Modal -->
<div id="modalLightbox" class="fixed inset-0 z-[60] hidden image-lightbox" onclick="closeModalLightbox()">
    <div class="absolute inset-0 bg-gray-900/80"></div>
    <button onclick="closeModalLightbox()" class="absolute top-4 right-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
        <i class="ph ph-x text-xl"></i>
    </button>
    <div class="relative flex items-center justify-center h-full px-4" onclick="event.stopPropagation()">
        <button id="lbPrevBtn" onclick="modalLightboxPrev()" class="absolute left-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
            <i class="ph ph-caret-left text-lg"></i>
        </button>
        <img id="modalLightboxImg" src="" alt="Full Image" class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg shadow-2xl">
        <button id="lbNextBtn" onclick="modalLightboxNext()" class="absolute right-4 z-10 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition backdrop-blur-sm">
            <i class="ph ph-caret-right text-lg"></i>
        </button>
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-black/60 text-white text-xs font-bold rounded-full backdrop-blur-sm">
            <span id="lbCounter">1 / 1</span>
        </div>
    </div>
</div>

<!-- Document Viewer for Modal -->
<div id="modalDocViewer" class="fixed inset-0 z-[60] hidden doc-viewer-modal" onclick="closeModalDocViewer()">
    <div class="absolute inset-0 bg-gray-900/80"></div>
    <div class="relative flex items-center justify-center h-full p-4 lg:p-10" onclick="event.stopPropagation()">
        <div class="bg-white rounded-xl shadow-2xl w-full h-full max-w-5xl overflow-hidden flex flex-col doc-viewer-surface">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center">
                        <i class="ph ph-file-text"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Request Letter</h3>
                        <p class="text-[10px] text-gray-400 font-semibold">UniOrg Document Viewer</p>
                    </div>
                </div>
                <button onclick="closeModalDocViewer()" class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="flex-1 bg-gray-100 relative">
                <iframe id="modalDocFrame" src="" class="w-full h-full border-none" onload="document.getElementById('modalDocLoader').classList.add('hidden')"></iframe>
                <div id="modalDocLoader" class="absolute inset-0 flex items-center justify-center bg-gray-50">
                    <div class="w-12 h-12 border-4 border-[#26488C] border-t-transparent rounded-full animate-spin"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const originalEvents = @json($events);
        const modal = document.getElementById('eventModal');
        
        // Define Organization Colors from Settings
        const orgColors = {};
        @foreach($organizations as $org)
            orgColors[{{ $org->id }}] = '{{ $org->settings->primary_color ?? "#26488C" }}';
        @endforeach
        
        // Map events with colors (only if no conflict/status override is needed)
        const coloredEvents = originalEvents.map(event => {
            if (!event.extendedProps.hasConflict && event.extendedProps.status === 'approved') {
                event.color = orgColors[event.extendedProps.organization_id] || '#26488C';
            }
            return event;
        });

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            themeSystem: 'standard',
            events: coloredEvents,
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                showEventModal(info.event);
            },
            eventDisplay: 'block',
            displayEventTime: true,
            height: '100%',
            dayMaxEvents: true,
            nowIndicator: true,
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' }
        });

        calendar.render();

        // Filtering Logic
        window.filterEvents = function() {
            const selectedVenue = document.getElementById('venueFilter').value;
            const searchTerm = document.getElementById('calendarSearch').value.toLowerCase();
            const selectedOrgIds = Array.from(document.querySelectorAll('.org-checkbox:checked'))
                                        .map(cb => parseInt(cb.dataset.orgId));
            
            calendar.getEvents().forEach(e => e.remove());
            
            const filtered = coloredEvents.filter(e => {
                const venueMatch = selectedVenue === 'all' || e.extendedProps.venue === selectedVenue;
                const searchMatch = e.title.toLowerCase().includes(searchTerm) || 
                                    e.extendedProps.organization.toLowerCase().includes(searchTerm);
                const orgMatch = selectedOrgIds.includes(e.extendedProps.organization_id);
                
                return venueMatch && searchMatch && orgMatch;
            });
            
            calendar.addEventSource(filtered);
        };

        document.getElementById('venueFilter').addEventListener('change', filterEvents);
        document.getElementById('calendarSearch').addEventListener('input', filterEvents);

        window.toggleAllOrgs = function(state) {
            document.querySelectorAll('.org-checkbox').forEach(cb => cb.checked = state);
            filterEvents();
        };

        window.showEventModal = function(event) {
            const props = event.extendedProps;
            window.currentEventId = event.id;
            window.currentEventImages = props.images || [];
            window.currentEventRequestLetter = props.request_letter_url || null;
            window.modalLightboxIndex = 0;
            
            document.getElementById('modalTitle').textContent = event.title.replace(/^\[.*?\]\s*/, '').replace('⚠️ [CONFLICT] ', '');
            document.getElementById('modalOrgName').textContent = props.organization_full_name || props.organization || 'Organization';
            document.getElementById('modalVenueText').textContent = props.venue || 'No venue specified';
            
            // Date and Time in separate boxes (multi-day aware)
            const startObj = event.start;
            const endObj = event.end || null;
            let dateText = startObj.toLocaleDateString([], { dateStyle: 'medium' });
            let timeText = startObj.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            if (endObj) {
                if (startObj.toDateString() !== endObj.toDateString()) {
                    dateText += ' – ' + endObj.toLocaleDateString([], { dateStyle: 'medium' });
                }
                timeText += ' – ' + endObj.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            }
            document.getElementById('modalDate').textContent = dateText;
            document.getElementById('modalTime').textContent = timeText;
            
            const statusBadge = document.getElementById('modalStatusBadge');
            const actionsEl = document.getElementById('modalActions');
            const modalOrgIcon = document.getElementById('modalOrgIcon');

            if (props.org_logo) {
                modalOrgIcon.innerHTML = `<img src="${props.org_logo}" class="w-full h-full object-cover">`;
            } else {
                modalOrgIcon.innerHTML = `<i class="ph ph-calendar-check text-[#26488C] text-2xl"></i>`;
            }

            const effectiveStatus = props.osas_status || props.status || 'approved';
            statusBadge.textContent = effectiveStatus.toUpperCase();

            const pillBase = 'inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest border ';
            if (effectiveStatus === 'pending') {
                statusBadge.className = pillBase + 'bg-amber-50 text-amber-700 border-amber-200';
                actionsEl.classList.remove('hidden');
            } else if (effectiveStatus === 'rejected') {
                statusBadge.className = pillBase + 'bg-red-50 text-red-700 border-red-200';
                actionsEl.classList.add('hidden');
            } else {
                statusBadge.className = pillBase + 'bg-emerald-50 text-emerald-700 border-emerald-200';
                actionsEl.classList.add('hidden');
            }

            // Category badge (hidden when the event has no category)
            const categoryBadge = document.getElementById('modalCategoryBadge');
            const categoryText = document.getElementById('modalCategoryText');
            const categoryBox = document.getElementById('modalCategoryBox');
            const hasCategory = props.category_label && String(props.category_label).trim() && props.category_label !== '—';
            if (hasCategory) {
                categoryText.textContent = props.category_label;
                document.getElementById('modalVenueCategory').textContent = props.category_label;
                categoryBadge.classList.remove('hidden');
                categoryBox.classList.remove('hidden');
            } else {
                categoryBadge.classList.add('hidden');
                categoryBox.classList.add('hidden');
            }
            
            // Image Preview Thumbnail (clickable)
            const imageContainer = document.getElementById('modalImageContainer');
            const imagePreview = document.getElementById('modalImagePreview');
            const imageCount = document.getElementById('modalImageCount');
            if (props.images && props.images.length > 0) {
                imageContainer.classList.remove('hidden');
                imagePreview.src = window.storageUrl ? window.storageUrl(props.images[0]) : `/storage/${props.images[0]}`;
                imageCount.textContent = props.images.length > 1 ? `${props.images.length} photos` : '1 photo';
            } else {
                imageContainer.classList.add('hidden');
            }

            // Request Letter Preview
            const docContainer = document.getElementById('modalDocContainer');
            if (props.request_letter_url) {
                docContainer.classList.remove('hidden');
                window.currentEventRequestLetter = props.request_letter_url;
            } else {
                docContainer.classList.add('hidden');
            }

            // Planned Budget
            const budgetContainer = document.getElementById('modalBudgetContainer');
            const budgetText = document.getElementById('modalBudgetText');
            if (props.planned_budget !== null && props.planned_budget !== undefined && props.planned_budget !== '') {
                budgetContainer.classList.remove('hidden');
                const parsed = parseFloat(props.planned_budget);
                budgetText.textContent = !isNaN(parsed) ? '₱' + parsed.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00';
            } else {
                budgetContainer.classList.add('hidden');
            }

            // Description Snippet
            const descContainer = document.getElementById('modalDescContainer');
            const descText = document.getElementById('modalDescText');
            if (props.description && props.description.trim()) {
                descContainer.classList.remove('hidden');
                const tmp = document.createElement('DIV');
                tmp.innerHTML = props.description;
                descText.textContent = tmp.textContent || tmp.innerText || '';
            } else {
                descContainer.classList.add('hidden');
            }

            // Conflict Warning
            const conflictWarning = document.getElementById('conflictWarning');
            if (props.hasConflict) {
                conflictWarning.classList.remove('hidden');
            } else {
                conflictWarning.classList.add('hidden');
            }
            
            document.getElementById('modalMessage').onclick = () => {
                // Open the floating chat directly on this org's thread.
                // The calendar modal stays open; the chat floats above it.
                if (typeof window.openOsasChatWithOrg === 'function') {
                    window.openOsasChatWithOrg(props.organization_id, {
                        id: props.organization_id,
                        name: props.organization_full_name || props.organization || 'Organization',
                        logo_url: props.org_logo || null,
                    });
                }
            };
            
            // Show modal with animation
            modal.classList.remove('hidden');
            const surface = modal.querySelector('.modal-surface');
            requestAnimationFrame(() => {
                surface.style.transform = 'scale(1)';
                surface.style.opacity = '1';
            });
        };

        window.closeModal = () => {
            const surface = modal.querySelector('.modal-surface');
            surface.style.transform = 'scale(0.95)';
            surface.style.opacity = '0';
            setTimeout(() => {
                modal.classList.add('hidden');
                surface.style.transform = '';
                surface.style.opacity = '';
            }, 250);
        };

        // Image Lightbox functions
        window.openModalLightbox = function() {
            if (!window.currentEventImages || window.currentEventImages.length === 0) return;
            window.modalLightboxIndex = 0;
            updateLightboxImage();
            document.getElementById('modalLightbox').classList.remove('hidden');
        };

        window.closeModalLightbox = function() {
            document.getElementById('modalLightbox').classList.add('hidden');
        };

        window.modalLightboxPrev = function() {
            if (!window.currentEventImages) return;
            window.modalLightboxIndex = window.modalLightboxIndex > 0 ? window.modalLightboxIndex - 1 : window.currentEventImages.length - 1;
            updateLightboxImage();
        };

        window.modalLightboxNext = function() {
            if (!window.currentEventImages) return;
            window.modalLightboxIndex = window.modalLightboxIndex < window.currentEventImages.length - 1 ? window.modalLightboxIndex + 1 : 0;
            updateLightboxImage();
        };

        function updateLightboxImage() {
            const img = window.currentEventImages[window.modalLightboxIndex];
            const src = window.storageUrl ? window.storageUrl(img) : `/storage/${img}`;
            document.getElementById('modalLightboxImg').src = src;
            document.getElementById('lbCounter').textContent = `${window.modalLightboxIndex + 1} / ${window.currentEventImages.length}`;
            
            const prevBtn = document.getElementById('lbPrevBtn');
            const nextBtn = document.getElementById('lbNextBtn');
            if (window.currentEventImages.length <= 1) {
                prevBtn.classList.add('hidden');
                nextBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
                nextBtn.classList.remove('hidden');
            }
        }

        // Document Viewer functions
        window.openModalDocViewer = function() {
            if (!window.currentEventRequestLetter) return;
            const frame = document.getElementById('modalDocFrame');
            const loader = document.getElementById('modalDocLoader');
            loader.classList.remove('hidden');
            frame.src = window.currentEventRequestLetter;
            document.getElementById('modalDocViewer').classList.remove('hidden');
        };

        window.closeModalDocViewer = function() {
            const viewer = document.getElementById('modalDocViewer');
            const frame = document.getElementById('modalDocFrame');
            viewer.classList.add('hidden');
            frame.src = '';
        };

        window.handleEventAction = async (action) => {
            if (!confirm(`Are you sure you want to ${action} this proposal?`)) return;
            try {
                const response = await fetch(`/osas/proposals/${window.currentEventId}/${action}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: action === 'reject' ? JSON.stringify({ rejection_reason: 'Rejected via Master Calendar review.' }) : null
                });
                const data = await response.json();
                if (data.success) {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                    modal.classList.add('hidden');
                    if (typeof window.refreshCurrentOsasPage === 'function') {
                        await window.refreshCurrentOsasPage();
                    } else {
                        window.location.reload();
                    }
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Something went wrong', 'error');
                    } else {
                        alert(data.message || 'Something went wrong');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        };
    });
</script>
@endpush
