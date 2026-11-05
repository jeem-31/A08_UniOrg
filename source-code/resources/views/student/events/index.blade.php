@extends('layouts.app')

@section('title', 'Events')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
@endpush

@section('content')
<div class="student-events-page min-h-screen">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8" x-data="{
    events: {{ Js::from($events->items()) }},
    isCalendarView: true,
    calendar: null,
    resolveColor(value) {
        if (!value) return null;
        const cleaned = value.trim();
        if (cleaned.startsWith('#')) {
            let hex = cleaned.replace('#', '');
            if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
            if (hex.length === 6) {
                return { r: parseInt(hex.slice(0, 2), 16), g: parseInt(hex.slice(2, 4), 16), b: parseInt(hex.slice(4, 6), 16) };
            }
        }
        const rgbMatch = cleaned.match(/rgba?\(([^)]+)\)/);
        if (rgbMatch) {
            const parts = rgbMatch[1].split(',').map(p => parseFloat(p.trim()));
            if (parts.length >= 3 && !isNaN(parts[0])) {
                return { r: parts[0], g: parts[1], b: parts[2] };
            }
        }
        return null;
    },
    getEventColor() {
        const raw = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#8238B3';
        const rgb = this.resolveColor(raw);
        const brightness = rgb ? (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) : 128;
        const text = brightness > 150 ? '#111827' : '#FFFFFF';
        return { bg: raw, border: raw, text: text };
    },
    addEvent(newEvent) {
        this.events.unshift(newEvent);
        if (this.calendar) {
            this.updateCalendar();
        }
    },
    updateEvent(updatedEvent) {
        const index = this.events.findIndex(e => e.id === updatedEvent.id);
        if (index !== -1) {
            this.events[index] = updatedEvent;
            if (this.calendar) {
                this.updateCalendar();
            }
        }
    },
    removeEvent(eventId) {
        const index = this.events.findIndex(e => e.id === eventId);
        if (index !== -1) {
            this.events.splice(index, 1);
            if (this.calendar) {
                this.updateCalendar();
            }
        }
    },
    updateCalendar() {
        if (!this.calendar) return;
        
        // Remove all existing events
        this.calendar.getEvents().forEach(event => event.remove());
        
        // Prepare events data
        let eventsData = this.events.map(event => ({
            id: event.id,
            title: event.title,
            start: event.start_date,
            end: event.end_date || event.start_date,
            url: '/events/' + event.id,
            extendedProps: {
                venue: event.venue || '',
                description: event.description || ''
            }
        }));
        
        // Calculate duration and sort
        eventsData.forEach(event => {
            const startDate = new Date(event.start);
            const endDate = new Date(event.end);
            event.extendedProps.duration = endDate - startDate;
        });
        eventsData.sort((a, b) => b.extendedProps.duration - a.extendedProps.duration);
        
        // Assign colors - single primary color with auto-contrast text
        const eventColor = this.getEventColor();
        eventsData.forEach(event => {
            event.backgroundColor = eventColor.bg;
            event.borderColor = eventColor.border;
            event.textColor = eventColor.text;
        });
        
        // Add events to calendar
        eventsData.forEach(event => this.calendar.addEvent(event));
    },
    toggleView() {
        this.isCalendarView = !this.isCalendarView;
    }
}" x-init="
    // Listen for real-time updates
    if (window.Echo) {
        window.Echo.channel('events')
            .listen('.event-created', (e) => {
                console.log('✅ Student received: event-created', e);
                fetch('/events/' + e.id + '/json')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addEvent(data.event);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            })
            .listen('.event-updated', (e) => {
                console.log('✅ Student received: event-updated', e);
                fetch('/events/' + e.id + '/json')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateEvent(data.event);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            })
            .listen('.event-deleted', (e) => {
                console.log('✅ Student received: event-deleted', e);
                removeEvent(e.id);
            });
    }
