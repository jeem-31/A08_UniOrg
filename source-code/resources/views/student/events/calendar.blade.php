@extends('layouts.app')

@section('title', 'Events Calendar')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<style>
    .fc-button-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    .fc-button-primary:hover {
        background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) !important;
        border-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) !important;
    }
    .fc-button-primary:not(:disabled).fc-button-active {
        background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) !important;
        border-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) !important;
    }
    .fc-event {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }
    .fc-event:hover {
        background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) !important;
    }
    
    /* Mobile responsive calendar */
    @media (max-width: 640px) {
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }
        .fc .fc-toolbar-title {
            font-size: 1.1rem !important;
        }
        .fc .fc-button {
            padding: 0.3rem 0.6rem !important;
            font-size: 0.75rem !important;
        }
        .fc .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
            flex: 1 1 auto;
        }
        .fc-event {
            font-size: 10px !important;
            padding: 1px 2px !important;
        }
        .fc .fc-daygrid-day-number {
            font-size: 0.75rem !important;
            padding: 2px !important;
        }
        .fc .fc-col-header-cell-cushion {
            font-size: 0.7rem !important;
            padding: 4px 2px !important;
        }
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">Events Calendar</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1 sm:mt-2">View all {{ $orgAcronym }} events and activities</p>
        </div>
        <a href="{{ route('events.index') }}" data-student-navigation class="px-4 py-2 bg-[var(--primary-color)] text-white rounded-lg hover:bg-[rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)] transition text-sm sm:text-base self-start sm:self-auto">
            <i class="ph ph-list mr-2 text-[var(--complementary-color)]"></i>List View
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6 overflow-x-auto">
        <div id="calendar"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    (function () {
        var events = @json($events);
        var initialized = false;

        function initializeCalendar() {
            var calendarEl = document.getElementById('calendar');
            if (!calendarEl || initialized) return;
            if (!window.FullCalendar) {
                window.setTimeout(initializeCalendar, 50);
                return;
            }

            initialized = true;
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: window.innerWidth < 640
                    ? { left: 'prev,next', center: 'title', right: 'today' }
                    : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
                footerToolbar: window.innerWidth < 640 ? { center: 'dayGridMonth,listMonth' } : false,
                events: events,
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) window.location.assign(info.event.url);
                },
                eventDisplay: 'block',
                displayEventTime: true,
                height: 'auto'
            });
            calendar.render();
        }

        document.addEventListener('DOMContentLoaded', initializeCalendar, { once: true });
        document.addEventListener('student:navigation-ready', initializeCalendar);
        initializeCalendar();
    })();
</script>
@endpush
