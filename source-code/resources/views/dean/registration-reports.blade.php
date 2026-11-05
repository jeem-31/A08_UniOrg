@extends('layouts.dean')

@section('title', 'Registration Reports')

@php
    $eventCount = $eventRegistrations->total();
    $participants = (int) ($totalParticipants ?? $eventRegistrations->getCollection()->sum('registrations_count'));
    $registeredStudents = (int) ($totalRegistrations ?? 0);
    $averageParticipants = $eventCount > 0 ? round($participants / $eventCount, 1) : 0;
@endphp

@section('content')
<div class="dean-registration-page" x-data="{
    showModal: false,
    isExporting: false,
    exportError: '',
    selectedEvent: null,
    selectedRegistrations: [],
    openDetails(event, registrations) {
        this.selectedEvent = event;
        this.selectedRegistrations = registrations;
        this.showModal = true;
    },
    async exportReport() {
        if (this.isExporting) return;
        this.isExporting = true;
        this.exportError = '';

        try {
            const response = await fetch('{{ route('dean.registration-reports.export') }}', {
                method: 'GET',
                headers: { 'Accept': 'application/pdf' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('The report could not be generated.');
            }

            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const filenameMatch = disposition.match(/filename\*?=([^;]+)/i);
            const filename = filenameMatch ? decodeURIComponent(filenameMatch[1].trim()).replaceAll(String.fromCharCode(34), '').replaceAll(String.fromCharCode(39), '') : 'registration-reports-{{ now()->format('Y-m-d') }}.pdf';
            const downloadUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
        } catch (error) {
            console.error('Registration report export failed:', error);
            this.exportError = 'Unable to download the report. Please try again.';
        } finally {
            this.isExporting = false;
        }
    }
}">
    <div class="dean-registration-page__shell">
        <header class="dean-registration-header">
            <div>
                <div class="dean-registration-eyebrow">Participation analytics</div>
                <h1>Registration reports</h1>
                <p>Review event participation and inspect the students registered for each approved activity.</p>
            </div>
            <div class="dean-registration-header__actions">
                <a href="{{ route('dean.dashboard') }}" class="dean-registration-btn dean-registration-btn--secondary"><i class="ph ph-arrow-left"></i><span>Dashboard</span></a>
                <button type="button" class="dean-registration-btn dean-registration-btn--primary" @click="exportReport()" :disabled="isExporting"><i class="ph" :class="isExporting ? 'ph-spinner ph-spin' : 'ph-file-pdf'"></i><span x-text="isExporting ? 'Generating PDF...' : 'Export report'">Export report</span></button>
                <span x-cloak x-show="exportError" x-text="exportError" class="dean-registration-export-error" role="status"></span>
            </div>
        </header>

        <section class="dean-registration-metrics" aria-label="Registration summary">
            <div class="dean-registration-metric dean-registration-metric--purple"><span class="dean-registration-metric__icon"><i class="ph ph-calendar-check"></i></span><span><small>Events with registrations</small><strong>{{ number_format($eventCount) }}</strong><em>Approved department activities</em></span></div>
            <div class="dean-registration-metric dean-registration-metric--blue"><span class="dean-registration-metric__icon"><i class="ph ph-users"></i></span><span><small>Event participants</small><strong>{{ number_format($participants) }}</strong><em>Registrations across approved events</em></span></div>
            <div class="dean-registration-metric dean-registration-metric--gold"><span class="dean-registration-metric__icon"><i class="ph ph-student"></i></span><span><small>Registered students</small><strong>{{ number_format($registeredStudents) }}</strong><em>Students in the department</em></span></div>
        </section>

        <div class="dean-registration-workspace">
            <main class="dean-registration-panel">
                <div class="dean-registration-panel__header">
                    <div><span class="dean-registration-panel__eyebrow">REGISTRATION REGISTER</span><h2>Event participation</h2><p>Open an event to view its complete registration list.</p></div>
                    <span class="dean-registration-panel__count">{{ number_format($eventCount) }} events</span>
                </div>
                <div class="dean-registration-table-meta"><span>Latest approved activities</span><span>Average {{ number_format($averageParticipants, 1) }} participants per event</span></div>
                <div class="dean-registration-table-wrap">
                    <table class="dean-registration-table">
                        <thead><tr><th>Event</th><th>Date and venue</th><th>Participants</th><th><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                            @forelse($eventRegistrations as $event)
                                @php
                                    $registrationData = $event->registrations->map(function ($registration) {
                                        $user = $registration->user;
                                        $yearNumber = $user && $user->year_level ? substr((string) $user->year_level, 0, 1) : '';
                                        return [
                                            'first_name' => $user->first_name ?? 'Unknown',
                                            'last_name' => $user->last_name ?? 'student',
                                            'student_id' => $user->student_id ?? 'Not available',
                                            'course' => $user->course ?? 'Course not set',
                                            'section' => trim($yearNumber . ($user->section ?? '')) ?: 'Not set',
                                            'registered_at' => $registration->registered_at ? $registration->registered_at->format('M d, Y h:i A') : 'Not available',
                                        ];
                                    })->values()->all();
                                    $eventSummary = ['title' => $event->title, 'start_date' => $event->start_date ? $event->start_date->format('M d, Y h:i A') : 'Date to be confirmed', 'venue' => $event->venue ?: 'Venue to be confirmed'];
                                @endphp
                                <tr>
                                    <td><div class="dean-registration-event"><span class="dean-registration-event__icon">@if($event->images && count($event->images) > 0)<img src="{{ storage_url($event->images[0]) }}" alt="" loading="lazy">@else<i class="ph ph-calendar-dot"></i>@endif</span><div><strong>{{ $event->title }}</strong><small>{{ Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($event->description ?? '')))), 92) }}</small></div></div></td>
                                    <td><strong class="dean-registration-date">{{ $event->start_date ? $event->start_date->format('M d, Y') : 'Date to be confirmed' }}</strong><span class="dean-registration-venue">{{ $event->venue ?: 'Venue to be confirmed' }}</span></td>
                                    <td><span class="dean-registration-count"><i class="ph ph-users"></i>{{ number_format($event->registrations_count) }}</span></td>
                                    <td class="dean-registration-action"><button type="button" @click="openDetails(@js($eventSummary), @js($registrationData))"><i class="ph ph-arrow-square-out"></i><span>View details</span></button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="dean-registration-empty"><span><i class="ph ph-calendar-x"></i></span><strong>No registered events</strong><p>Event registrations will appear here when students join approved activities.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($eventRegistrations->hasPages())
                    <div class="dean-registration-pagination">{{ $eventRegistrations->onEachSide(1)->links() }}</div>
                @endif
            </main>

            <aside class="dean-registration-sidebar">
                <div class="dean-registration-sidebar__header"><div><span class="dean-registration-panel__eyebrow">AT A GLANCE</span><h2>Participation pulse</h2></div><span class="dean-registration-sidebar__icon"><i class="ph ph-chart-line-up"></i></span></div>
                <div class="dean-registration-pulse"><div><span>Participants</span><strong>{{ number_format($participants) }}</strong></div><div class="dean-registration-pulse__bar"><span style="width: {{ min(100, $participants > 0 ? 100 : 0) }}%"></span></div><small>Recorded across approved events</small></div>
                <div class="dean-registration-sidebar__section"><div class="dean-registration-sidebar__label">REPORT CONTENT</div><div class="dean-registration-breakdown"><div><span><i class="ph ph-calendar-check"></i>Approved events</span><strong>{{ number_format($eventCount) }}</strong></div><div><span><i class="ph ph-users"></i>Total registrations</span><strong>{{ number_format($participants) }}</strong></div><div><span><i class="ph ph-student"></i>Department students</span><strong>{{ number_format($registeredStudents) }}</strong></div></div></div>
                <div class="dean-registration-sidebar__section"><div class="dean-registration-sidebar__label">QUICK NAVIGATION</div><div class="dean-registration-links"><a href="{{ route('dean.dashboard') }}"><span><i class="ph ph-gauge"></i></span><strong>Department dashboard</strong><i class="ph ph-caret-right"></i></a><a href="{{ route('dean.content-overview') }}"><span><i class="ph ph-squares-four"></i></span><strong>Content overview</strong><i class="ph ph-caret-right"></i></a></div></div>
            </aside>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="dean-registration-modal" @click.self="showModal = false" @keydown.escape.window="showModal = false">
        <div class="dean-registration-modal__dialog" x-show="selectedEvent">
            <div class="dean-registration-modal__header"><div><span class="dean-registration-panel__eyebrow">EVENT PARTICIPATION</span><h2 x-text="selectedEvent?.title"></h2><p><i class="ph ph-calendar"></i><span x-text="selectedEvent?.start_date"></span><span>·</span><i class="ph ph-map-pin"></i><span x-text="selectedEvent?.venue"></span></p></div><button type="button" @click="showModal = false" aria-label="Close details"><i class="ph ph-x"></i></button></div>
            <div class="dean-registration-modal__summary"><span><strong x-text="selectedRegistrations.length"></strong><small>registered students</small></span><span><strong>Approved</strong><small>event status</small></span></div>
            <div class="dean-registration-modal__table-wrap"><table class="dean-registration-modal__table"><thead><tr><th>Student</th><th>Student ID</th><th>Course</th><th>Section</th><th>Registered</th></tr></thead><tbody><template x-for="(registration, index) in selectedRegistrations" :key="index"><tr><td x-text="registration.first_name + ' ' + registration.last_name"></td><td x-text="registration.student_id"></td><td x-text="registration.course"></td><td x-text="registration.section"></td><td x-text="registration.registered_at"></td></tr></template><tr x-show="selectedRegistrations.length === 0"><td colspan="5" class="dean-registration-modal__empty">No registrations found.</td></tr></tbody></table></div>
            <div class="dean-registration-modal__footer"><button type="button" @click="showModal = false">Close</button></div>
        </div>
    </div>
