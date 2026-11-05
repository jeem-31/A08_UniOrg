@extends('layouts.app')

@section('title', 'Announcements')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="student-announcements-page min-h-screen">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <header class="announcements-hero">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">Announcements</h1>
                <p>Stay updated with the latest news from {{ $orgAcronym }}.</p>
            </div>
        </header>

        <section class="announcements-feed-panel" aria-label="Latest announcements">
            <div class="announcements-feed-panel__heading">
                <p class="announcements-eyebrow">Latest updates</p>
                <p>News, reminders, and important notices for your student organization.</p>
            </div>
            @include('components.announcement-list')
        </section>
    </div>
</div>
<style>
    .student-announcements-page {
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

    .student-announcements-page > .max-w-7xl { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }
    .announcements-hero { padding: 0 0 clamp(2.25rem, 4vw, 3.25rem); }
    .announcements-hero h1 { color: var(--neu-text); }
    .announcements-hero p {
        max-width: 38rem;
        margin: .55rem 0 0;
        color: var(--neu-muted);
        font-size: clamp(.95rem, 2vw, 1.05rem);
        line-height: 1.55;
    }

    .announcements-feed-panel {
        padding: clamp(1.25rem, 3vw, 2rem);
        border: 0;
        border-radius: 1.5rem;
        background: var(--neu-bg);
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow);
    }

    .announcements-feed-panel__heading { margin-bottom: 1.5rem; }
    .announcements-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #0071e3);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .announcements-feed-panel__heading > p:last-child { max-width: 37rem; margin: 0; color: var(--neu-muted); font-size: .92rem; line-height: 1.55; }
    .announcement-list__grid { gap: 1rem !important; }
    .announcement-card-link { display: block; height: 100%; }
    .announcement-card {
        height: 100%;
        border: 0;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow) !important;
        transition: transform .32s cubic-bezier(.2, .8, .2, 1), box-shadow .32s cubic-bezier(.2, .8, .2, 1) !important;
        animation: announcement-card-in .65s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--announcement-index, 0) * 45ms);
    }

    .announcement-card:hover { transform: translateY(-3px) !important; box-shadow: 12px 12px 24px var(--neu-dark-shadow), -12px -12px 24px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .22) !important; }
    .announcement-card__media, .announcement-card__placeholder { height: 12.5rem !important; }
    .announcement-card__image { transition: transform .55s cubic-bezier(.2, .8, .2, 1) !important; }
    .announcement-card-link:hover .announcement-card__image { transform: scale(1.045) !important; }
    .announcement-card__placeholder { background: rgba(var(--primary-color-rgb, 0, 113, 227), .10) !important; box-shadow: inset 5px 5px 12px rgba(var(--primary-color-rgb, 0, 113, 227), .14), inset -5px -5px 12px rgba(255, 255, 255, .6) !important; }
    .announcement-card__placeholder > i { color: var(--primary-color, #0071e3); font-size: 3rem; opacity: .6; }

    .announcement-card__badge { border-radius: 999px !important; box-shadow: 0 2px 8px rgba(163, 177, 198, .5) !important; }
    .announcement-card__badge.bg-red-500 { background: rgba(255, 255, 255, .92) !important; color: #B91C1C !important; }
    .announcement-card__badge.bg-blue-500 { background: rgba(255, 255, 255, .92) !important; color: #1D4ED8 !important; }
    .announcement-card__badge.bg-green-500 { background: rgba(255, 255, 255, .92) !important; color: #15803D !important; }
    .announcement-card__badge.bg-gray-500 { background: rgba(255, 255, 255, .92) !important; color: #475569 !important; }
    .announcement-card__org-badge { border-radius: 999px !important; box-shadow: 0 2px 8px rgba(163, 177, 198, .5) !important; background: rgba(var(--primary-color-rgb, 124, 58, 237), .92) !important; color: #fff !important; }

    .announcement-card__body { padding: 1.15rem !important; }
    .announcement-card .announcement-title { color: var(--neu-text) !important; font-size: 1.08rem !important; line-height: 1.28; letter-spacing: -.02em !important; }
    .announcement-card-link:hover .announcement-title { color: var(--primary-color, #0071e3) !important; }
    .announcement-card__excerpt { color: var(--neu-muted) !important; line-height: 1.5; }
    .announcement-card__meta { border-color: var(--neu-divider) !important; color: var(--neu-muted) !important; }
    .announcement-card__meta .text-gray-500 { color: var(--neu-muted) !important; }
    .announcement-card__meta img.ring-1 { box-shadow: 0 0 0 1px rgba(163, 177, 198, .5) !important; }
    .announcement-card__meta span[class*="bg-gray-100"] { background: #DDE3EC !important; }
    .announcement-card__read-more { color: var(--primary-color, #0071e3) !important; }

    .announcement-list__empty {
        border: 0;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: inset 5px 5px 12px rgba(163, 177, 198, .5), inset -5px -5px 12px rgba(255, 255, 255, .8) !important;
    }

    .announcement-list__empty > i { color: rgba(var(--primary-color-rgb, 0, 113, 227), .30) !important; }
    .announcement-list__empty p { color: var(--neu-muted) !important; }

    .announcement-pagination { margin-top: 1.5rem !important; }
    .announcement-pagination nav div:last-child a.relative, .announcement-pagination nav div:last-child span.relative {
        background: var(--neu-bg) !important;
        border-color: transparent !important;
        color: var(--neu-muted) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        border-radius: .7rem !important;
        margin: 0 .2rem;
    }
    .announcement-pagination nav div:last-child span.relative.z-10 {
        border-color: var(--primary-color, #0071e3) !important;
        background: var(--primary-color, #0071e3) !important;
        color: #fff !important;
        box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -4px -4px 10px rgba(255, 255, 255, .85) !important;
    }
    .announcement-pagination nav div:last-child a.relative:hover {
        color: var(--primary-color, #0071e3) !important;
        box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
    }

    @keyframes announcement-card-in { from { opacity: 0; transform: translateY(12px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-announcements-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-announcements-page > .max-w-7xl { padding-top: 2rem !important; padding-bottom: 3rem !important; }
        .announcements-hero { padding-bottom: 2.25rem; }
        .announcements-feed-panel { border-radius: 1.25rem; }
        .announcement-card { border-radius: 1.25rem !important; }
        .announcement-card__media, .announcement-card__placeholder { height: 11rem !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        .announcement-card { animation: none !important; transition-duration: .01ms !important; }
        .announcement-card__image { transition-duration: .01ms !important; }
    }
</style>
<script>
(() => {
    if (window.__studentAnnouncementRealtimeInitialized) return;
    window.__studentAnnouncementRealtimeInitialized = true;

    const visibleOrganizationIds = @json(array_map('intval', $visibleOrganizationIds));
    let isRefreshingAnnouncementList = false;

    const isVisibleAnnouncement = (event) => {
        const organizationId = event?.announcement?.organization_id ?? event?.organization_id;

        return organizationId === null || visibleOrganizationIds.includes(Number(organizationId));
    };

    const refreshAnnouncementList = async () => {
        if (isRefreshingAnnouncementList) return;

        const currentList = document.querySelector('[data-student-announcement-list]');
        if (!currentList) return;

        isRefreshingAnnouncementList = true;

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('announcement_list', '1');

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Announcement fragment request failed with status ${response.status}`);
            }

            const template = document.createElement('template');
            template.innerHTML = (await response.text()).trim();
            const replacementList = template.content.querySelector('[data-student-announcement-list]');

            if (!replacementList) {
                throw new Error('Announcement fragment did not include the list container.');
            }

            currentList.replaceWith(replacementList);
        } catch (error) {
            console.error('Unable to refresh the announcement list:', error);
        } finally {
            isRefreshingAnnouncementList = false;
        }
    };

    const subscribeToAnnouncementUpdates = () => {
        if (!window.AnnouncementEcho) return;

        window.AnnouncementEcho.channel('announcements')
            .listen('.announcement-created', (event) => {
                if (isVisibleAnnouncement(event)) refreshAnnouncementList();
            })
            .listen('.announcement-updated', (event) => {
                if (isVisibleAnnouncement(event)) refreshAnnouncementList();
            })
            .listen('.announcement-deleted', (event) => {
                if (isVisibleAnnouncement(event)) refreshAnnouncementList();
            });
    };

    if (window.AnnouncementEcho) {
        subscribeToAnnouncementUpdates();
    } else {
        window.addEventListener('announcement-echo-ready', subscribeToAnnouncementUpdates, { once: true });
    }
})();
</script>
@endsection
