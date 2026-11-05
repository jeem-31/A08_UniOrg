@extends('layouts.dean')

@section('title', 'Dean Dashboard - UniOrg')

@section('content')
@php
    $events = $upcomingEvents ?? $recentEvents ?? collect();
    $pendingApprovals = (int) ($stats['pending_approvals'] ?? 0);
    $students = (int) ($stats['total_students'] ?? 0);
    $admins = (int) ($stats['total_soccs_admins'] ?? 0);
    $totalAccounts = $students + $admins;
    $studentShare = $totalAccounts > 0 ? round(($students / $totalAccounts) * 100) : 0;
    $adminShare = $totalAccounts > 0 ? 100 - $studentShare : 0;
@endphp

<div class="dean-dashboard">
    <div class="dean-dashboard__shell">
        <header class="dean-dashboard__header">
            <div>
                <div class="dean-eyebrow">Department operations</div>
                <h1>Dean dashboard</h1>
                <p>Welcome back, {{ auth()->user()->short_name }}. Here is the latest activity across your department.</p>
            </div>
            <div class="dean-dashboard__actions">
                <a href="{{ route('dean.content-overview') }}" class="dean-btn dean-btn--secondary">
                    <i class="ph ph-squares-four"></i><span>Content overview</span>
                </a>
                <a href="{{ route('dean.proposals.index') }}" class="dean-btn dean-btn--primary">
                    <i class="ph ph-clipboard-text"></i><span>Review proposals</span>
                    @if($pendingApprovals > 0)
                        <span class="dean-btn__count">{{ $pendingApprovals }}</span>
                    @endif
                </a>
            </div>
        </header>

        <section class="dean-metrics" aria-label="Department metrics">
            <div class="dean-metric" data-tone="purple">
                <div class="dean-metric__top"><span class="dean-metric__label">Students</span><span class="dean-metric__icon"><i class="ph ph-student"></i></span></div>
                <strong>{{ number_format($students) }}</strong>
                <span class="dean-metric__meta">Registered accounts</span>
            </div>
            <div class="dean-metric" data-tone="gold">
                <div class="dean-metric__top"><span class="dean-metric__label">Admins</span><span class="dean-metric__icon"><i class="ph ph-shield-check"></i></span></div>
                <strong>{{ number_format($admins) }}</strong>
                <span class="dean-metric__meta">Organization leads</span>
            </div>
            <div class="dean-metric" data-tone="blue">
                <div class="dean-metric__top"><span class="dean-metric__label">Events</span><span class="dean-metric__icon"><i class="ph ph-calendar-check"></i></span></div>
                <strong>{{ number_format($stats['total_events'] ?? 0) }}</strong>
                <span class="dean-metric__meta">Published activities</span>
            </div>
            <div class="dean-metric" data-tone="teal">
                <div class="dean-metric__top"><span class="dean-metric__label">Announcements</span><span class="dean-metric__icon"><i class="ph ph-megaphone"></i></span></div>
                <strong>{{ number_format($stats['total_announcements'] ?? 0) }}</strong>
                <span class="dean-metric__meta">Department updates</span>
            </div>
            <a href="{{ route('dean.proposals.index') }}" class="dean-metric dean-metric--action" data-tone="amber">
                <div class="dean-metric__top"><span class="dean-metric__label">Needs review</span><span class="dean-metric__icon"><i class="ph ph-tray"></i></span></div>
                <strong>{{ number_format($pendingApprovals) }}</strong>
                <span class="dean-metric__meta">Open proposal{{ $pendingApprovals === 1 ? '' : 's' }} <i class="ph ph-arrow-square-out"></i></span>
            </a>
            <a href="{{ route('dean.content-overview') }}?tab=complaints" class="dean-metric dean-metric--action" data-tone="red">
                <div class="dean-metric__top"><span class="dean-metric__label">Complaints</span><span class="dean-metric__icon"><i class="ph ph-flag"></i></span></div>
                <strong>{{ number_format($stats['total_complaints'] ?? 0) }}</strong>
                <span class="dean-metric__meta">Filed reports <i class="ph ph-arrow-square-out"></i></span>
            </a>
        </section>

        <div class="dean-section-heading">
            <div>
                <span class="dean-section-heading__eyebrow">WORK QUEUE</span>
                <h2>Department pulse</h2>
            </div>
        </div>

        <section class="dean-dashboard__grid dean-dashboard__grid--top">
            <article class="dean-panel dean-panel--queue">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">PRIORITY QUEUE</span>
                        <h3>Approval requests</h3>
                    </div>
                    <span class="dean-panel__header-icon dean-panel__header-icon--amber"><i class="ph ph-clipboard-text"></i></span>
                </div>

                @if($pendingApprovals > 0)
                    <div class="dean-queue-summary">
                        <div class="dean-queue-summary__number">{{ $pendingApprovals }}</div>
                        <div>
                            <strong>Event proposal{{ $pendingApprovals === 1 ? '' : 's' }} waiting</strong>
                            <p>Review the details and make a decision before the organization can publish its activity.</p>
                        </div>
                    </div>
                    <a href="{{ route('dean.proposals.index') }}" class="dean-inline-action dean-inline-action--amber">Open approval queue <i class="ph ph-arrow-right"></i></a>
                @else
                    <div class="dean-empty-state dean-empty-state--compact">
                        <span><i class="ph ph-check"></i></span>
                        <div><strong>Queue is clear</strong><p>No event proposals are waiting for your review.</p></div>
                    </div>
                @endif

                <div class="dean-process-list" aria-label="Approval workflow">
                    <div><span>01</span><p><strong>Review</strong><small>Check date, venue, and budget</small></p></div>
                    <div><span>02</span><p><strong>Decide</strong><small>Approve or send back with a note</small></p></div>
                    <div><span>03</span><p><strong>Notify</strong><small>Organization receives the result</small></p></div>
                </div>
            </article>

            <article class="dean-panel dean-panel--accounts">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">ACCOUNT MIX</span>
                        <h3>Department accounts</h3>
                    </div>
                    <span class="dean-panel__header-icon dean-panel__header-icon--purple"><i class="ph ph-users"></i></span>
                </div>
                <div class="dean-account-total"><strong>{{ number_format($totalAccounts) }}</strong><span>total accounts</span></div>
                <div class="dean-account-bar" role="img" aria-label="{{ $studentShare }} percent students and {{ $adminShare }} percent admins">
                    <span style="width: {{ $studentShare }}%"></span><span style="width: {{ $adminShare }}%"></span>
                </div>
                <div class="dean-account-legend">
                    <div><span class="dean-legend-dot dean-legend-dot--purple"></span><div><strong>{{ number_format($students) }}</strong><small>Students · {{ $studentShare }}%</small></div></div>
                    <div><span class="dean-legend-dot dean-legend-dot--gold"></span><div><strong>{{ number_format($admins) }}</strong><small>Admins · {{ $adminShare }}%</small></div></div>
                </div>
                <div class="dean-shortcuts">
                    <a href="{{ route('dean.registration-reports') }}"><i class="ph ph-chart-bar"></i> Registration reports <i class="ph ph-caret-right"></i></a>
                    <a href="{{ route('dean.financial-reports.index') }}"><i class="ph ph-file-text"></i> Financial reports <i class="ph ph-caret-right"></i></a>
                </div>
            </article>
        </section>

        @if(!empty($ownedVenues))
        <div class="dean-section-heading" id="premises-notices">
            <div>
                <span class="dean-section-heading__eyebrow">PREMISES</span>
                <h2>Premises use notices</h2>
            </div>
        </div>

        <section class="dean-dashboard__grid dean-dashboard__grid--content" aria-label="Premises use notices">
            <article class="dean-panel dean-panel--table dean-panel--events dean-panel--premises">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">ADVISORY</span>
                        <h3>Events using {{ implode(' / ', $ownedVenues) }}</h3>
                    </div>
                    <a href="{{ route('dean.proposals.index') }}" class="dean-view-all">Proposals <i class="ph ph-arrow-right"></i></a>
                </div>
                <div class="dean-table-wrap">
                    <table class="dean-table">
                        <thead><tr><th>Event</th><th>Org</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($venueNotices as $notice)
                                @php $noticeData = array_merge($notice->toArray(), ['images' => transform_images($notice->images ?? null), 'organization_name' => $notice->organization->name ?? null, 'organization_acronym' => $notice->organization->org_acronym ?? null]); @endphp
                                <tr class="dean-row-clickable" @click="$dispatch('open-event-modal', @js($noticeData))" title="Click to view event details">
                                    <td><div class="dean-item-title"><span class="dean-item-icon dean-item-icon--blue dean-thumb">@if($notice->images && count($notice->images) > 0)<img src="{{ storage_url($notice->images[0]) }}" alt="" loading="lazy">@else<i class="ph ph-map-pin"></i>@endif</span><div><strong>{{ $notice->title }}</strong><small>{{ $notice->venue }}</small></div></div></td>
                                    <td class="dean-table__muted">{{ $notice->organization->org_acronym ?? $notice->organization->name ?? '—' }}</td>
                                    <td class="dean-table__date">{{ $notice->start_date ? \Carbon\Carbon::parse($notice->start_date)->format('M d, Y') : '—' }}</td>
                                    <td>
                                        @if($notice->approval_status === 'rejected')
                                            <span class="dean-status dean-status--rejected">Dean rejected</span>
                                        @elseif($notice->osas_status === 'approved')
                                            <span class="dean-status dean-status--approved">OSAS approved</span>
                                        @elseif($notice->osas_status === 'rejected')
                                            <span class="dean-status dean-status--rejected">OSAS rejected</span>
                                        @elseif($notice->approval_status === 'approved')
                                            <span class="dean-status dean-status--pending">For OSAS approval</span>
                                        @else
                                            <span class="dean-status dean-status--pending">Proposed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="dean-empty-table"><i class="ph ph-map-pin"></i><span>No events are using your premises right now.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="dean-panel__footnote">Advisory only — no action required. Approval stays with the org's own Dean and OSAS.</p>
            </article>
        </section>
        @endif

        <section class="dean-dashboard__grid dean-dashboard__grid--content">
            <article class="dean-panel dean-panel--table dean-panel--events">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">SCHEDULE</span>
                        <h3>Upcoming events</h3>
                    </div>
                    <a href="{{ route('dean.content-overview') }}?tab=events" class="dean-view-all">View all <i class="ph ph-arrow-right"></i></a>
                </div>
                <div class="dean-table-wrap">
                    <table class="dean-table">
                        <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th></th></tr></thead>
                        <tbody>
                            @forelse($events as $event)
                                @php $eventData = array_merge($event->toArray(), ['images' => transform_images($event->images ?? null), 'organization_name' => $event->organization->name ?? null]); @endphp
                                <tr class="dean-row-clickable" @click="$dispatch('open-event-modal', @js($eventData))" title="Click to view event details">
                                    <td><div class="dean-item-title"><span class="dean-item-icon dean-item-icon--blue dean-thumb">@if($event->images && count($event->images) > 0)<img src="{{ storage_url($event->images[0]) }}" alt="" loading="lazy">@else<i class="ph ph-calendar-dot"></i>@endif</span><div><strong>{{ $event->title }}</strong><small>Published {{ \Carbon\Carbon::parse($event->created_at)->diffForHumans() }}</small></div></div></td>
                                    <td class="dean-table__date">{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y') : '—' }}</td>
                                    <td class="dean-table__muted">{{ $event->venue ?: 'Venue to be confirmed' }}</td>
                                    <td class="dean-table__action"><span class="dean-table__view" aria-label="View {{ $event->title }}"><i class="ph ph-arrow-square-out"></i></span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="dean-empty-table"><i class="ph ph-calendar-x"></i><span>No upcoming events scheduled.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="dean-panel dean-panel--list">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">INBOX</span>
                        <h3>Recent announcements</h3>
                    </div>
                    <a href="{{ route('dean.content-overview') }}?tab=announcements" class="dean-view-all">View all <i class="ph ph-arrow-right"></i></a>
                </div>
                <div class="dean-list">
                    @forelse($recentAnnouncements as $announcement)
                        @php $announcementData = array_merge($announcement->toArray(), ['images' => transform_images($announcement->images ?? null)]); @endphp
                        <button type="button" @click="$dispatch('open-announcement-modal', @js($announcementData))" class="dean-list-item" title="Click to view announcement">
                            <span class="dean-list-item__marker dean-thumb {{ $announcement->type === 'urgent' ? 'dean-list-item__marker--urgent' : '' }}">@if($announcement->images && count($announcement->images) > 0)<img src="{{ storage_url($announcement->images[0]) }}" alt="" loading="lazy">@else<i class="ph {{ $announcement->type === 'urgent' ? 'ph-warning' : 'ph-megaphone' }}"></i>@endif</span>
                            <span class="dean-list-item__body"><strong>{{ $announcement->title }}</strong><small>{{ ucfirst($announcement->type ?? 'normal') }} · {{ $announcement->created_at->diffForHumans() }}</small></span>
                            <i class="ph ph-caret-right dean-list-item__arrow"></i>
                        </button>
                    @empty
                        <div class="dean-empty-state"><span><i class="ph ph-megaphone"></i></span><div><strong>No announcements yet</strong><p>Published department updates will appear here.</p></div></div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="dean-dashboard__grid dean-dashboard__grid--bottom">
            <article class="dean-panel dean-panel--list dean-panel--complaints">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">FOLLOW-UP</span>
                        <h3>Filed complaints</h3>
                    </div>
                    <a href="{{ route('dean.content-overview') }}?tab=complaints" class="dean-view-all dean-view-all--red">View all <i class="ph ph-arrow-right"></i></a>
                </div>
                <div class="dean-list">
                    @forelse($pendingComplaints as $complaint)
                        <a href="{{ route('dean.content-overview') }}?view_complaint={{ $complaint->id }}" class="dean-list-item">
                            <span class="dean-list-item__marker dean-list-item__marker--red"><i class="ph ph-flag"></i></span>
                            <span class="dean-list-item__body"><strong>{{ $complaint->subject }}</strong><small>{{ $complaint->type ?: 'General concern' }} · {{ $complaint->created_at->diffForHumans() }}</small></span>
                            <span class="dean-status dean-status--pending">Pending</span>
                        </a>
                    @empty
                        <div class="dean-empty-state"><span class="dean-empty-state__success"><i class="ph ph-check"></i></span><div><strong>No pending complaints</strong><p>There are no open follow-ups in the department.</p></div></div>
                    @endforelse
                </div>
            </article>

            <article class="dean-panel dean-panel--directory">
                <div class="dean-panel__header">
                    <div>
                        <span class="dean-panel__eyebrow">QUICK ACCESS</span>
                        <h3>Common tasks</h3>
                    </div>
                    <span class="dean-panel__header-icon dean-panel__header-icon--teal"><i class="ph ph-lightning"></i></span>
                </div>
                <div class="dean-task-grid">
                    <a href="{{ route('dean.proposals.index') }}"><span class="dean-task-icon dean-task-icon--purple"><i class="ph ph-signature"></i></span><span><strong>Event proposals</strong><small>Review submissions</small></span><i class="ph ph-arrow-right"></i></a>
                    <a href="{{ route('dean.users.index') }}"><span class="dean-task-icon dean-task-icon--blue"><i class="ph ph-user-gear"></i></span><span><strong>Manage users</strong><small>View department accounts</small></span><i class="ph ph-arrow-right"></i></a>
                    <a href="{{ route('dean.registration-reports') }}"><span class="dean-task-icon dean-task-icon--gold"><i class="ph ph-chart-pie"></i></span><span><strong>Registration reports</strong><small>Check participation data</small></span><i class="ph ph-arrow-right"></i></a>
                    <a href="{{ route('dean.financial-reports.index') }}"><span class="dean-task-icon dean-task-icon--teal"><i class="ph ph-coins"></i></span><span><strong>Financial reports</strong><small>Review fund submissions</small></span><i class="ph ph-arrow-right"></i></a>
                </div>
            </article>
        </section>
    </div>
</div>
<style>
    .dean-dashboard {
        --dd-accent: var(--primary-color, #8238b3);
        --dd-gold: var(--complementary-color, #d7ac28);
        --dd-ink: #20212b;
        --dd-muted: #727582;
        --dd-line: #e7e8ed;
        --dd-surface: #ffffff;
        --dd-bg: #f7f7fa;
        color: var(--dd-ink);
        font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
        background: var(--dd-bg);
        min-height: calc(100vh - 64px);
    }

    .dean-dashboard__shell { width: min(100%, 1680px); margin: 0 auto; padding: 28px clamp(20px, 2.4vw, 42px) 42px; }
    .dean-dashboard__header { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
    .dean-eyebrow, .dean-panel__eyebrow, .dean-section-heading__eyebrow { color: var(--dd-muted); font-size: 10px; font-weight: 800; letter-spacing: .14em; line-height: 1.2; text-transform: uppercase; }
    .dean-eyebrow { margin-bottom: 8px; }
    .dean-dashboard__header h1 { margin: 0; color: var(--dd-ink); font-size: clamp(25px, 2.1vw, 34px); font-weight: 800; letter-spacing: -.04em; line-height: 1.1; }
    .dean-dashboard__header p { margin: 7px 0 0; color: var(--dd-muted); font-size: 13px; line-height: 1.5; }
    .dean-dashboard__actions { display: flex; align-items: center; gap: 9px; flex-shrink: 0; }
    .dean-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 0 13px; border: 1px solid transparent; border-radius: 7px; font-size: 11px; font-weight: 750; text-decoration: none; transition: border-color .18s ease, background .18s ease, transform .18s ease; }
    .dean-btn:hover { transform: translateY(-1px); }
    .dean-btn--secondary { border-color: var(--dd-line); background: var(--dd-surface); color: #555967; }
    .dean-btn--secondary:hover { border-color: #cfd1d9; background: #fbfbfc; }
    .dean-btn--primary { background: var(--dd-accent); color: #fff; box-shadow: 0 4px 10px rgba(var(--primary-color-rgb, 130, 56, 179), .16); }
    .dean-btn--primary:hover { filter: brightness(.95); }
    .dean-btn__count { display: inline-flex; align-items: center; justify-content: center; min-width: 19px; height: 19px; padding: 0 5px; border-radius: 10px; background: rgba(255,255,255,.2); font-size: 10px; }

    .dean-metrics { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-bottom: 28px; }
    .dean-metric { min-width: 0; padding: 14px 15px 13px; border: 1px solid var(--dd-line); border-radius: 8px; background: var(--dd-surface); box-shadow: 0 1px 2px rgba(32, 33, 43, .025); }
    .dean-metric--action { display: block; color: inherit; text-decoration: none; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .dean-metric--action:hover { border-color: color-mix(in srgb, var(--dd-accent) 35%, var(--dd-line)); box-shadow: 0 5px 14px rgba(32,33,43,.07); transform: translateY(-1px); }
    .dean-metric__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    .dean-metric__label { overflow: hidden; color: var(--dd-muted); font-size: 10px; font-weight: 750; letter-spacing: .04em; text-overflow: ellipsis; text-transform: uppercase; white-space: nowrap; }
    .dean-metric__icon { display: inline-flex; align-items: center; justify-content: center; width: 25px; height: 25px; border-radius: 6px; font-size: 11px; }
    .dean-metric strong { display: block; color: var(--dd-ink); font-size: 23px; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .dean-metric__meta { display: block; overflow: hidden; margin-top: 7px; color: #9a9da6; font-size: 10px; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
    .dean-metric__meta i { margin-left: 3px; font-size: 9px; }
    .dean-metric[data-tone="purple"] .dean-metric__icon { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dd-accent); }
    .dean-metric[data-tone="gold"] .dean-metric__icon { background: #fbf5e4; color: #ad8211; }
    .dean-metric[data-tone="blue"] .dean-metric__icon { background: #eaf2fb; color: #3173b8; }
    .dean-metric[data-tone="teal"] .dean-metric__icon { background: #e8f5f2; color: #16816f; }
    .dean-metric[data-tone="amber"] .dean-metric__icon { background: #fff1dc; color: #bc7415; }
    .dean-metric[data-tone="red"] .dean-metric__icon { background: #fcecee; color: #c24d5b; }

    .dean-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin: 0 0 11px; }
    .dean-section-heading h2 { margin: 4px 0 0; color: var(--dd-ink); font-size: 16px; font-weight: 800; letter-spacing: -.02em; }

    .dean-dashboard__grid { display: grid; gap: 12px; margin-bottom: 12px; }
    .dean-dashboard__grid--top { grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); }
    .dean-dashboard__grid--content { grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); }
    .dean-dashboard__grid--bottom { grid-template-columns: minmax(0, 1fr) minmax(320px, 1fr); }
    .dean-panel { min-width: 0; border: 1px solid var(--dd-line); border-radius: 8px; background: var(--dd-surface); box-shadow: 0 1px 2px rgba(32,33,43,.02); }
    .dean-panel__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 16px 18px 13px; border-bottom: 1px solid #eff0f3; }
    .dean-panel__header h3 { margin: 5px 0 0; color: var(--dd-ink); font-size: 14px; font-weight: 800; letter-spacing: -.02em; }
    .dean-panel__header-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px; font-size: 12px; }
    .dean-panel__header-icon--amber { background: #fff3e3; color: #b87517; }
    .dean-panel__header-icon--purple { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dd-accent); }
    .dean-panel__header-icon--teal { background: #e8f5f2; color: #16816f; }
    .dean-view-all { display: inline-flex; align-items: center; gap: 7px; margin-top: 3px; color: var(--dd-accent); font-size: 10px; font-weight: 800; text-decoration: none; white-space: nowrap; }
    .dean-view-all:hover { text-decoration: underline; }
    .dean-view-all--red { color: #c24d5b; }

    .dean-panel--queue { padding-bottom: 16px; }
    .dean-queue-summary { display: flex; align-items: flex-start; gap: 13px; padding: 16px 18px 10px; }
    .dean-queue-summary__number { display: flex; align-items: center; justify-content: center; width: 47px; height: 47px; flex: 0 0 47px; border-radius: 8px; background: #fff4e5; color: #b87517; font-size: 22px; font-weight: 800; letter-spacing: -.04em; }
    .dean-queue-summary strong { display: block; margin-top: 2px; font-size: 12px; font-weight: 800; }
    .dean-queue-summary p { max-width: 470px; margin: 5px 0 0; color: var(--dd-muted); font-size: 11px; line-height: 1.45; }
    .dean-inline-action { display: inline-flex; align-items: center; gap: 8px; margin: 0 18px 15px 78px; font-size: 10px; font-weight: 800; text-decoration: none; }
    .dean-inline-action--amber { color: #a66e12; }
    .dean-inline-action:hover { text-decoration: underline; }
    .dean-empty-state { display: flex; align-items: center; gap: 11px; padding: 17px 18px; }
    .dean-empty-state > span { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; background: #f1f8f4; color: #209568; font-size: 13px; }
    .dean-empty-state strong { font-size: 12px; font-weight: 800; }
    .dean-empty-state p { margin: 3px 0 0; color: var(--dd-muted); font-size: 10px; line-height: 1.4; }
    .dean-empty-state--compact { padding: 16px 18px 22px; }
    .dean-empty-state__success { background: #f1f8f4 !important; color: #209568 !important; }
    .dean-process-list { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 0 18px; }
    .dean-process-list > div { display: flex; align-items: flex-start; gap: 7px; padding-top: 11px; border-top: 1px solid var(--dd-line); }
    .dean-process-list > div > span { color: #b2b4bc; font-size: 9px; font-weight: 800; }
    .dean-process-list p { margin: 0; }
    .dean-process-list strong, .dean-process-list small { display: block; }
    .dean-process-list strong { color: #4f525d; font-size: 10px; font-weight: 800; }
    .dean-process-list small { margin-top: 3px; color: #92959e; font-size: 9px; line-height: 1.3; }

    .dean-panel--accounts { padding-bottom: 0; }
    .dean-account-total { display: flex; align-items: baseline; gap: 7px; padding: 15px 18px 11px; }
    .dean-account-total strong { font-size: 26px; font-weight: 800; letter-spacing: -.05em; }
    .dean-account-total span { color: var(--dd-muted); font-size: 10px; }
    .dean-account-bar { display: flex; height: 8px; margin: 0 18px 14px; overflow: hidden; border-radius: 4px; background: #f0f0f3; }
    .dean-account-bar span:first-child { background: var(--dd-accent); }
    .dean-account-bar span:last-child { background: var(--dd-gold); }
    .dean-account-legend { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 18px 15px; }
    .dean-account-legend > div { display: flex; align-items: flex-start; gap: 7px; }
    .dean-legend-dot { width: 7px; height: 7px; flex: 0 0 7px; margin-top: 4px; border-radius: 50%; }
    .dean-legend-dot--purple { background: var(--dd-accent); }
    .dean-legend-dot--gold { background: var(--dd-gold); }
    .dean-account-legend strong, .dean-account-legend small { display: block; }
    .dean-account-legend strong { font-size: 12px; font-weight: 800; }
    .dean-account-legend small { margin-top: 2px; color: var(--dd-muted); font-size: 9px; }
    .dean-shortcuts { border-top: 1px solid #eff0f3; }
    .dean-shortcuts a { display: flex; align-items: center; gap: 9px; padding: 11px 18px; color: #5c5f69; font-size: 10px; font-weight: 750; text-decoration: none; }
    .dean-shortcuts a + a { border-top: 1px solid #f3f3f5; }
    .dean-shortcuts a:hover { color: var(--dd-accent); background: #fcfbfd; }
    .dean-shortcuts a > i:first-child { width: 14px; color: var(--dd-accent); text-align: center; }
    .dean-shortcuts a > i:last-child { margin-left: auto; color: #b5b7bf; font-size: 9px; }

    .dean-panel--table, .dean-panel--list { overflow: hidden; }
    .dean-table-wrap { overflow-x: auto; }
    .dean-table { width: 100%; min-width: 590px; border-collapse: collapse; }
    .dean-table th { padding: 10px 18px; border-bottom: 1px solid #eff0f3; color: #999ca5; font-size: 9px; font-weight: 800; letter-spacing: .1em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .dean-table td { padding: 10px 18px; border-bottom: 1px solid #f0f1f3; font-size: 10px; vertical-align: middle; }
    .dean-table tbody tr:last-child td { border-bottom: 0; }
    .dean-table tbody tr { transition: background .15s ease; }
    .dean-table tbody tr:hover { background: #fcfbfd; }
    .dean-item-title { display: flex; align-items: center; gap: 10px; min-width: 200px; }
    .dean-item-icon { display: inline-flex; align-items: center; justify-content: center; width: 29px; height: 29px; flex: 0 0 29px; border-radius: 7px; font-size: 11px; }
    .dean-item-icon--blue { background: #eaf2fb; color: #3173b8; }
    .dean-item-title strong, .dean-item-title small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dean-item-title strong { max-width: 265px; color: #3c3e48; font-size: 11px; font-weight: 800; }
    .dean-item-title small { max-width: 265px; margin-top: 3px; color: #9a9da6; font-size: 9px; }
    .dean-table__date { color: #575a65; font-weight: 750; white-space: nowrap; }
    .dean-table__muted { max-width: 150px; color: #858893; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dean-table__action { width: 35px; text-align: right; }
    .dean-table__action a { color: #a7a9b1; font-size: 10px; }
    .dean-table__action a:hover { color: var(--dd-accent); }
    .dean-empty-table { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 26px 10px; color: #9a9da6; font-size: 10px; }
    .dean-empty-table i { color: #c4c6cd; font-size: 13px; }

    .dean-list { padding: 2px 0; }
    .dean-list-item { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 11px 18px; border-bottom: 1px solid #f0f1f3; color: inherit; text-decoration: none; transition: background .15s ease; }
    .dean-list-item:last-child { border-bottom: 0; }
    .dean-list-item:hover { background: #fcfbfd; }
    .dean-list-item__marker { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; flex: 0 0 28px; border-radius: 7px; background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dd-accent); font-size: 10px; }
    .dean-list-item__marker--urgent { background: #fcecee; color: #c24d5b; }
    .dean-list-item__marker--red { background: #fcecee; color: #c24d5b; }
    .dean-list-item__body { min-width: 0; flex: 1; }
    .dean-list-item__body strong, .dean-list-item__body small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dean-list-item__body strong { color: #41434d; font-size: 11px; font-weight: 800; }
    .dean-list-item__body small { margin-top: 3px; color: #989aa3; font-size: 9px; }
    .dean-list-item__arrow { color: #b9bbc2; font-size: 9px; }
    .dean-status { padding: 4px 7px; border-radius: 5px; font-size: 9px; font-weight: 800; white-space: nowrap; }
    .dean-status--pending { background: #fff3e3; color: #a66e12; }
    .dean-status--approved { background: #eaf8f1; color: #218252; }
    .dean-status--rejected { background: #fcecee; color: #c24d5b; }
    .dean-panel__footnote { padding: 10px 18px 14px; color: #989aa3; font-size: 10px; }
    .dean-panel--premises { grid-column: 1 / -1; }
    #premises-notices { scroll-margin-top: 88px; }

    .dean-task-grid { display: grid; grid-template-columns: 1fr 1fr; }
    .dean-task-grid a { display: flex; align-items: center; gap: 9px; min-width: 0; padding: 14px 15px; border-bottom: 1px solid #eff0f3; color: inherit; text-decoration: none; transition: background .15s ease; }
    .dean-task-grid a:nth-child(odd) { border-right: 1px solid #eff0f3; }
    .dean-task-grid a:hover { background: #fcfbfd; }
    .dean-task-icon { display: inline-flex; align-items: center; justify-content: center; width: 27px; height: 27px; flex: 0 0 27px; border-radius: 7px; font-size: 10px; }
    .dean-task-icon--purple { background: rgba(var(--primary-color-rgb, 130, 56, 179), .1); color: var(--dd-accent); }
    .dean-task-icon--blue { background: #eaf2fb; color: #3173b8; }
    .dean-task-icon--gold { background: #fbf5e4; color: #ad8211; }
    .dean-task-icon--teal { background: #e8f5f2; color: #16816f; }
    .dean-task-grid a > span:nth-child(2) { min-width: 0; flex: 1; }
    .dean-task-grid strong, .dean-task-grid small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dean-task-grid strong { color: #4a4c56; font-size: 10px; font-weight: 800; }
    .dean-task-grid small { margin-top: 3px; color: #989aa3; font-size: 9px; }
    .dean-task-grid a > i { color: #b6b8c0; font-size: 9px; }

    @media (max-width: 1280px) {
        .dean-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 960px) {
        .dean-dashboard__header { align-items: flex-start; flex-direction: column; }
        .dean-dashboard__actions { width: 100%; }
        .dean-dashboard__actions .dean-btn { flex: 1; }
        .dean-dashboard__grid--top, .dean-dashboard__grid--content, .dean-dashboard__grid--bottom { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .dean-dashboard__shell { padding: 20px 14px 30px; }
        .dean-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 22px; }
        .dean-metric { padding: 12px; }
        .dean-metric strong { font-size: 21px; }
        .dean-panel__header { padding: 14px; }
        .dean-process-list { grid-template-columns: 1fr; gap: 0; padding: 0 14px; }
        .dean-process-list > div { padding: 9px 0; }
        .dean-process-list > div:first-child { border-top: 1px solid var(--dd-line); }
        .dean-queue-summary { padding-left: 14px; padding-right: 14px; }
        .dean-inline-action { margin-left: 72px; }
        .dean-account-total, .dean-account-legend { padding-left: 14px; padding-right: 14px; }
        .dean-account-bar { margin-left: 14px; margin-right: 14px; }
        .dean-shortcuts a { padding-left: 14px; padding-right: 14px; }
        .dean-list-item { padding-left: 14px; padding-right: 14px; }
        .dean-task-grid { grid-template-columns: 1fr; }
        .dean-task-grid a:nth-child(odd) { border-right: 0; }
        .dean-task-grid a { padding-left: 14px; padding-right: 14px; }
    .dean-thumb { overflow: hidden; }
    .dean-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
    .dean-row-clickable { cursor: pointer; }
    .dean-list-item { width: 100%; border: 0; background: transparent; font: inherit; text-align: left; cursor: pointer; }
    .dean-table__view { color: #a7a9b1; font-size: 10px; }
    .dean-row-clickable:hover .dean-table__view { color: var(--dd-accent); }
</style>

<!-- Read-only event details modal (dashboard quick view, no actions) -->
<div x-data="{ showModal: false, event: null, currentImage: 0, openModal(item) { this.event = item; this.currentImage = 0; this.showModal = true; }, cleanHTML(html) { return html ? html.replace(/&nbsp;/g, ' ').replace(/\s\s+/g, ' ') : ''; } }" @open-event-modal.window="openModal($event.detail)" @keydown.escape.window="showModal = false" x-show="showModal" x-cloak @click.self="showModal = false" class="dean-dash-modal">
    <div class="dean-dash-modal__dialog" x-show="event">
        <button type="button" @click="showModal = false" class="dean-dash-modal__close" aria-label="Close event"><i class="ph ph-x"></i></button>
        <div class="dean-dash-modal__grid">
            <div class="dean-dash-modal__media">
                <template x-if="event && event.images && event.images.length > 0"><div class="dean-dash-modal__carousel"><template x-for="(image, index) in event.images" :key="index"><img x-show="currentImage === index" :src="image" :alt="event.title"></template><template x-if="event.images.length > 1"><div><button type="button" class="dean-dash-modal__nav dean-dash-modal__nav--prev" @click="currentImage = currentImage > 0 ? currentImage - 1 : event.images.length - 1"><i class="ph ph-caret-left"></i></button><button type="button" class="dean-dash-modal__nav dean-dash-modal__nav--next" @click="currentImage = currentImage < event.images.length - 1 ? currentImage + 1 : 0"><i class="ph ph-caret-right"></i></button><span class="dean-dash-modal__counter"><span x-text="currentImage + 1"></span> / <span x-text="event.images.length"></span></span></div></template></div></template>
                <template x-if="!event?.images || event.images.length === 0"><div class="dean-dash-modal__fallback dean-dash-modal__fallback--blue"><i class="ph ph-calendar-blank"></i></div></template>
            </div>
            <div class="dean-dash-modal__body"><span class="dean-dash-modal__eyebrow"><i class="ph ph-calendar"></i> <span x-text="event?.start_date ? new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Date to be confirmed'"></span></span><h2 x-text="event?.title"></h2><div class="dean-dash-facts"><div><small>Venue</small><strong x-text="event?.venue || 'To be confirmed'"></strong></div><div><small>Organization</small><strong x-text="event?.organization_name || (event?.organization ? (event.organization.org_acronym || event.organization.name) : '')"></strong></div></div><h3>Event details</h3><div class="dean-dash-modal__copy" x-html="cleanHTML(event?.description)"></div><p class="dean-dash-modal__note">Read-only preview — approval stays with the org's own Dean and OSAS.</p></div>
        </div>
    </div>
</div>

<!-- Read-only announcement details modal (dashboard quick view) -->
<div x-data="{ showModal: false, announcement: null, currentImage: 0, openModal(item) { this.announcement = item; this.currentImage = 0; this.showModal = true; }, cleanHTML(html) { return html ? html.replace(/&nbsp;/g, ' ').replace(/\s\s+/g, ' ') : ''; } }" @open-announcement-modal.window="openModal($event.detail)" @keydown.escape.window="showModal = false" x-show="showModal" x-cloak @click.self="showModal = false" class="dean-dash-modal">
    <div class="dean-dash-modal__dialog" x-show="announcement">
        <button type="button" @click="showModal = false" class="dean-dash-modal__close" aria-label="Close announcement"><i class="ph ph-x"></i></button>
        <div class="dean-dash-modal__grid">
            <div class="dean-dash-modal__media">
                <template x-if="announcement && announcement.images && announcement.images.length > 0"><div class="dean-dash-modal__carousel"><template x-for="(image, index) in announcement.images" :key="index"><img x-show="currentImage === index" :src="image" :alt="announcement.title"></template><template x-if="announcement.images.length > 1"><div><button type="button" class="dean-dash-modal__nav dean-dash-modal__nav--prev" @click="currentImage = currentImage > 0 ? currentImage - 1 : announcement.images.length - 1"><i class="ph ph-caret-left"></i></button><button type="button" class="dean-dash-modal__nav dean-dash-modal__nav--next" @click="currentImage = currentImage < announcement.images.length - 1 ? currentImage + 1 : 0"><i class="ph ph-caret-right"></i></button><span class="dean-dash-modal__counter"><span x-text="currentImage + 1"></span> / <span x-text="announcement.images.length"></span></span></div></template></div></template>
                <template x-if="!announcement?.images || announcement.images.length === 0"><div class="dean-dash-modal__fallback dean-dash-modal__fallback--purple"><i class="ph ph-megaphone"></i></div></template>
            </div>
            <div class="dean-dash-modal__body"><span class="dean-dash-modal__eyebrow"><i class="ph ph-clock"></i> <span x-text="announcement?.created_at ? new Date(announcement.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''"></span></span><h2 x-text="announcement?.title"></h2><div class="dean-dash-modal__copy" x-html="cleanHTML(announcement?.content)"></div></div>
        </div>
    </div>
</div>
<style>
    .dean-dash-modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(31, 32, 42, .58); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px); }
    .dean-dash-modal__dialog { position: relative; width: min(100%, 920px); max-height: min(680px, calc(100vh - 40px)); overflow: hidden; border-radius: 9px; background: #fff; box-shadow: 0 20px 55px rgba(20, 20, 30, .25); }
    .dean-dash-modal__close { position: absolute; top: 12px; right: 12px; z-index: 2; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 6px; background: rgba(255,255,255,.92); color: #656873; box-shadow: 0 2px 8px rgba(0,0,0,.12); cursor: pointer; }
    .dean-dash-modal__close:hover { color: #252631; }
    .dean-dash-modal__grid { display: grid; grid-template-columns: minmax(280px, .9fr) minmax(0, 1.1fr); max-height: inherit; }
    .dean-dash-modal__media { min-height: 420px; background: #f0f1f4; }
    .dean-dash-modal__carousel, .dean-dash-modal__carousel > img { position: relative; width: 100%; height: 100%; min-height: 420px; }
    .dean-dash-modal__carousel > img { position: absolute; inset: 0; object-fit: contain; }
    .dean-dash-modal__fallback { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; min-height: 420px; color: #fff; font-size: 64px; }
    .dean-dash-modal__fallback--purple { background: linear-gradient(135deg, #a268c5, #6f2f9a); }
    .dean-dash-modal__fallback--blue { background: linear-gradient(135deg, #5c8fe5, #2455ae); }
    .dean-dash-modal__nav { position: absolute; top: 50%; display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 0; border-radius: 6px; background: rgba(30,30,40,.5); color: #fff; cursor: pointer; transform: translateY(-50%); }
    .dean-dash-modal__nav--prev { left: 14px; }
    .dean-dash-modal__nav--next { right: 14px; }
    .dean-dash-modal__counter { position: absolute; right: 14px; bottom: 14px; padding: 5px 8px; border-radius: 4px; background: rgba(30,30,40,.62); color: #fff; font-size: 10px; }
    .dean-dash-modal__body { max-height: min(680px, calc(100vh - 40px)); overflow: auto; padding: 34px 34px 30px; }
    .dean-dash-modal__eyebrow { display: inline-flex; align-items: center; gap: 7px; color: #8b8d96; font-size: 10px; font-weight: 750; }
    .dean-dash-modal__eyebrow i { color: #d7ac28; }
    .dean-dash-modal__body h2 { margin: 13px 0 20px; color: #24252f; font-size: 25px; font-weight: 800; letter-spacing: -.04em; line-height: 1.15; }
    .dean-dash-modal__body h3 { margin: 22px 0 0; color: #363742; font-size: 13px; font-weight: 800; }
    .dean-dash-modal__copy { margin-top: 20px; color: #62646f; font-size: 12px; line-height: 1.7; white-space: pre-line; }
    .dean-dash-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 13px 0; border-top: 1px solid #e7e8ed; border-bottom: 1px solid #e7e8ed; }
    .dean-dash-facts small { display: block; color: #989aa3; font-size: 9px; text-transform: uppercase; letter-spacing: .08em; }
    .dean-dash-facts strong { display: block; margin-top: 3px; color: #4c4e59; font-size: 11px; }
    .dean-dash-modal__note { margin-top: 18px; color: #989aa3; font-size: 10px; }
    @media (max-width: 760px) { .dean-dash-modal { padding: 10px; } .dean-dash-modal__dialog { max-height: calc(100vh - 20px); overflow: auto; } .dean-dash-modal__grid { grid-template-columns: 1fr; } .dean-dash-modal__media, .dean-dash-modal__carousel, .dean-dash-modal__carousel > img, .dean-dash-modal__fallback { min-height: 240px; height: 240px; } .dean-dash-modal__body { max-height: none; padding: 25px 20px 24px; } }
</style>
@endsection

@push('scripts')
<script>
    // Premises deep-link UX: land on top first, then glide down smoothly so
    // the section heading is fully visible (used by venue-use notifications).
    (function () {
        if (window.location.hash !== '#premises-notices') return;
        const glide = () => {
            const el = document.getElementById('premises-notices');
            if (!el) return;
            window.scrollTo(0, 0);
            setTimeout(() => {
                const target = document.getElementById('premises-notices');
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 500);
        };
        if (document.readyState === 'complete') glide();
        else window.addEventListener('load', glide);
    })();
</script>
@endpush