">
        <header class="events-hero">
            <div class="events-hero__copy">
                <p class="events-eyebrow">Upcoming activities</p>
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">Events</h1>
                    <button type="button" @click="toggleView()" class="events-view-toggle events-view-toggle--mobile" aria-label="Switch between calendar and list view">
                        <span class="events-view-toggle__icon"><i class="ph" :class="isCalendarView ? 'ph-list' : 'ph-calendar-blank'" aria-hidden="true"></i></span>
                        <span x-text="isCalendarView ? 'List View' : 'Calendar View'"></span>
                    </button>
                </div>
            </div>
            <div class="events-hero__actions">
                <button type="button" @click="toggleView()" class="events-view-toggle events-view-toggle--desktop" aria-label="Switch between calendar and list view">
                    <span class="events-view-toggle__icon"><i class="ph" :class="isCalendarView ? 'ph-list' : 'ph-calendar-blank'" aria-hidden="true"></i></span>
                    <span x-text="isCalendarView ? 'List View' : 'Calendar View'"></span>
                </button>
            </div>
        </header>

        <!-- Calendar View (Default) -->
        <section x-show="isCalendarView" class="events-panel" aria-label="Events calendar">
            <div class="events-panel__heading">
                <div>
                    <p class="events-eyebrow">Schedule</p>
                    <p>Browse the monthly overview — click any event to open the full details.</p>
                </div>
            </div>
            <div id="calendar"></div>
        </section>

        <!-- List View -->
        <section x-show="!isCalendarView" class="events-list-section" aria-label="Events list">
            <div class="events-section__heading">
                <div>
                    <p class="events-eyebrow">All events</p>
                    <p>Everything happening for your organization, newest first.</p>
                </div>
            </div>
            <div class="events-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <template x-for="(event, i) in events" :key="event.id">
                    <article class="event-card" :style="{ '--event-index': i }">
                        <template x-if="event.images && event.images.length > 0">
                            <div class="event-card__media relative overflow-hidden">
                                <img :src="window.storageUrl(event.images[0])" :alt="event.title" class="event-card__image w-full h-full object-cover">
                                <template x-if="event.images.length > 1">
                                    <div class="absolute top-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded-full text-xs font-semibold">
                                        <i class="ph ph-images mr-1"></i><span x-text="event.images.length"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!event.images || event.images.length === 0">
                            <div class="event-card__placeholder relative h-48 flex items-center justify-center">
                                <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                            </div>
                        </template>
                        <div class="event-card__body">
                            <h3 class="mb-2 line-clamp-2" x-text="event.title"></h3>
                            <p class="event-card__description mb-4 line-clamp-2" x-text="event.description ? event.description.replace(/<[^>]*>/g, '').substring(0, 100) + '...' : ''"></p>
                            <div class="event-card__meta space-y-2 mb-4 text-sm">
                                <p class="flex items-center gap-2"><i class="ph ph-calendar"></i><span x-text="new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })"></span></p>
                                <p class="flex items-center gap-2"><i class="ph ph-clock"></i><span x-text="new Date(event.start_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })"></span></p>
                                <p class="flex items-center gap-2"><i class="ph ph-map-pin"></i><span x-text="event.venue"></span></p>
                            </div>
                            <div class="mt-auto">
                                <a :href="'/events/' + event.id" data-student-navigation class="event-details-btn block w-full text-center px-4 py-3 text-white text-sm font-semibold">
                                    <i class="ph ph-eye mr-2 text-[var(--complementary-color)]"></i>View Details
                                </a>
                            </div>
                        </div>
                    </article>
                </template>

                <!-- Empty state -->
                <template x-if="events.length === 0">
                    <div class="events-empty col-span-full" aria-label="No events">
                        <span><i class="ph ph-calendar-x" aria-hidden="true"></i></span>
                        <p class="events-eyebrow">Nothing scheduled</p>
                        <h2>No upcoming events.</h2>
                        <p>There are no events for your organization right now. Check back later for new activities.</p>
                    </div>
                </template>
            </div>
        </section>

        <div class="events-pagination mt-8" id="pagination" data-student-navigation-pagination>
            {{ $events->links() }}
        </div>
    </div>