</div>

<style>
    .dean-registration-page { --dr-ink:#20212b; --dr-muted:#727582; --dr-line:#e7e8ed; --dr-bg:#f7f7fa; --dr-surface:#fff; --dr-purple:var(--primary-color,#8238b3); --dr-gold:var(--complementary-color,#d7ac28); min-height:calc(100vh - 64px); background:var(--dr-bg); color:var(--dr-ink); font-family:'Plus Jakarta Sans','Inter',ui-sans-serif,system-ui,sans-serif; }
    .dean-registration-page__shell { width:min(100%,1680px); margin:0 auto; padding:28px clamp(20px,2.4vw,42px) 42px; }.dean-registration-header { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:22px; }.dean-registration-eyebrow,.dean-registration-panel__eyebrow,.dean-registration-sidebar__label { color:var(--dr-muted); font-size:10px; font-weight:800; letter-spacing:.14em; line-height:1.2; text-transform:uppercase; }.dean-registration-eyebrow { margin-bottom:8px; }.dean-registration-header h1 { margin:0; font-size:clamp(25px,2.1vw,34px); font-weight:800; letter-spacing:-.04em; line-height:1.1; }.dean-registration-header p { margin:7px 0 0; color:var(--dr-muted); font-size:13px; line-height:1.5; }.dean-registration-header__actions { display:flex; align-items:center; gap:9px; flex-shrink:0; }.dean-registration-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:38px; padding:0 13px; border:1px solid transparent; border-radius:7px; font-size:11px; font-weight:750; text-decoration:none; cursor:pointer; transition:transform .18s ease,background .18s ease,border-color .18s ease; }.dean-registration-btn:hover { transform:translateY(-1px); }.dean-registration-btn--secondary { border-color:var(--dr-line); background:#fff; color:#555967; }.dean-registration-btn--primary { min-width:136px; background:var(--dr-purple); color:#fff; box-shadow:0 4px 10px rgba(88,40,123,.16); }.dean-registration-btn--primary:hover { filter:brightness(.95); }.dean-registration-btn:disabled { cursor:not-allowed; opacity:.6; transform:none; }.dean-registration-export-error { max-width:190px; color:#c93636; font-size:9px; line-height:1.3; }
    .dean-registration-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:14px; }.dean-registration-metric { display:flex; align-items:center; gap:13px; min-height:76px; padding:15px 18px; border:1px solid var(--dr-line); border-radius:8px; background:#fff; box-shadow:0 1px 2px rgba(32,33,43,.025); }.dean-registration-metric__icon { display:inline-flex; align-items:center; justify-content:center; width:35px; height:35px; flex:0 0 35px; border-radius:7px; font-size:13px; }.dean-registration-metric > span:last-child { display:grid; min-width:0; }.dean-registration-metric small { color:#5c5f6a; font-size:10px; font-weight:750; }.dean-registration-metric strong { margin-top:2px; font-size:22px; letter-spacing:-.04em; line-height:1.05; }.dean-registration-metric em { margin-top:5px; overflow:hidden; color:var(--dr-muted); font-size:9px; font-style:normal; text-overflow:ellipsis; white-space:nowrap; }.dean-registration-metric--purple .dean-registration-metric__icon { background:#f3eafa; color:#8238b3; }.dean-registration-metric--blue .dean-registration-metric__icon { background:#eaf2ff; color:#2563eb; }.dean-registration-metric--gold .dean-registration-metric__icon { background:#fff7df; color:#ae7a08; }
    .dean-registration-workspace { display:grid; grid-template-columns:minmax(0,1fr) 292px; align-items:start; gap:10px; }.dean-registration-panel,.dean-registration-sidebar { border:1px solid var(--dr-line); border-radius:9px; background:#fff; box-shadow:0 1px 2px rgba(32,33,43,.025); }.dean-registration-panel { min-width:0; overflow:hidden; }.dean-registration-panel__header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px 20px 14px; border-bottom:1px solid var(--dr-line); }.dean-registration-panel__header h2,.dean-registration-sidebar h2 { margin:4px 0 0; font-size:17px; font-weight:800; letter-spacing:-.025em; }.dean-registration-panel__header p { margin:5px 0 0; color:var(--dr-muted); font-size:11px; }.dean-registration-panel__count { flex-shrink:0; margin-top:4px; color:var(--dr-muted); font-size:10px; font-weight:700; }.dean-registration-table-meta { display:flex; align-items:center; justify-content:space-between; padding:12px 20px 8px; color:#8a8c95; font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }.dean-registration-table-meta span:last-child { color:#a0a2aa; font-size:9px; letter-spacing:0; font-weight:700; text-transform:none; }.dean-registration-table-wrap { overflow-x:auto; }.dean-registration-table { width:100%; border-collapse:collapse; }.dean-registration-table th { padding:9px 12px; border-bottom:1px solid var(--dr-line); background:#fbfbfc; color:#83858f; font-size:9px; font-weight:800; letter-spacing:.08em; text-align:left; text-transform:uppercase; white-space:nowrap; }.dean-registration-table th:first-child,.dean-registration-table td:first-child { padding-left:20px; }.dean-registration-table th:last-child,.dean-registration-table td:last-child { padding-right:20px; }.dean-registration-table td { padding:11px 12px; border-bottom:1px solid #f0f0f3; color:#4d4f59; font-size:10px; vertical-align:middle; white-space:nowrap; }.dean-registration-table tbody tr:hover { background:#fafafd; }.dean-registration-event { display:flex; align-items:center; gap:9px; min-width:260px; }.dean-registration-event__icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; overflow:hidden; border-radius:7px; background:#f3eafa; color:#8238b3; font-size:11px; }.dean-registration-event__icon img { width:100%; height:100%; object-fit:cover; }.dean-registration-event div { display:grid; min-width:0; gap:3px; }.dean-registration-event strong { overflow:hidden; color:#30313c; font-size:10px; text-overflow:ellipsis; white-space:nowrap; }.dean-registration-event small { max-width:330px; overflow:hidden; color:#999ba4; font-size:9px; text-overflow:ellipsis; white-space:nowrap; }.dean-registration-date { display:block; color:#4d4f59; font-size:10px; }.dean-registration-venue { display:block; margin-top:3px; color:#999ba4; font-size:9px; }.dean-registration-count { display:inline-flex; align-items:center; gap:6px; padding:5px 7px; border-radius:5px; background:#eaf2ff; color:#2563eb; font-size:9px; font-weight:800; }.dean-registration-count i { font-size:9px; }.dean-registration-action { text-align:right; }.dean-registration-action button { display:inline-flex; align-items:center; gap:6px; border:0; background:transparent; color:var(--dr-purple); font-size:10px; font-weight:800; cursor:pointer; }.dean-registration-action button:hover { color:#5f237f; }.dean-registration-action button i { font-size:9px; }.dean-registration-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:270px; padding:30px 20px; text-align:center; }.dean-registration-empty span { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; margin-bottom:9px; border-radius:8px; background:#f1f1f5; color:#a6a8b1; }.dean-registration-empty strong { color:#4b4d58; font-size:12px; }.dean-registration-empty p { margin:4px 0 0; color:#888a93; font-size:10px; }.dean-registration-pagination { padding:11px 20px 14px; border-top:1px solid #f0f0f3; font-size:10px; }.dean-registration-pagination nav { display:flex; justify-content:flex-end; }.dean-registration-pagination svg { width:16px; }
    .dean-registration-sidebar { padding:18px; }.dean-registration-sidebar__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:17px; }.dean-registration-sidebar__icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:7px; background:#eaf2ff; color:#2563eb; font-size:12px; }.dean-registration-pulse { padding:13px; border:1px solid #dfe9f7; border-radius:7px; background:#f8fbff; }.dean-registration-pulse > div:first-child { display:flex; align-items:center; justify-content:space-between; color:#686a75; font-size:10px; }.dean-registration-pulse strong { color:#343540; font-size:14px; }.dean-registration-pulse__bar { height:7px; margin:13px 0 7px; overflow:hidden; border-radius:5px; background:#e3ebf6; }.dean-registration-pulse__bar span { display:block; height:100%; border-radius:5px; background:#2563eb; }.dean-registration-pulse small { color:#999ba4; font-size:9px; }.dean-registration-sidebar__section { padding:17px 0 0; }.dean-registration-breakdown { display:grid; gap:11px; margin-top:12px; }.dean-registration-breakdown > div { display:flex; align-items:center; justify-content:space-between; color:#666874; font-size:10px; }.dean-registration-breakdown span { display:inline-flex; align-items:center; gap:8px; }.dean-registration-breakdown i { width:15px; color:#92949d; text-align:center; }.dean-registration-breakdown strong { color:#343540; font-size:11px; }.dean-registration-links { display:grid; gap:2px; margin-top:8px; }.dean-registration-links a { display:flex; align-items:center; gap:9px; padding:8px 0; color:inherit; text-decoration:none; }.dean-registration-links a > span { display:inline-flex; align-items:center; justify-content:center; width:27px; height:27px; border-radius:6px; background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--dr-purple); font-size:11px; }.dean-registration-links strong { flex:1; color:#4d4f59; font-size:10px; }.dean-registration-links a > i { color:#b3b5bd; font-size:9px; }.dean-registration-links a:hover strong { color:var(--dr-purple); }
    .dean-registration-modal { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(31,32,42,.58); -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px); }.dean-registration-modal__dialog { width:min(100%,920px); max-height:min(680px,calc(100vh - 40px)); overflow:hidden; border-radius:9px; background:#fff; box-shadow:0 20px 55px rgba(20,20,30,.25); }.dean-registration-modal__header { display:flex; align-items:flex-start; justify-content:space-between; gap:15px; padding:22px 24px 17px; border-bottom:1px solid var(--dr-line); }.dean-registration-modal__header h2 { margin:5px 0 8px; color:#24252f; font-size:20px; font-weight:800; letter-spacing:-.03em; }.dean-registration-modal__header p { display:flex; align-items:center; gap:7px; margin:0; color:#81838d; font-size:10px; }.dean-registration-modal__header button { display:inline-flex; align-items:center; justify-content:center; width:29px; height:29px; border:0; border-radius:6px; background:#f1f1f5; color:#747681; cursor:pointer; }.dean-registration-modal__summary { display:flex; gap:32px; padding:13px 24px; border-bottom:1px solid #f0f0f3; background:#fbfbfc; }.dean-registration-modal__summary span { display:grid; gap:3px; }.dean-registration-modal__summary strong { color:#343540; font-size:14px; }.dean-registration-modal__summary small { color:#92949d; font-size:9px; }.dean-registration-modal__table-wrap { max-height:390px; overflow:auto; }.dean-registration-modal__table { width:100%; border-collapse:collapse; }.dean-registration-modal__table th { position:sticky; top:0; z-index:1; padding:9px 12px; background:#f3eafa; color:#63318a; font-size:9px; font-weight:800; letter-spacing:.07em; text-align:left; text-transform:uppercase; }.dean-registration-modal__table th:first-child,.dean-registration-modal__table td:first-child { padding-left:24px; }.dean-registration-modal__table td { padding:10px 12px; border-bottom:1px solid #f0f0f3; color:#62646f; font-size:10px; }.dean-registration-modal__empty { padding:25px !important; color:#92949d !important; text-align:center; }.dean-registration-modal__footer { display:flex; justify-content:flex-end; padding:13px 24px; border-top:1px solid var(--dr-line); }.dean-registration-modal__footer button { padding:8px 13px; border:1px solid var(--dr-line); border-radius:6px; background:#f7f7f9; color:#5f626d; font-size:10px; font-weight:800; cursor:pointer; }
    @media (max-width:1180px) { .dean-registration-workspace { grid-template-columns:minmax(0,1fr); }.dean-registration-sidebar { display:grid; grid-template-columns:minmax(0,1fr) minmax(220px,.8fr); column-gap:22px; }.dean-registration-sidebar__header,.dean-registration-pulse,.dean-registration-links { grid-column:1/-1; }.dean-registration-sidebar__section { padding-top:15px; } }
    @media (max-width:760px) { .dean-registration-page__shell { padding:20px 14px 30px; }.dean-registration-header { align-items:flex-start; flex-direction:column; gap:16px; }.dean-registration-header__actions { width:100%; }.dean-registration-btn { flex:1; }.dean-registration-metrics { grid-template-columns:1fr; }.dean-registration-panel__header { padding-left:15px; padding-right:15px; }.dean-registration-table-meta { padding-left:15px; padding-right:15px; }.dean-registration-table th:first-child,.dean-registration-table td:first-child { padding-left:15px; }.dean-registration-table th:last-child,.dean-registration-table td:last-child { padding-right:15px; }.dean-registration-pagination { padding-left:15px; padding-right:15px; }.dean-registration-sidebar { display:block; }.dean-registration-sidebar__section { padding-top:17px; }.dean-registration-modal { padding:10px; }.dean-registration-modal__dialog { max-height:calc(100vh - 20px); overflow:auto; }.dean-registration-modal__header { padding:18px 18px 14px; }.dean-registration-modal__summary { padding-left:18px; padding-right:18px; }.dean-registration-modal__table th:first-child,.dean-registration-modal__table td:first-child { padding-left:18px; }.dean-registration-modal__footer { padding-left:18px; padding-right:18px; } }
</style>
@endsection
