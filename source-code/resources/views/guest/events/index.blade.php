@extends('layouts.guest')

@section('title', 'Events')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<style>
    .fc-button-primary {
        background-color: #8238B3 !important;
        border-color: #8238B3 !important;
    }
    .fc-button-primary:hover {
        background-color: #3D0066 !important;
        border-color: #3D0066 !important;
    }
    .fc-button-primary:not(:disabled).fc-button-active {
        background-color: #3D0066 !important;
        border-color: #3D0066 !important;
    }
    
    /* Custom event colors - no default purple */
    .fc-event {
        cursor: pointer;
        border: none !important;
        padding: 2px 4px !important;
        margin-bottom: 2px !important;
        border-radius: 3px !important;
        font-size: 12px !important;
    }
    
    /* More link styling */
    .fc-more-link {
        color: #8238B3 !important;
        font-weight: 600 !important;
        font-size: 11px !important;
    }
    .fc-more-link:hover {
        color: #3D0066 !important;
        text-decoration: underline !important;
    }
    
    /* Popover for more events */
    .fc-popover {
        border: 2px solid #8238B3 !important;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    }
    .fc-popover-header {
        background-color: #8238B3 !important;
        color: white !important;
        font-weight: 600 !important;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('guest.dashboard') }}" class="inline-flex items-center text-purple-600 hover:text-purple-800 font-semibold transition-colors">
            <i class="ph ph-arrow-left mr-2" style="color: #D7AC28;"></i>
            Back to Home
        </a>
    </div>
    
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Events</h1>
            <p class="text-gray-600 mt-2">Upcoming CCS events and activities</p>
        </div>
        <button id="toggleView" class="px-4 py-2 bg-[#8238B3] text-white rounded-lg hover:bg-[#3D0066] transition">
            <i class="ph ph-list mr-2 text-[#D7AC28]"></i>List View
        </button>
    </div>

    <!-- Calendar View (Default) -->
    <div id="calendarView" class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div id="calendar"></div>
    </div>

    <!-- List View -->
    <div id="listView" class="hidden grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($events as $event)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition flex flex-col h-full">
                @if($event->images && count($event->images) > 0)
                    <div class="relative overflow-hidden h-48">
                        <img src="{{ storage_url($event->images[0]) }}" alt="{{ $event->title }}" class="w-full h-full object-cover">
                        @if(count($event->images) > 1)
                            <div class="absolute top-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded-full text-xs font-semibold">
                                <i class="ph ph-images mr-1"></i>{{ count($event->images) }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="relative h-48 bg-gradient-to-br from-[#8238B3] to-[#3D0066] flex items-center justify-center">
                        <i class="ph ph-calendar-blank text-[#D7AC28] text-6xl"></i>
                    </div>
                @endif
                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $event->title }}</h3>
                    <p class="text-gray-600 text-sm mb-4">{{ Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($event->description)))), 100) }}</p>
                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                        <p><i class="ph ph-calendar mr-2 text-[#D7AC28]"></i>{{ $event->start_date->format('M d, Y') }}</p>
                        <p><i class="ph ph-clock mr-2 text-[#D7AC28]"></i>{{ $event->start_date->format('h:i A') }}</p>
                        <p><i class="ph ph-map-pin mr-2 text-[#D7AC28]"></i>{{ $event->venue }}</p>
                    </div>
                    <div class="mt-auto">
                        <a href="{{ route('guest.events.show', $event) }}" class="block w-full text-center px-4 py-3 bg-[#8238B3] text-white rounded-lg hover:bg-[#3D0066] transition-all duration-200 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <i class="ph ph-eye mr-2 text-[#D7AC28]"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 bg-white rounded-lg shadow p-12 text-center">
                <i class="ph ph-calendar-x text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">No events available at the moment</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8" id="pagination">
        {{ $events->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Color palette: Purple, Gold, Green, Blue with shades (Purple first!)
        const colorPalette = [
            // Purple shades (FIRST)
            { bg: '#8238B3', border: '#6B21A8', text: '#FFFFFF' }, // Purple
            { bg: '#A855F7', border: '#9333EA', text: '#FFFFFF' }, // Light Purple
            { bg: '#6B21A8', border: '#581C87', text: '#FFFFFF' }, // Dark Purple
            { bg: '#C084FC', border: '#A855F7', text: '#1F2937' }, // Very Light Purple
            
            // Gold/Yellow shades
            { bg: '#D7AC28', border: '#B8941F', text: '#1F2937' }, // Gold
            { bg: '#F59E0B', border: '#D97706', text: '#1F2937' }, // Amber
            { bg: '#FBBF24', border: '#F59E0B', text: '#1F2937' }, // Light Gold
            { bg: '#B8941F', border: '#92741A', text: '#FFFFFF' }, // Dark Gold
            
            // Green shades
            { bg: '#10B981', border: '#059669', text: '#FFFFFF' }, // Green
            { bg: '#34D399', border: '#10B981', text: '#1F2937' }, // Light Green
            { bg: '#059669', border: '#047857', text: '#FFFFFF' }, // Dark Green
            { bg: '#6EE7B7', border: '#34D399', text: '#1F2937' }, // Very Light Green
            
            // Blue shades (LAST)
            { bg: '#3B82F6', border: '#2563EB', text: '#FFFFFF' }, // Blue
            { bg: '#60A5FA', border: '#3B82F6', text: '#FFFFFF' }, // Light Blue
            { bg: '#1E40AF', border: '#1E3A8A', text: '#FFFFFF' }, // Dark Blue
            { bg: '#93C5FD', border: '#60A5FA', text: '#1F2937' }  // Very Light Blue
        ];
        
        // Initialize calendar
        var calendarEl = document.getElementById('calendar');
        var eventsData = [
            @foreach($events as $event)
            {
                id: '{{ $event->id }}',
                title: '{{ addslashes($event->title) }}',
                start: '{{ $event->start_date->format('Y-m-d H:i:s') }}',
                end: '{{ $event->end_date ? $event->end_date->format('Y-m-d H:i:s') : '' }}',
                url: '{{ route('guest.events.show', $event) }}'
            },
            @endforeach
        ];
        
        // Calculate duration for each event
        eventsData.forEach(event => {
            const startDate = new Date(event.start);
            const endDate = event.end ? new Date(event.end) : startDate;
            // Calculate duration in milliseconds
            const duration = endDate - startDate;
            // Store duration in extendedProps for eventOrder function
            event.extendedProps = event.extendedProps || {};
            event.extendedProps.duration = duration;
        });
        
        // Sort events by duration (longest first) for color assignment
        eventsData.sort((a, b) => b.extendedProps.duration - a.extendedProps.duration);
        
        // Assign unique colors to each event after sorting
        eventsData.forEach((event, index) => {
            const color = colorPalette[index % colorPalette.length];
            event.backgroundColor = color.bg;
            event.borderColor = color.border;
            event.textColor = color.text;
        });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            events: eventsData,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.location.href = info.event.url;
                }
            },
            eventDisplay: 'block',
            displayEventTime: false,
            height: 'auto',
            dayMaxEvents: 3, // Show only 3 events per day
            moreLinkClick: function(info) {
                // Prevent default FullCalendar popover
                info.jsEvent.preventDefault();
                info.jsEvent.stopPropagation();
                
                // Get ONLY the hidden events
                const hiddenEvents = info.hiddenSegs.map(seg => seg.event);
                
                // Remove any existing custom popover
                const existingPopover = document.querySelector('.custom-event-popover');
                if (existingPopover) existingPopover.remove();
                
                // Create custom popover
                const popoverDiv = document.createElement('div');
                popoverDiv.className = 'custom-event-popover';
                popoverDiv.style.cssText = 'position: fixed; z-index: 10000; background: white; border: 2px solid #8238B3; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 12px; min-width: 200px; max-width: 300px;';
                
                // Header
                const header = document.createElement('div');
                header.style.cssText = 'font-weight: bold; margin-bottom: 8px; color: #8238B3; font-size: 14px; border-bottom: 2px solid #8238B3; padding-bottom: 6px;';
                header.textContent = 'More Events (' + hiddenEvents.length + ')';
                popoverDiv.appendChild(header);
                
                // Events list
                hiddenEvents.forEach(event => {
                    const eventDiv = document.createElement('div');
                    eventDiv.style.cssText = 'background-color: ' + (event.backgroundColor || '#8238B3') + '; color: ' + (event.textColor || '#FFFFFF') + '; padding: 8px 10px; margin-bottom: 4px; border-radius: 4px; cursor: pointer; font-size: 13px; transition: opacity 0.2s;';
                    eventDiv.textContent = event.title;
                    eventDiv.onmouseover = function() { this.style.opacity = '0.8'; };
                    eventDiv.onmouseout = function() { this.style.opacity = '1'; };
                    eventDiv.onclick = function(e) {
                        e.stopPropagation();
                        if (event.url) {
                            window.location.href = event.url;
                        }
                    };
                    popoverDiv.appendChild(eventDiv);
                });
                
                // Position the popover
                const rect = info.jsEvent.target.getBoundingClientRect();
                popoverDiv.style.left = rect.left + 'px';
                popoverDiv.style.top = (rect.bottom + 5) + 'px';
                
                document.body.appendChild(popoverDiv);
                
                // Close on click outside
                setTimeout(() => {
                    const closeHandler = function(e) {
                        if (!popoverDiv.contains(e.target) && !e.target.classList.contains('fc-more-link')) {
                            popoverDiv.remove();
                            document.removeEventListener('click', closeHandler);
                        }
                    };
                    document.addEventListener('click', closeHandler);
                }, 100);
                
                return 'prevent-default'; // Explicitly prevent FullCalendar's default popover
            },
            moreLinkContent: function(args) {
                // args.num is the number of HIDDEN events (not total)
                return '+' + args.num + ' more';
            },
            eventDidMount: function(info) {
                // Add tooltip showing event title on hover
                info.el.title = info.event.title;
            },
            eventOrder: function(a, b) {
                // Sort by duration (longest events at the top)
                const durationA = a.extendedProps.duration || 0;
                const durationB = b.extendedProps.duration || 0;
                return durationB - durationA; // Descending order (longest first)
            }
        });

        calendar.render();

        // Toggle between calendar and list view
        const toggleBtn = document.getElementById('toggleView');
        const calendarView = document.getElementById('calendarView');
        const listView = document.getElementById('listView');
        const pagination = document.getElementById('pagination');
        let isCalendarView = true;

        toggleBtn.addEventListener('click', function() {
            isCalendarView = !isCalendarView;
            
            if (isCalendarView) {
                calendarView.classList.remove('hidden');
                listView.classList.add('hidden');
                pagination.classList.add('hidden');
                toggleBtn.innerHTML = '<i class="ph ph-list mr-2 text-[#D7AC28]"></i>List View';
            } else {
                calendarView.classList.add('hidden');
                listView.classList.remove('hidden');
                pagination.classList.remove('hidden');
                toggleBtn.innerHTML = '<i class="ph ph-calendar-blank mr-2 text-[#D7AC28]"></i>Calendar View';
            }
        });

        // Hide pagination initially since calendar is default
        pagination.classList.add('hidden');
    });
</script>
@endpush
