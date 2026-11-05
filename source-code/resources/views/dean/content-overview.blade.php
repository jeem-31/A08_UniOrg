@extends('layouts.dean')

@section('title', 'Content Overview')

@php
    $announcementCount = method_exists($announcements, 'total') ? $announcements->total() : $announcements->count();
    $eventCount = method_exists($events, 'total') ? $events->total() : $events->count();
    $complaintCount = method_exists($complaints, 'total') ? $complaints->total() : $complaints->count();
    $complaintRecords = method_exists($complaints, 'getCollection') ? $complaints->getCollection() : collect($complaints);
    $openComplaintCount = $complaintRecords->where('status', 'pending')->count();
@endphp

@section('content')
<div class="dean-content-page" data-content-overview>
    <div class="dean-content-page__shell">
        <header class="dean-content-header">
            <div>
                <div class="dean-content-eyebrow">Content operations</div>
                <h1>Content overview</h1>
                <p>Review department updates, scheduled activities, and concerns from one focused workspace.</p>
            </div>
            <div class="dean-content-header__actions">
                <a href="{{ route('dean.dashboard') }}" class="dean-content-btn dean-content-btn--secondary">
                    <i class="ph ph-arrow-left"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('dean.proposals.index') }}" class="dean-content-btn dean-content-btn--primary">
                    <i class="ph ph-clipboard-text"></i><span>Review proposals</span>
                </a>
            </div>
        </header>

        <section class="dean-content-metrics" aria-label="Content totals">
            <button type="button" class="dean-content-metric" data-content-tab="announcements" data-active-class="dean-content-metric--purple">
                <span class="dean-content-metric__icon dean-content-metric__icon--purple"><i class="ph ph-megaphone"></i></span>
                <span class="dean-content-metric__body"><span>Announcements</span><strong>{{ number_format($announcementCount) }}</strong><small>Department updates</small></span>
                <i class="ph ph-arrow-right dean-content-metric__arrow"></i>
            </button>
            <button type="button" class="dean-content-metric" data-content-tab="events" data-active-class="dean-content-metric--blue">
                <span class="dean-content-metric__icon dean-content-metric__icon--blue"><i class="ph ph-calendar-check"></i></span>
                <span class="dean-content-metric__body"><span>Events</span><strong>{{ number_format($eventCount) }}</strong><small>Approved activities</small></span>
                <i class="ph ph-arrow-right dean-content-metric__arrow"></i>
            </button>
            <button type="button" class="dean-content-metric" data-content-tab="complaints" data-active-class="dean-content-metric--red">
                <span class="dean-content-metric__icon dean-content-metric__icon--red"><i class="ph ph-flag"></i></span>
                <span class="dean-content-metric__body"><span>Complaints</span><strong>{{ number_format($complaintCount) }}</strong><small>{{ number_format($openComplaintCount) }} open on this page</small></span>
                <i class="ph ph-arrow-right dean-content-metric__arrow"></i>
            </button>
        </section>

        <div class="dean-content-tabs" role="tablist" aria-label="Content type">
            <button type="button" role="tab" data-content-tab="announcements" data-active-class="" aria-selected="false">
                <i class="ph ph-megaphone"></i><span>Announcements</span><b>{{ number_format($announcementCount) }}</b>
            </button>
            <button type="button" role="tab" data-content-tab="events" data-active-class="" aria-selected="false">
                <i class="ph ph-calendar-blank"></i><span>Events</span><b>{{ number_format($eventCount) }}</b>
            </button>
            <button type="button" role="tab" data-content-tab="complaints" data-active-class="dean-content-tabs--red" aria-selected="false">
                <i class="ph ph-warning"></i><span>Complaints</span><b>{{ number_format($complaintCount) }}</b>
            </button>
        </div>

        <div class="dean-content-workspace">
            <main class="dean-content-panel">
                <div class="dean-content-panel__header">
                    <div>
                        <span class="dean-content-panel__eyebrow">CONTENT LIBRARY</span>
                        <h2 data-content-title>Announcements</h2>
                        <p data-content-description="announcements">Latest notices published for the department community.</p>
                        <p data-content-description="events" hidden>Approved activities and their current publication status.</p>
                        <p data-content-description="complaints" hidden>Concerns submitted by students and organization members.</p>
                    </div>
                    <span class="dean-content-panel__count"><span data-content-count="announcements">{{ number_format($announcementCount) }}</span><span data-content-count="events" hidden>{{ number_format($eventCount) }}</span><span data-content-count="complaints" hidden>{{ number_format($complaintCount) }}</span> records</span>
                </div>

                <div data-content-panel="announcements" role="tabpanel">
                    <div class="dean-content-list__header"><span>Latest updates</span><span>Click a row to view details</span></div>
                    <div class="dean-content-list">
                        @forelse($announcements as $announcement)
                            @php
                                $announcementData = array_merge($announcement->toArray(), ['images' => transform_images($announcement->images)]);
                            @endphp
                            <button type="button" class="dean-content-record" data-announcement-id="{{ $announcement->id }}" data-announcement-json="{{ base64_encode(json_encode($announcementData)) }}" @click="$dispatch('open-announcement-modal', @js($announcementData))">
                                <span class="dean-content-record__visual dean-content-record__visual--purple">
                                    @if($announcement->images && count($announcement->images) > 0)
                                        <img src="{{ storage_url($announcement->images[0]) }}" alt="" loading="lazy">
                                    @else
                                        <i class="ph ph-megaphone"></i>
                                    @endif
                                </span>
                                <span class="dean-content-record__main">
                                    <span class="dean-content-record__title-line"><strong>{{ $announcement->title }}</strong><span class="dean-status dean-status--{{ $announcement->is_published ? 'published' : 'draft' }}">{{ $announcement->is_published ? 'Published' : 'Draft' }}</span></span>
                                    <span class="dean-content-record__excerpt">{{ Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($announcement->content)))), 150) }}</span>
                                    <span class="dean-content-record__meta"><span><i class="ph ph-clock"></i>{{ $announcement->created_at->diffForHumans() }}</span><span><i class="ph ph-user"></i>{{ $announcement->user ? $announcement->user->short_name : 'SOCCS Admin' }}</span></span>
                                </span>
                                <i class="ph ph-caret-right dean-content-record__arrow"></i>
                            </button>
                        @empty
                            <div class="dean-content-empty"><span><i class="ph ph-megaphone"></i></span><strong>No announcements found</strong><p>Published department updates will appear here.</p></div>
                        @endforelse
                    </div>
                    @if(method_exists($announcements, 'hasPages') && $announcements->hasPages())
                        <div class="dean-content-pagination">{{ $announcements->links() }}</div>
                    @endif
                </div>

                <div data-content-panel="events" role="tabpanel" hidden>
                    <div class="dean-content-list__header"><span>Approved activities</span><span>Click a row to view details</span></div>
                    <div class="dean-content-list">
                        @forelse($events as $event)
                            @php $eventData = array_merge($event->toArray(), ['images' => transform_images($event->images)]); @endphp
                            <button type="button" class="dean-content-record" data-event-id="{{ $event->id }}" data-event-json="{{ base64_encode(json_encode($eventData)) }}" @click="$dispatch('open-event-modal', @js($eventData))">
                                <span class="dean-content-record__visual dean-content-record__visual--blue">
                                    @if($event->images && count($event->images) > 0)
                                        <img src="{{ storage_url($event->images[0]) }}" alt="" loading="lazy">
                                    @else
                                        <i class="ph ph-calendar-dot"></i>
                                    @endif
                                </span>
                                <span class="dean-content-record__main">
                                    <span class="dean-content-record__title-line"><strong>{{ $event->title }}</strong><span class="dean-status dean-status--{{ $event->is_published ? 'published' : 'draft' }}">{{ $event->is_published ? 'Posted' : 'Not posted' }}</span></span>
                                    <span class="dean-content-record__excerpt">{{ Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($event->description)))), 150) }}</span>
                                    <span class="dean-content-record__meta"><span><i class="ph ph-calendar"></i>{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y') : 'Date to be confirmed' }}</span><span><i class="ph ph-map-pin"></i>{{ $event->venue ?: 'Venue to be confirmed' }}</span></span>
                                </span>
                                <span class="dean-content-record__side"><span class="dean-status dean-status--{{ $event->approval_status === 'approved' ? 'approved' : 'pending' }}">{{ ucfirst($event->approval_status) }}</span><i class="ph ph-caret-right dean-content-record__arrow"></i></span>
                            </button>
                        @empty
                            <div class="dean-content-empty"><span><i class="ph ph-calendar-x"></i></span><strong>No events found</strong><p>Approved department activities will appear here.</p></div>
                        @endforelse
                    </div>
                    @if(method_exists($events, 'hasPages') && $events->hasPages())
                        <div class="dean-content-pagination">{{ $events->links() }}</div>
                    @endif
                </div>

                <div data-content-panel="complaints" role="tabpanel" hidden>
                    <div class="dean-content-list__header"><span>Submitted concerns</span><span>Status and response tracking</span></div>
                    <div class="dean-complaint-list">
                        @forelse($complaints as $complaint)
                            <div class="dean-complaint-record">
                                <span class="dean-content-record__visual dean-content-record__visual--red"><i class="ph ph-flag"></i></span>
                                <div class="dean-complaint-record__main">
                                    <div class="dean-content-record__title-line"><strong>{{ $complaint->subject }}</strong><span class="dean-status dean-status--{{ $complaint->status === 'resolved' ? 'resolved' : ($complaint->status === 'reviewed' ? 'progress' : 'pending') }}">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span></div>
                                    <p>{{ Str::limit($complaint->message, 180) }}</p>
                                    <div class="dean-content-record__meta"><span><i class="ph ph-user"></i>@if($complaint->is_anonymous == 1 || $complaint->is_anonymous === true) Anonymous @elseif($complaint->user) {{ $complaint->user->short_name }} @elseif($complaint->user_id) User #{{ $complaint->user_id }} @else Unknown user @endif</span><span><i class="ph ph-clock"></i>{{ $complaint->created_at->diffForHumans() }}</span></div>
                                </div>
                                <div class="dean-complaint-record__response">@if($complaint->admin_response)<i class="ph ph-check-circle"></i><span>Responded</span>@else<span>Awaiting response</span>@endif</div>
                            </div>
                        @empty
                            <div class="dean-content-empty"><span class="dean-content-empty__success"><i class="ph ph-check"></i></span><strong>No complaints found</strong><p>There are no submitted concerns to review.</p></div>
                        @endforelse
                    </div>
                    @if(method_exists($complaints, 'hasPages') && $complaints->hasPages())
                        <div class="dean-content-pagination">{{ $complaints->links() }}</div>
                    @endif
                </div>
            </main>

            <aside class="dean-content-context">
                <div class="dean-content-context__header"><div><span class="dean-content-panel__eyebrow">AT A GLANCE</span><h2>Content health</h2></div><span class="dean-content-context__icon"><i class="ph ph-chart-line-up"></i></span></div>

                <div class="dean-content-attention {{ $openComplaintCount > 0 ? 'is-warning' : 'is-clear' }}">
                    <span class="dean-content-attention__icon"><i class="ph {{ $openComplaintCount > 0 ? 'ph-flag' : 'ph-check' }}"></i></span>
                    <div><strong>{{ $openComplaintCount > 0 ? number_format($openComplaintCount) . ' open complaint' . ($openComplaintCount === 1 ? '' : 's') : 'Queue is clear' }}</strong><p>{{ $openComplaintCount > 0 ? 'Follow-up is needed on the current page.' : 'No pending complaints on the current page.' }}</p></div>
                </div>
                <button type="button" class="dean-content-context-link" data-content-tab="complaints">Open complaints view <i class="ph ph-arrow-right"></i></button>

                <div class="dean-content-context__section">
                    <div class="dean-content-context__label">CONTENT MIX</div>
                    <div class="dean-content-breakdown">
                        <div><span><i class="ph ph-megaphone"></i>Announcements</span><strong>{{ number_format($announcementCount) }}</strong></div>
                        <div><span><i class="ph ph-calendar-check"></i>Events</span><strong>{{ number_format($eventCount) }}</strong></div>
                        <div><span><i class="ph ph-flag"></i>Complaints</span><strong>{{ number_format($complaintCount) }}</strong></div>
                    </div>
                </div>

                <div class="dean-content-context__section">
                    <div class="dean-content-context__label">QUICK NAVIGATION</div>
                    <div class="dean-content-quicklinks">
                        <a href="{{ route('dean.dashboard') }}"><span class="dean-content-quicklinks__icon dean-content-quicklinks__icon--purple"><i class="ph ph-gauge"></i></span><span><strong>Department dashboard</strong><small>Return to the main pulse</small></span><i class="ph ph-caret-right"></i></a>
                        <a href="{{ route('dean.proposals.index') }}"><span class="dean-content-quicklinks__icon dean-content-quicklinks__icon--gold"><i class="ph ph-clipboard-text"></i></span><span><strong>Approval queue</strong><small>Review event proposals</small></span><i class="ph ph-caret-right"></i></a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<!-- Announcement modal -->
<div x-data="{ showModal: false, announcement: null, currentImage: 0, openModal(item) { this.announcement = item; this.currentImage = 0; this.showModal = true; }, cleanHTML(html) { return html ? html.replace(/&nbsp;/g, ' ').replace(/\s\s+/g, ' ') : ''; } }" @open-announcement-modal.window="openModal($event.detail)" @keydown.escape.window="showModal = false" x-show="showModal" x-cloak @click.self="showModal = false" class="dean-content-modal">
    <div class="dean-content-modal__dialog" x-show="announcement">
        <button type="button" @click="showModal = false" class="dean-content-modal__close" aria-label="Close announcement"><i class="ph ph-x"></i></button>
        <div class="dean-content-modal__grid">
            <div class="dean-content-modal__media">
                <template x-if="announcement && announcement.images && announcement.images.length > 0"><div class="dean-content-modal__carousel"><template x-for="(image, index) in announcement.images" :key="index"><img x-show="currentImage === index" :src="image" :alt="announcement.title"></template><template x-if="announcement.images.length > 1"><div><button type="button" class="dean-content-modal__nav dean-content-modal__nav--prev" @click="currentImage = currentImage > 0 ? currentImage - 1 : announcement.images.length - 1"><i class="ph ph-caret-left"></i></button><button type="button" class="dean-content-modal__nav dean-content-modal__nav--next" @click="currentImage = currentImage < announcement.images.length - 1 ? currentImage + 1 : 0"><i class="ph ph-caret-right"></i></button><span class="dean-content-modal__counter"><span x-text="currentImage + 1"></span> / <span x-text="announcement.images.length"></span></span></div></template></div></template>
                <template x-if="!announcement?.images || announcement.images.length === 0"><div class="dean-content-modal__fallback dean-content-modal__fallback--purple"><i class="ph ph-megaphone"></i></div></template>
            </div>
            <div class="dean-content-modal__body"><span class="dean-content-modal__eyebrow"><i class="ph ph-clock"></i> <span x-text="announcement?.created_at ? new Date(announcement.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''"></span></span><h2 x-text="announcement?.title"></h2><div class="dean-content-modal__author"><span><i class="ph ph-user"></i></span><div><strong>SOCCS Admin</strong><small>Department announcement</small></div></div><div class="dean-content-modal__copy" x-html="cleanHTML(announcement?.content)"></div></div>
        </div>
    </div>
</div>

<!-- Event modal -->
<div x-data="{ showModal: false, event: null, currentImage: 0, openModal(item) { this.event = item; this.currentImage = 0; this.showModal = true; }, cleanHTML(html) { return html ? html.replace(/&nbsp;/g, ' ').replace(/\s\s+/g, ' ') : ''; } }" @open-event-modal.window="openModal($event.detail)" @keydown.escape.window="showModal = false" x-show="showModal" x-cloak @click.self="showModal = false" class="dean-content-modal">
    <div class="dean-content-modal__dialog" x-show="event">
        <button type="button" @click="showModal = false" class="dean-content-modal__close" aria-label="Close event"><i class="ph ph-x"></i></button>
        <div class="dean-content-modal__grid">
            <div class="dean-content-modal__media">
                <template x-if="event && event.images && event.images.length > 0"><div class="dean-content-modal__carousel"><template x-for="(image, index) in event.images" :key="index"><img x-show="currentImage === index" :src="image" :alt="event.title"></template><template x-if="event.images.length > 1"><div><button type="button" class="dean-content-modal__nav dean-content-modal__nav--prev" @click="currentImage = currentImage > 0 ? currentImage - 1 : event.images.length - 1"><i class="ph ph-caret-left"></i></button><button type="button" class="dean-content-modal__nav dean-content-modal__nav--next" @click="currentImage = currentImage < event.images.length - 1 ? currentImage + 1 : 0"><i class="ph ph-caret-right"></i></button><span class="dean-content-modal__counter"><span x-text="currentImage + 1"></span> / <span x-text="event.images.length"></span></span></div></template></div></template>
                <template x-if="!event?.images || event.images.length === 0"><div class="dean-content-modal__fallback dean-content-modal__fallback--blue"><i class="ph ph-calendar-blank"></i></div></template>
            </div>
            <div class="dean-content-modal__body"><span class="dean-content-modal__eyebrow"><i class="ph ph-calendar"></i> <span x-text="event?.start_date ? new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''"></span></span><h2 x-text="event?.title"></h2><div class="dean-event-facts"><div><span><i class="ph ph-calendar-dot"></i></span><small>Date</small><strong x-text="event?.start_date ? new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'To be confirmed'"></strong></div><div><span><i class="ph ph-clock"></i></span><small>Time</small><strong x-text="event?.start_date ? new Date(event.start_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'To be confirmed'"></strong></div><div><span><i class="ph ph-map-pin"></i></span><small>Venue</small><strong x-text="event?.venue || 'To be confirmed'"></strong></div></div><h3>Event details</h3><div class="dean-content-modal__copy" x-html="cleanHTML(event?.description)"></div></div>
        </div>
    </div>
</div>

<script>
    (function () {
        const root = document.querySelector('[data-content-overview]');
        if (!root) return;

        const validTabs = ['announcements', 'events', 'complaints'];
        const labels = {
            announcements: 'Announcements',
            events: 'Events',
            complaints: 'Complaints'
        };
        const tabs = Array.from(root.querySelectorAll('[data-content-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-content-panel]'));
        const descriptions = Array.from(root.querySelectorAll('[data-content-description]'));
        const counts = Array.from(root.querySelectorAll('[data-content-count]'));
        const title = root.querySelector('[data-content-title]');

        function setActiveTab(tab, updateUrl = true) {
            if (!validTabs.includes(tab)) tab = 'announcements';

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.contentPanel !== tab;
            });
            descriptions.forEach((description) => {
                description.hidden = description.dataset.contentDescription !== tab;
            });
            counts.forEach((count) => {
                count.hidden = count.dataset.contentCount !== tab;
            });
            tabs.forEach((button) => {
                const active = button.dataset.contentTab === tab;
                button.classList.toggle('is-active', active);
                if (button.dataset.activeClass) {
                    button.classList.toggle(button.dataset.activeClass, active);
                }
                if (button.getAttribute('role') === 'tab') {
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                }
            });
            if (title) title.textContent = labels[tab];

            try {
                localStorage.setItem('dean_content_overview_tab', tab);
            } catch (error) {
                // Storage may be unavailable in private browsing; tab state still works.
            }
            document.title = labels[tab] + ' Overview - {{ organization_name() }}';
            if (updateUrl) window.history.replaceState(null, null, '#' + tab);
            return tab;
        }

        tabs.forEach((button) => {
            button.addEventListener('click', () => setActiveTab(button.dataset.contentTab));
        });

        const params = new URLSearchParams(window.location.search);
        let initialTab = params.get('tab') || window.location.hash.substring(1);
        if (!validTabs.includes(initialTab)) {
            try {
                initialTab = localStorage.getItem('dean_content_overview_tab');
            } catch (error) {
                initialTab = 'announcements';
            }
        }
        const announcementId = params.get('view_announcement');
        const eventId = params.get('view_event');
        const complaintId = params.get('view_complaint');
        if (announcementId) initialTab = 'announcements';
        if (eventId) initialTab = 'events';
        if (complaintId) initialTab = 'complaints';
        setActiveTab(initialTab, false);

        if (announcementId || eventId) {
            const type = announcementId ? 'announcement' : 'event';
            const id = announcementId || eventId;
            window.setTimeout(() => {
                const row = root.querySelector('[data-' + type + '-id="' + id + '"]');
                if (!row) return;
                try {
                    const data = JSON.parse(atob(row.dataset[type + 'Json']));
                    window.dispatchEvent(new CustomEvent('open-' + type + '-modal', { detail: data }));
                } catch (error) {
                    console.error('Unable to open content item:', error);
                }
            }, 300);
        }
    })();