</div>
<style>
    .student-events-page {
        --neu-bg: #E9EDF3;
        --neu-text: #2E3A4C;
        --neu-muted: #5A6B80;
        --neu-faint: #7C8A9E;
        --neu-divider: rgba(163, 177, 198, .45);
        --neu-dark-shadow: rgba(163, 177, 198, .6);
        --neu-light-shadow: rgba(255, 255, 255, .85);
        min-height: calc(100vh + 4rem) !important;
        margin-top: -2rem;
        margin-bottom: -2rem;
        background: var(--neu-bg);
        color: var(--neu-text);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .student-events-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

    .events-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
    }

    .events-hero__copy { max-width: 44rem; }
    .events-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #0071e3);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .events-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }

    .events-view-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        min-height: 2.9rem;
        padding: .42rem 1rem .42rem .5rem;
        border: 0;
        border-radius: 999px;
        background: var(--neu-bg);
        box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow);
        color: var(--neu-text);
        font-size: .9rem;
        font-weight: 600;
        letter-spacing: -.01em;
        white-space: nowrap;
        transition: box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease;
    }

    .events-view-toggle__icon {
        display: inline-flex;
        width: 2rem;
        height: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .13);
        color: var(--primary-color, #0071e3);
        font-size: 1.05rem;
        box-shadow: inset 2px 2px 4px rgba(var(--primary-color-rgb, 0, 113, 227), .12), inset -2px -2px 4px rgba(255, 255, 255, .6);
    }

    .events-view-toggle:hover,
    .events-view-toggle:focus-visible { box-shadow: inset 3px 3px 8px var(--neu-dark-shadow), inset -3px -3px 8px var(--neu-light-shadow); }

    .events-view-toggle--desktop { display: none; }
    @media (min-width: 640px) {
        .events-view-toggle--desktop { display: inline-flex; }
        .events-view-toggle--mobile { display: none; }
    }

    .events-panel,
    .events-list-section {
        padding: clamp(1.25rem, 3vw, 2rem);
        border: 0;
        border-radius: 1.5rem;
        background: var(--neu-bg);
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow);
        animation: events-panel-in .6s both cubic-bezier(.2, .8, .2, 1);
    }

    .events-panel__heading,
    .events-section__heading { margin-bottom: 1.5rem; }
    .events-panel__heading > div > p:last-child,
    .events-section__heading > div > p:last-child {
        max-width: 37rem;
        margin: .65rem 0 0;
        color: var(--neu-muted);
        font-size: .92rem;
        line-height: 1.55;
    }

    .events-grid { align-items: stretch; }

    .event-card {
        display: flex;
        height: 100%;
        flex-direction: column;
        overflow: hidden;
        border: 0;
        border-radius: 1.5rem;
        background: var(--neu-bg);
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow);
        transition: transform .32s cubic-bezier(.2, .8, .2, 1), box-shadow .32s cubic-bezier(.2, .8, .2, 1);
        animation: event-card-in .65s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--event-index, 0) * 55ms);
    }

    .event-card:hover {
        transform: translateY(-3px);
        box-shadow: 12px 12px 24px var(--neu-dark-shadow), -12px -12px 24px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .22);
    }

    .event-card__media { height: 12.5rem; }
    .event-card__image { transition: transform .55s cubic-bezier(.2, .8, .2, 1); }
    .event-card:hover .event-card__image { transform: scale(1.045); }
    .event-card__placeholder {
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .10);
        box-shadow: inset 5px 5px 12px rgba(var(--primary-color-rgb, 0, 113, 227), .14), inset -5px -5px 12px rgba(255, 255, 255, .6);
    }
    .event-card__placeholder > i { color: var(--primary-color, #0071e3); font-size: 3.25rem; opacity: .68; }

    .event-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        padding: 1.15rem 1.25rem 1.3rem;
    }

    .event-card h3 {
        color: var(--neu-text);
        font-size: 1.08rem;
        font-weight: 800;
        letter-spacing: -.02em;
        line-height: 1.28;
    }
    .event-card__description { color: var(--neu-muted); font-size: .88rem; line-height: 1.5; }
    .event-card__meta { color: var(--neu-muted); font-size: .85rem; }
    .event-card__meta i { color: var(--primary-color, #0071e3); opacity: .75; font-size: .95rem; }

    .event-details-btn {
        border-radius: .9rem;
        background: var(--primary-color, #0071e3);
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
    }
    .event-details-btn:hover { transform: translateY(-2px); filter: brightness(1.03); }

    .events-empty {
        max-width: 38rem;
        margin: 2rem auto 1rem;
        padding: 3rem 2rem;
        border: 0;
        border-radius: 1.75rem;
        background: var(--neu-bg);
        box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8);
        text-align: center;
    }

    .events-empty > span {
        display: inline-flex;
        width: 3.7rem;
        height: 3.7rem;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
        border-radius: 50%;
        background: rgba(var(--primary-color-rgb, 0, 113, 227), .13);
        color: var(--primary-color, #0071e3);
        font-size: 2rem;
        box-shadow: 5px 5px 10px rgba(var(--primary-color-rgb, 0, 113, 227), .25), -5px -5px 10px rgba(255, 255, 255, .9);
    }
    .events-empty h2 {
        margin: 0;
        color: var(--neu-text);
        font-size: clamp(1.5rem, 3vw, 1.9rem);
        font-weight: 700;
        letter-spacing: -.045em;
    }
    .events-empty > p:last-child {
        max-width: 28rem;
        margin: .8rem auto 0;
        color: var(--neu-muted);
        line-height: 1.55;
    }

    /* FullCalendar neumorphic styling */
    .events-panel .fc { font-family: inherit; color: var(--neu-text); }
    .events-panel .fc .fc-button {
        border: 0 !important;
        border-radius: 999px !important;
        background: var(--neu-bg) !important;
        color: var(--neu-text) !important;
        font-weight: 600 !important;
        padding: .38rem .85rem !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        transition: box-shadow .28s cubic-bezier(.2, .8, .2, 1), background-color .28s ease, color .28s ease;
    }
    .events-panel .fc .fc-button:hover,
    .events-panel .fc .fc-button:focus {
        background: var(--neu-bg) !important;
        color: var(--primary-color, #0071e3) !important;
        box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
    }
    .events-panel .fc .fc-button.fc-button-active,
    .events-panel .fc .fc-button-primary:not(:disabled):active,
    .events-panel .fc .fc-button-primary:not(:disabled).fc-button-active {
        background: var(--primary-color, #0071e3) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -4px -4px 10px rgba(255, 255, 255, .85) !important;
    }
    .events-panel .fc .fc-toolbar-title {
        color: var(--neu-text) !important;
        font-size: 1.35rem !important;
        font-weight: 700 !important;
        letter-spacing: -.02em !important;
    }
    .events-panel .fc .fc-scrollgrid,
    .events-panel .fc .fc-daygrid-day,
    .events-panel .fc .fc-daygrid-day-frame { border-color: var(--neu-divider) !important; }
    .events-panel .fc .fc-scrollgrid,
    .events-panel .fc .fc-list-table,
    .events-panel .fc .fc-list,
    .events-panel .fc .fc-list-event,
    .events-panel .fc .fc-timegrid-slot { background: transparent !important; }
    .events-panel .fc .fc-list-day-cushion { background: rgba(163, 177, 198, .18) !important; color: var(--neu-muted) !important; }
    .events-panel .fc .fc-col-header-cell-cushion {
        color: var(--neu-muted) !important;
        font-size: .75rem !important;
        font-weight: 600 !important;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .events-panel .fc .fc-daygrid-day-number {
        color: var(--neu-text) !important;
        font-size: .82rem !important;
    }
    .events-panel .fc .fc-daygrid-day.fc-day-other .fc-daygrid-day-number { color: rgba(163, 177, 198, .65) !important; }
    .events-panel .fc .fc-day-today { background: rgba(var(--primary-color-rgb, 0, 113, 227), .06) !important; }

    .events-panel .fc .fc-event {
        border: 0 !important;
        border-radius: .45rem !important;
        padding: 2px 6px !important;
        margin: 0 2px 2px !important;
        font-size: 11.5px !important;
        font-weight: 600 !important;
        box-shadow: 0 2px 6px rgba(163, 177, 198, .5) !important;
        transition: transform .2s ease, filter .2s ease;
    }
    .events-panel .fc .fc-event:hover { transform: translateY(-1px); filter: brightness(1.06); }

    .events-panel .fc .fc-more-link {
        color: var(--primary-color, #0071e3) !important;
        font-weight: 600 !important;
        font-size: 11px !important;
    }
    .events-panel .fc .fc-popover {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 10px 10px 24px var(--neu-dark-shadow), -10px -10px 24px var(--neu-light-shadow) !important;
        overflow: hidden;
    }
    .events-panel .fc .fc-popover-header {
        background: #DEE4EE !important;
        color: var(--neu-text) !important;
        font-weight: 700 !important;
        padding: .6rem .8rem !important;
    }
    .events-panel .fc .fc-popover-body { padding: .35rem !important; }

    .events-view-toggle:focus-visible,
    .event-details-btn:focus-visible,
    .events-panel .fc .fc-button:focus-visible {
        outline: 3px solid rgba(var(--primary-color-rgb, 0, 113, 227), .35);
        outline-offset: 3px;
    }

    /* Custom Pagination Styles */
    .events-pagination nav div:last-child a.relative,
    .events-pagination nav div:last-child span.relative {
        background: var(--neu-bg) !important;
        border-color: transparent !important;
        color: var(--neu-muted) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        border-radius: .7rem !important;
        margin: 0 .2rem;
    }

    .events-pagination nav div:last-child span.relative.z-10 {
        background: var(--primary-color) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -4px -4px 10px rgba(255, 255, 255, .85) !important;
        font-weight: bold;
    }

    .events-pagination nav div:last-child a.relative:not(.z-10):hover {
        background: var(--neu-bg) !important;
        color: var(--primary-color) !important;
        box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
    }

    .events-pagination nav div:last-child a[rel="next"],
    .events-pagination nav div:last-child span[aria-label="Next &raquo;"],
    .events-pagination nav div:last-child a[rel="prev"],
    .events-pagination nav div:last-child span[aria-label="&laquo; Previous"] {
        color: var(--complementary-color) !important;
    }

    .events-pagination nav div:last-child a[rel="next"] svg,
    .events-pagination nav div:last-child a[rel="next"] i,
    .events-pagination nav div:last-child a[rel="prev"] svg,
    .events-pagination nav div:last-child a[rel="prev"] i {
        color: var(--complementary-color) !important;
    }

    @keyframes event-card-in { from { opacity: 0; transform: translateY(14px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes events-panel-in { from { opacity: 0; transform: translateY(12px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-events-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-events-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .events-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
        .events-hero__copy { width: 100%; }
        .events-panel, .events-list-section { border-radius: 1.25rem; }
        .event-card { border-radius: 1.25rem; }
        .event-card__media, .event-card__placeholder { height: 11rem; }
        .fc .fc-toolbar { flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
        .fc .fc-toolbar-title { font-size: 1.1rem !important; }
        .fc .fc-button { padding: 0.3rem 0.6rem !important; font-size: 0.75rem !important; }
        .fc .fc-toolbar-chunk { display: flex; justify-content: center; flex: 1 1 auto; }
        .fc-event { font-size: 10px !important; padding: 1px 2px !important; }
        .fc .fc-daygrid-day-number { font-size: 0.75rem !important; padding: 2px !important; }
        .fc .fc-col-header-cell-cushion { font-size: 0.7rem !important; padding: 4px 2px !important; }
        .fc-more-link { font-size: 9px !important; }
    }

    @media (min-width: 640px) {
        .events-hero h1 { font-size: 1.875rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .events-panel, .events-list-section, .event-card { animation: none !important; }
        .event-card, .event-card__image, .events-view-toggle, .event-details-btn,
        .events-panel .fc .fc-event, .events-panel .fc .fc-button { transition-duration: .01ms !important; }
    }

    /* Event hover tooltip */
    #event-tooltip {
        position: fixed;
        z-index: 100000;
        max-width: 300px;
        min-width: 180px;
        background: var(--neu-bg, #E9EDF3);
        border-radius: 1rem;
        box-shadow: 8px 8px 20px rgba(163,177,198,.55), -8px -8px 20px rgba(255,255,255,.9);
        padding: 12px 14px;
        pointer-events: none;
        opacity: 0;
        transform: translateY(6px) scale(.98);
        transition: opacity .18s ease, transform .18s ease;
    }
    #event-tooltip.event-tooltip--visible {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    #event-tooltip::after {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        border: 7px solid transparent;
    }
    #event-tooltip[data-pos="top"]::after {
        top: 100%;
        border-top-color: var(--neu-bg, #E9EDF3);
    }
    #event-tooltip[data-pos="bottom"]::after {
        bottom: 100%;
        border-bottom-color: var(--neu-bg, #E9EDF3);
    }
    .event-tooltip__header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 800;
        font-size: 13px;
        color: var(--neu-text, #2E3A4C);
        padding-bottom: 8px;
        margin-bottom: 8px;
        border-bottom: 1px solid rgba(163,177,198,.45);
        letter-spacing: -.01em;
        line-height: 1.3;
    }
    .event-tooltip__header i {
        color: var(--primary-color, #8238B3);
        font-size: 16px;
        flex-shrink: 0;
    }
    .event-tooltip__row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--neu-muted, #5A6B80);
        padding: 3px 0;
        line-height: 1.4;
    }
    .event-tooltip__row i {
        color: var(--primary-color, #8238B3);
        font-size: 14px;
        opacity: .85;
        flex-shrink: 0;
    }
    .event-tooltip__row span { min-width: 0; overflow-wrap: anywhere; }
</style>
@endsection

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function showEventTooltip(info) {
        hideEventTooltip();

        var event = info.event;
        var title = event.title || 'Event';
        var start = event.start;
        var end = event.end;
        var venue = (event.extendedProps && event.extendedProps.venue) || '';

        var dateLine = '';
        if (start) {
            var startStr = start.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
            var endStr = end && end.getTime() !== start.getTime()
                ? end.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })
                : null;
            dateLine = endStr ? startStr + ' – ' + endStr : startStr;
        }

        var tooltip = document.createElement('div');
        tooltip.id = 'event-tooltip';

        var venueRow = venue
            ? '<div class="event-tooltip__row"><i class="ph ph-map-pin"></i><span>' + escapeHtml(venue) + '</span></div>'
            : '';

        tooltip.innerHTML =
            '<div class="event-tooltip__header"><i class="ph ph-calendar-blank"></i><span>' + escapeHtml(title) + '</span></div>' +
            (dateLine ? '<div class="event-tooltip__row"><i class="ph ph-clock"></i><span>' + escapeHtml(dateLine) + '</span></div>' : '') +
            venueRow;

        document.body.appendChild(tooltip);

        // Position relative to the hovered event element
        var rect = info.el.getBoundingClientRect();
        var tipRect = tooltip.getBoundingClientRect();
        var left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        var top = rect.top - tipRect.height - 10;
        var pos = 'top';

        if (top < 8) {
            top = rect.bottom + 10;
            pos = 'bottom';
        }

        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        top = Math.max(8, top);

        tooltip.setAttribute('data-pos', pos);
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';

        requestAnimationFrame(function () {
            tooltip.classList.add('event-tooltip--visible');
        });
    }

    function hideEventTooltip() {
        var tooltip = document.getElementById('event-tooltip');
        if (tooltip) tooltip.remove();
    }

    function initEventsPage() {
        // Check if FullCalendar is loaded
        if (typeof FullCalendar === 'undefined') {
            console.log('FullCalendar not loaded yet, retrying...');
            setTimeout(initEventsPage, 100);
            return;
        }
        
        // Initialize calendar
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;
        
        // Get Alpine component
        const alpineComponent = Alpine.$data(calendarEl.closest('[x-data]'));
        if (!alpineComponent) return;
        
        // Prepare events data
        let eventsData = alpineComponent.events.map(event => ({
            id: event.id,
            title: event.title,
            start: event.start_date,
            end: event.end_date || event.start_date,
            url: '/events/' + event.id,
            extendedProps: {
                venue: event.venue || '',
                description: event.description || ''
            }
        }));
        
        // Calculate duration for each event
        eventsData.forEach(event => {
            const startDate = new Date(event.start);
            const endDate = new Date(event.end);
            event.extendedProps.duration = endDate - startDate;
        });
        
        // Sort events by duration (longest first)
        eventsData.sort((a, b) => b.extendedProps.duration - a.extendedProps.duration);
        
        // Assign colors - single primary color with auto-contrast text
        const eventColor = alpineComponent.getEventColor();
        eventsData.forEach(event => {
            event.backgroundColor = eventColor.bg;
            event.borderColor = eventColor.border;
            event.textColor = eventColor.text;
        });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: window.innerWidth < 640 
                ? { left: 'prev,next', center: 'title', right: 'today' }
                : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
            footerToolbar: window.innerWidth < 640 
                ? { center: 'dayGridMonth,listMonth' }
                : false,
            events: eventsData,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    const navigationLink = document.createElement('a');
                    navigationLink.href = info.event.url;
                    navigationLink.setAttribute('data-student-navigation', '');
                    navigationLink.className = 'hidden';
                    document.body.appendChild(navigationLink);
                    navigationLink.click();
                    navigationLink.remove();
                }
            },
            eventDisplay: 'block',
            displayEventTime: false,
            height: 'auto',
            dayMaxEvents: 3,
            moreLinkClick: function(info) {
                info.jsEvent.preventDefault();
                info.jsEvent.stopPropagation();
                
                const hiddenEvents = info.hiddenSegs.map(seg => seg.event);
                const existingPopover = document.querySelector('.custom-event-popover');
                if (existingPopover) existingPopover.remove();
                
                const popoverDiv = document.createElement('div');
                popoverDiv.className = 'custom-event-popover';
                popoverDiv.style.cssText = 'position: fixed; z-index: 10000; background: rgba(233,237,243,.98); border: 0; border-radius: 1rem; box-shadow: 10px 10px 24px rgba(163,177,198,.55), -10px -10px 24px rgba(255,255,255,.85); padding: 12px; min-width: 200px; max-width: 300px;';
                
                const header = document.createElement('div');
                header.style.cssText = 'font-weight: 700; margin-bottom: 8px; color: var(--primary-color); font-size: 13px; border-bottom: 1px solid rgba(163,177,198,.45); padding-bottom: 8px; letter-spacing: -.01em;';
                header.textContent = 'More Events (' + hiddenEvents.length + ')';
                popoverDiv.appendChild(header);
                
                hiddenEvents.forEach(event => {
                    const eventDiv = document.createElement('div');
                    eventDiv.style.cssText = 'background-color: ' + (event.backgroundColor || 'var(--primary-color)') + '; color: ' + (event.textColor || '#FFFFFF') + '; padding: 8px 10px; margin-bottom: 4px; border-radius: 8px; cursor: pointer; font-size: 13px; transition: opacity 0.2s;';
                    eventDiv.textContent = event.title;
                    eventDiv.onmouseover = function() { this.style.opacity = '0.8'; };
                    eventDiv.onmouseout = function() { this.style.opacity = '1'; };
                    eventDiv.onclick = function(e) {
                        e.stopPropagation();
                        if (event.url) {
                            const navigationLink = document.createElement('a');
                            navigationLink.href = event.url;
                            navigationLink.setAttribute('data-student-navigation', '');
                            navigationLink.className = 'hidden';
                            document.body.appendChild(navigationLink);
                            navigationLink.click();
                            navigationLink.remove();
                        }
                    };
                    popoverDiv.appendChild(eventDiv);
                });
                
                const rect = info.jsEvent.target.getBoundingClientRect();
                popoverDiv.style.left = rect.left + 'px';
                popoverDiv.style.top = (rect.bottom + 5) + 'px';
                document.body.appendChild(popoverDiv);
                
                setTimeout(() => {
                    const closeHandler = function(e) {
                        if (!popoverDiv.contains(e.target) && !e.target.classList.contains('fc-more-link')) {
                            popoverDiv.remove();
                            document.removeEventListener('click', closeHandler);
                        }
                    };
                    document.addEventListener('click', closeHandler);
                }, 100);
                
                return 'prevent-default';
            },
            moreLinkContent: function(args) {
                return '+' + args.num + ' more';
            },
            eventDidMount: function(info) {
                info.el.setAttribute('aria-label', info.event.title);
            },
            eventMouseEnter: function(info) {
                showEventTooltip(info);
            },
            eventMouseLeave: function() {
                hideEventTooltip();
            },
            eventOrder: function(a, b) {
                const durationA = a.extendedProps.duration || 0;
                const durationB = b.extendedProps.duration || 0;
                return durationB - durationA;
            }
        });

        calendar.render();
        
        // Store calendar instance in Alpine component
        alpineComponent.calendar = calendar;
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initEventsPage);
    
    
</script>
@endpush