</script>
<style>
    .dean-content-page {
        --dc-ink: #20212b;
        --dc-muted: #727582;
        --dc-line: #e7e8ed;
        --dc-bg: #f7f7fa;
        --dc-surface: #fff;
        --dc-purple: var(--primary-color, #8238b3);
        --dc-gold: var(--complementary-color, #d7ac28);
        color: var(--dc-ink);
        background: var(--dc-bg);
        min-height: calc(100vh - 64px);
        font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
    }
    .dean-content-page__shell { width: min(100%, 1680px); margin: 0 auto; padding: 28px clamp(20px, 2.4vw, 42px) 42px; }
    .dean-content-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
    .dean-content-eyebrow, .dean-content-panel__eyebrow, .dean-content-context__label { color: var(--dc-muted); font-size: 10px; font-weight: 800; letter-spacing: .14em; line-height: 1.2; text-transform: uppercase; }
    .dean-content-eyebrow { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .dean-content-header h1 { margin: 0; color: var(--dc-ink); font-size: clamp(25px, 2.1vw, 34px); font-weight: 800; letter-spacing: -.04em; line-height: 1.1; }
    .dean-content-header p { margin: 7px 0 0; color: var(--dc-muted); font-size: 13px; line-height: 1.5; }
    .dean-content-header__actions { display: flex; align-items: center; gap: 9px; flex-shrink: 0; }
    .dean-content-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 0 13px; border: 1px solid transparent; border-radius: 7px; font-size: 11px; font-weight: 750; text-decoration: none; transition: border-color .18s ease, background .18s ease, transform .18s ease; }
    .dean-content-btn:hover { transform: translateY(-1px); }
    .dean-content-btn--secondary { border-color: var(--dc-line); background: var(--dc-surface); color: #555967; }
    .dean-content-btn--secondary:hover { border-color: #cfd1d9; background: #fbfbfc; }
    .dean-content-btn--primary { background: var(--dc-purple); color: #fff; box-shadow: 0 4px 10px rgba(var(--primary-color-rgb, 130, 56, 179), .16); }
    .dean-content-btn--primary:hover { filter: brightness(.95); }

    .dean-content-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
    .dean-content-metric { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 13px 15px; border: 1px solid var(--dc-line); border-radius: 8px; background: var(--dc-surface); color: var(--dc-ink); text-align: left; cursor: pointer; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .dean-content-metric:hover, .dean-content-metric.is-active { border-color: #c7c9d2; box-shadow: 0 4px 12px rgba(32, 33, 43, .06); transform: translateY(-1px); }
    .dean-content-metric--purple.is-active { border-color: rgba(var(--primary-color-rgb, 130, 56, 179), .42); }
    .dean-content-metric--blue.is-active { border-color: rgba(37, 99, 235, .38); }
    .dean-content-metric--red.is-active { border-color: rgba(220, 38, 38, .35); }
    .dean-content-metric__icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 7px; font-size: 13px; }
    .dean-content-metric__icon--purple { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dc-purple); }
    .dean-content-metric__icon--blue { background: #eaf2ff; color: #2563eb; }
    .dean-content-metric__icon--red { background: #fff0f0; color: #dc3b3b; }
    .dean-content-metric__body { display: grid; min-width: 0; flex: 1; }
    .dean-content-metric__body > span { color: #5c5f6a; font-size: 11px; font-weight: 700; }
    .dean-content-metric__body strong { margin-top: 1px; font-size: 21px; letter-spacing: -.04em; line-height: 1; }
    .dean-content-metric__body small { margin-top: 5px; color: var(--dc-muted); font-size: 10px; }
    .dean-content-metric__arrow { color: #b3b5bd; font-size: 11px; }

    .dean-content-tabs { display: flex; align-items: center; gap: 3px; padding: 4px; margin-bottom: 10px; border: 1px solid var(--dc-line); border-radius: 8px; background: #f0f0f4; }
    .dean-content-tabs button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 37px; padding: 0 14px; border: 0; border-radius: 6px; background: transparent; color: #70727c; font-size: 11px; font-weight: 750; cursor: pointer; transition: color .18s ease, background .18s ease, box-shadow .18s ease; }
    .dean-content-tabs button i { font-size: 12px; color: #9496a1; }
    .dean-content-tabs button b { display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 19px; padding: 0 5px; border-radius: 10px; background: #e2e2e8; color: #666875; font-size: 10px; }
    .dean-content-tabs button:hover { color: var(--dc-ink); }
    .dean-content-tabs button.is-active { background: #fff; color: var(--dc-purple); box-shadow: 0 1px 3px rgba(32, 33, 43, .1); }
    .dean-content-tabs button.is-active i { color: currentColor; }
    .dean-content-tabs button.is-active b { background: #f0e7f7; color: var(--dc-purple); }
    .dean-content-tabs button.is-active.dean-content-tabs--red { color: #c93636; }
    .dean-content-tabs button.is-active.dean-content-tabs--red b { background: #fee9e9; color: #c93636; }

    .dean-content-workspace { display: grid; grid-template-columns: minmax(0, 1fr) 304px; align-items: start; gap: 10px; }
    .dean-content-panel, .dean-content-context { border: 1px solid var(--dc-line); border-radius: 9px; background: var(--dc-surface); box-shadow: 0 1px 2px rgba(32, 33, 43, .025); }
    .dean-content-panel { min-width: 0; overflow: hidden; }
    .dean-content-panel__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 20px 14px; border-bottom: 1px solid var(--dc-line); }
    .dean-content-panel__header h2, .dean-content-context h2 { margin: 4px 0 0; color: var(--dc-ink); font-size: 17px; font-weight: 800; letter-spacing: -.025em; }
    .dean-content-panel__header p { margin: 5px 0 0; color: var(--dc-muted); font-size: 11px; }
    .dean-content-panel__count { flex-shrink: 0; margin-top: 4px; color: var(--dc-muted); font-size: 10px; font-weight: 700; }
    .dean-content-list__header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 8px; color: #8a8c95; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
    .dean-content-list { padding: 0 10px 10px; }
    .dean-content-record { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 80px; padding: 11px 10px; border: 0; border-bottom: 1px solid #f0f0f3; background: transparent; color: inherit; text-align: left; cursor: pointer; transition: background .18s ease; }
    .dean-content-record:last-child { border-bottom: 0; }
    .dean-content-record:hover { background: #fafafd; }
    .dean-content-record__visual { display: inline-flex; align-items: center; justify-content: center; width: 45px; height: 45px; flex: 0 0 45px; overflow: hidden; border-radius: 7px; font-size: 16px; }
    .dean-content-record__visual img { width: 100%; height: 100%; object-fit: cover; }
    .dean-content-record__visual--purple { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dc-purple); }
    .dean-content-record__visual--blue { background: #eaf2ff; color: #2563eb; }
    .dean-content-record__visual--red { background: #fff0f0; color: #d13939; }
    .dean-content-record__main, .dean-complaint-record__main { display: grid; min-width: 0; flex: 1; gap: 5px; }
    .dean-content-record__title-line { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .dean-content-record__title-line strong { overflow: hidden; color: #282934; font-size: 12px; font-weight: 800; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
    .dean-content-record__excerpt { overflow: hidden; color: #757783; font-size: 11px; line-height: 1.4; text-overflow: ellipsis; white-space: nowrap; }
    .dean-content-record__meta { display: flex; align-items: center; flex-wrap: wrap; gap: 13px; color: #9a9ca5; font-size: 10px; }
    .dean-content-record__meta span { display: inline-flex; align-items: center; gap: 5px; }
    .dean-content-record__meta i { color: #b0b2ba; font-size: 10px; }
    .dean-content-record__arrow { flex: 0 0 auto; margin-left: 6px; color: #bbbcc3; font-size: 10px; }
    .dean-content-record__side { display: flex; align-items: center; gap: 14px; flex: 0 0 auto; }
    .dean-status { display: inline-flex; align-items: center; justify-content: center; width: max-content; padding: 4px 7px; border-radius: 4px; font-size: 9px; font-weight: 800; line-height: 1; white-space: nowrap; }
    .dean-status--published, .dean-status--approved, .dean-status--resolved { background: #eaf8f1; color: #218252; }
    .dean-status--draft { background: #f0f0f3; color: #737580; }
    .dean-status--pending { background: #fff5df; color: #a66b00; }
    .dean-status--progress { background: #eaf2ff; color: #2f67c6; }
    .dean-complaint-list { padding: 0 10px 10px; }
    .dean-complaint-record { display: flex; align-items: flex-start; gap: 12px; padding: 13px 10px; border-bottom: 1px solid #f0f0f3; }
    .dean-complaint-record:last-child { border-bottom: 0; }
    .dean-complaint-record__main p { margin: 0; overflow: hidden; color: #757783; font-size: 11px; line-height: 1.45; text-overflow: ellipsis; white-space: nowrap; }
    .dean-complaint-record__response { display: flex; align-items: center; gap: 5px; flex: 0 0 auto; margin-top: 2px; color: #999ba5; font-size: 10px; }
    .dean-complaint-record__response i { color: #2c9a62; }
    .dean-content-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; padding: 32px 20px; color: var(--dc-muted); text-align: center; }
    .dean-content-empty > span { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; margin-bottom: 10px; border-radius: 8px; background: #f1f1f5; color: #a6a8b1; }
    .dean-content-empty__success { background: #eaf8f1 !important; color: #2c9a62 !important; }
    .dean-content-empty strong { color: #4b4d58; font-size: 12px; }
    .dean-content-empty p { margin: 4px 0 0; font-size: 11px; }
    .dean-content-pagination { padding: 10px 20px 16px; border-top: 1px solid #f0f0f3; font-size: 11px; }
    .dean-content-pagination nav { display: flex; justify-content: flex-end; }
    .dean-content-pagination svg { width: 16px; }

    .dean-content-context { padding: 18px; }
    .dean-content-context__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 17px; }
    .dean-content-context__icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 7px; background: #eaf8f1; color: #258355; font-size: 12px; }
    .dean-content-attention { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid transparent; border-radius: 7px; }
    .dean-content-attention.is-warning { border-color: #f5dfad; background: #fffaf0; }
    .dean-content-attention.is-clear { border-color: #cdebdc; background: #f2fbf6; }
    .dean-content-attention__icon { display: inline-flex; align-items: center; justify-content: center; width: 25px; height: 25px; flex: 0 0 25px; border-radius: 6px; background: #fff; color: #bd8107; font-size: 11px; }
    .is-clear .dean-content-attention__icon { color: #2a9160; }
    .dean-content-attention strong { display: block; color: #4d4f59; font-size: 11px; line-height: 1.3; }
    .dean-content-attention p { margin: 3px 0 0; color: #888a93; font-size: 10px; line-height: 1.4; }
    .dean-content-context-link { display: inline-flex; align-items: center; justify-content: space-between; width: 100%; padding: 10px 0 13px; border: 0; border-bottom: 1px solid var(--dc-line); background: transparent; color: var(--dc-purple); font-size: 10px; font-weight: 800; text-align: left; cursor: pointer; }
    .dean-content-context-link i { font-size: 9px; transition: transform .18s ease; }
    .dean-content-context-link:hover i { transform: translateX(3px); }
    .dean-content-context__section { padding: 17px 0 0; }
    .dean-content-breakdown { display: grid; gap: 11px; margin-top: 12px; }
    .dean-content-breakdown > div { display: flex; align-items: center; justify-content: space-between; color: #666874; font-size: 10px; }
    .dean-content-breakdown span { display: inline-flex; align-items: center; gap: 8px; }
    .dean-content-breakdown i { width: 15px; color: #92949d; text-align: center; }
    .dean-content-breakdown strong { color: #343540; font-size: 11px; }
    .dean-content-quicklinks { display: grid; gap: 2px; margin-top: 8px; }
    .dean-content-quicklinks a { display: flex; align-items: center; gap: 9px; padding: 8px 0; color: inherit; text-decoration: none; }
    .dean-content-quicklinks a > span:nth-child(2) { display: grid; min-width: 0; flex: 1; }
    .dean-content-quicklinks strong { color: #4d4f59; font-size: 10px; }
    .dean-content-quicklinks small { margin-top: 2px; color: #999ba4; font-size: 9px; }
    .dean-content-quicklinks a > i { color: #b3b5bd; font-size: 9px; }
    .dean-content-quicklinks__icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; flex: 0 0 28px; border-radius: 6px; font-size: 11px; }
    .dean-content-quicklinks__icon--purple { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dc-purple); }
    .dean-content-quicklinks__icon--gold { background: #fff7df; color: #ae7a08; }
    .dean-content-quicklinks__icon--blue { background: #eaf2ff; color: #2563eb; }
    .dean-content-quicklinks a:hover strong { color: var(--dc-purple); }

    .dean-content-modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(31, 32, 42, .58); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
    .dean-content-modal__dialog { position: relative; width: min(100%, 920px); max-height: min(680px, calc(100vh - 40px)); overflow: hidden; border-radius: 9px; background: #fff; box-shadow: 0 20px 55px rgba(20, 20, 30, .25); }
    .dean-content-modal__close { position: absolute; top: 12px; right: 12px; z-index: 2; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 6px; background: rgba(255,255,255,.92); color: #656873; box-shadow: 0 2px 8px rgba(0,0,0,.12); cursor: pointer; }
    .dean-content-modal__close:hover { color: #252631; }
    .dean-content-modal__grid { display: grid; grid-template-columns: minmax(280px, .9fr) minmax(0, 1.1fr); max-height: inherit; }
    .dean-content-modal__media { min-height: 420px; background: #f0f1f4; }
    .dean-content-modal__carousel, .dean-content-modal__carousel > img { position: relative; width: 100%; height: 100%; min-height: 420px; }
    .dean-content-modal__carousel > img { position: absolute; inset: 0; object-fit: contain; }
    .dean-content-modal__fallback { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; min-height: 420px; color: #fff; font-size: 64px; }
    .dean-content-modal__fallback--purple { background: linear-gradient(135deg, #a268c5, #6f2f9a); }
    .dean-content-modal__fallback--blue { background: linear-gradient(135deg, #5c8fe5, #2455ae); }
    .dean-content-modal__nav { position: absolute; top: 50%; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 6px; background: rgba(30,30,40,.5); color: #fff; cursor: pointer; transform: translateY(-50%); }
    .dean-content-modal__nav--prev { left: 14px; }
    .dean-content-modal__nav--next { right: 14px; }
    .dean-content-modal__counter { position: absolute; right: 14px; bottom: 14px; padding: 5px 8px; border-radius: 4px; background: rgba(30,30,40,.62); color: #fff; font-size: 10px; }
    .dean-content-modal__body { max-height: min(680px, calc(100vh - 40px)); overflow: auto; padding: 34px 34px 30px; }
    .dean-content-modal__eyebrow { display: inline-flex; align-items: center; gap: 7px; color: #8b8d96; font-size: 10px; font-weight: 750; }
    .dean-content-modal__eyebrow i { color: var(--dc-gold); }
    .dean-content-modal__body h2 { margin: 13px 0 20px; color: #24252f; font-size: 25px; font-weight: 800; letter-spacing: -.04em; line-height: 1.15; }
    .dean-content-modal__author { display: flex; align-items: center; gap: 10px; padding: 12px 0 15px; border-top: 1px solid var(--dc-line); border-bottom: 1px solid var(--dc-line); }
    .dean-content-modal__author > span { display: inline-flex; align-items: center; justify-content: center; width: 29px; height: 29px; border-radius: 7px; background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dc-purple); font-size: 11px; }
    .dean-content-modal__author div { display: grid; gap: 2px; }
    .dean-content-modal__author strong { color: #4a4c57; font-size: 11px; }
    .dean-content-modal__author small { color: #999ba4; font-size: 10px; }
    .dean-content-modal__copy { margin-top: 20px; color: #62646f; font-size: 12px; line-height: 1.7; white-space: pre-line; }
    .dean-content-modal__copy p { margin: 0 0 10px; }
    .dean-content-modal__body h3 { margin: 22px 0 0; color: #363742; font-size: 13px; font-weight: 800; }
    .dean-event-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding: 13px 0; border-top: 1px solid var(--dc-line); border-bottom: 1px solid var(--dc-line); }
    .dean-event-facts > div { display: grid; grid-template-columns: 25px 1fr; column-gap: 7px; align-items: center; }
    .dean-event-facts span { grid-row: span 2; display: inline-flex; align-items: center; justify-content: center; width: 25px; height: 25px; border-radius: 5px; background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dc-purple); font-size: 10px; }
    .dean-event-facts small { color: #989aa3; font-size: 9px; }
    .dean-event-facts strong { overflow: hidden; color: #4c4e59; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 1100px) { .dean-content-workspace { grid-template-columns: minmax(0, 1fr); } .dean-content-context { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, .75fr); column-gap: 22px; } .dean-content-context__header, .dean-content-attention, .dean-content-context-link { grid-column: 1 / -1; } .dean-content-context__section { padding-top: 15px; } }
    @media (max-width: 760px) { .dean-content-page__shell { padding: 20px 14px 30px; } .dean-content-header { align-items: flex-start; flex-direction: column; gap: 16px; } .dean-content-header__actions { width: 100%; } .dean-content-btn { flex: 1; } .dean-content-metrics { grid-template-columns: 1fr; } .dean-content-tabs { overflow-x: auto; } .dean-content-tabs button { flex: 1 0 auto; padding: 0 12px; } .dean-content-panel__header { padding-left: 15px; padding-right: 15px; } .dean-content-list__header { padding-left: 15px; padding-right: 15px; } .dean-content-record { align-items: flex-start; } .dean-content-record__side { flex-direction: column; align-items: flex-end; gap: 9px; } .dean-content-record__excerpt { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; } .dean-content-record__meta { gap: 7px 11px; } .dean-complaint-record { flex-wrap: wrap; } .dean-complaint-record__response { width: 100%; margin-left: 57px; } .dean-content-context { display: block; } .dean-content-context__section { padding-top: 17px; } .dean-content-modal { padding: 10px; } .dean-content-modal__dialog { max-height: calc(100vh - 20px); overflow: auto; } .dean-content-modal__grid { grid-template-columns: 1fr; } .dean-content-modal__media, .dean-content-modal__carousel, .dean-content-modal__carousel > img, .dean-content-modal__fallback { min-height: 240px; height: 240px; } .dean-content-modal__body { max-height: none; padding: 25px 20px 24px; } }
    @media (max-width: 460px) { .dean-content-record__visual { width: 38px; height: 38px; flex-basis: 38px; } .dean-content-record { gap: 9px; padding-left: 4px; padding-right: 4px; } .dean-content-record__title-line { align-items: flex-start; flex-direction: column; gap: 4px; } .dean-content-record__side { gap: 5px; } .dean-event-facts { grid-template-columns: 1fr; } }
</style>
@endsection
