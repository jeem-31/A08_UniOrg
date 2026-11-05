<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Registration Reports</title>
    <style>
        @page { margin: 30px 34px 44px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #282934; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; }
        .topline { height: 4px; margin-bottom: 16px; background: #8238b3; }
        .masthead { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .masthead td { vertical-align: top; }
        .brand { color: #8238b3; font-size: 8px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        h1 { margin: 5px 0 4px; color: #20212b; font-size: 21px; letter-spacing: -.4px; }
        .subtitle { margin: 0; color: #727582; font-size: 9px; }
        .generated { color: #727582; font-size: 8px; line-height: 1.5; text-align: right; }
        .generated strong { display: block; color: #464854; font-size: 9px; }
        .scope { width: 100%; margin-bottom: 17px; border: 1px solid #e3e4e9; border-collapse: collapse; background: #fafafd; }
        .scope td { width: 33.33%; padding: 9px 11px; border-right: 1px solid #e3e4e9; vertical-align: top; }
        .scope td:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 4px; color: #888a94; font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .value { color: #343540; font-size: 13px; font-weight: bold; }
        .value.purple { color: #8238b3; }
        .scope-note { display: block; margin-top: 3px; color: #999ba4; font-size: 7px; }
        .report-heading { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .report-heading td { vertical-align: bottom; }
        .report-heading h2 { margin: 0; color: #353641; font-size: 12px; }
        .report-heading p { margin: 3px 0 0; color: #8b8d96; font-size: 8px; }
        .report-heading .count { color: #727582; font-size: 8px; text-align: right; }
        .event-block { margin-bottom: 13px; page-break-inside: auto; }
        .event-head { width: 100%; border: 1px solid #dedfe6; border-bottom: 0; border-collapse: collapse; background: #f7f2fb; page-break-after: avoid; }
        .event-head > tbody > tr > td { padding: 8px 10px; vertical-align: middle; }
        .event-identity { width: 78%; }
        .identity-table { border-collapse: collapse; }
        .identity-table td { padding: 0; vertical-align: middle; }
        .event-thumb { display: block; width: 28px; height: 28px; border-radius: 4px; object-fit: cover; }
        .event-thumb-fallback { width: 28px; height: 28px; background: #eadcf4; color: #8238b3; font-size: 11px; text-align: center; line-height: 28px; }
        .identity-copy { padding-left: 8px !important; }
        .event-title { display: block; color: #30313c; font-size: 10px; font-weight: bold; }
        .event-meta { display: block; margin-top: 3px; color: #777985; font-size: 7px; }
        .event-count { width: 22%; border-left: 1px solid #e3d9eb; text-align: right; }
        .event-count strong { display: block; color: #8238b3; font-size: 13px; }
        .event-count span { display: block; margin-top: 2px; color: #888a94; font-size: 7px; }
        table.data { width: 100%; border: 1px solid #dedfe6; border-collapse: collapse; table-layout: fixed; }
        .data thead { display: table-header-group; }
        .data th { padding: 7px 7px; border-bottom: 1px solid #d8d9e0; background: #fbfbfc; color: #777985; font-size: 7px; font-weight: bold; letter-spacing: .6px; text-align: left; text-transform: uppercase; }
        .data td { padding: 7px; border-bottom: 1px solid #ececf0; color: #555762; font-size: 8px; vertical-align: middle; word-wrap: break-word; }
        .data tr:nth-child(even) td { background: #fcfcfd; }
        .data tr:last-child td { border-bottom: 0; }
        .data th:nth-child(1) { width: 32%; }.data th:nth-child(2) { width: 20%; }.data th:nth-child(3) { width: 25%; }.data th:nth-child(4) { width: 23%; }
        .person { color: #30313c; font-size: 8px; font-weight: bold; }.sub { display: block; margin-top: 2px; color: #999ba4; font-size: 7px; }.date { color: #4d4f59; font-size: 8px; }.empty { padding: 15px; color: #888a93; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -27px; left: 0; padding-top: 8px; border-top: 1px solid #e3e4e9; color: #9698a1; font-size: 7px; }
        .footer .page { float: right; }
    </style>
</head>
<body>
    <div class="topline"></div>

    <table class="masthead">
        <tr>
            <td><div class="brand">SOCCSync - Dean reports</div><h1>Registration report</h1><p class="subtitle">Department participation register for approved events</p></td>
            <td class="generated"><strong>Report generated</strong>{{ now()->format('M d, Y - h:i A') }}<br>Scope: Approved events</td>
        </tr>
    </table>

    <table class="scope">
        <tr>
            <td><span class="label">Approved events</span><span class="value purple">{{ number_format($eventRegistrations->count()) }}</span><span class="scope-note">Activities in this report</span></td>
            <td><span class="label">Event participants</span><span class="value">{{ number_format($totalParticipants ?? $eventRegistrations->sum('registrations_count')) }}</span><span class="scope-note">Registrations across events</span></td>
            <td><span class="label">Department students</span><span class="value">{{ number_format($totalRegistrations ?? 0) }}</span><span class="scope-note">Students in the department</span></td>
        </tr>
    </table>

    <table class="report-heading"><tr><td><h2>Event participation</h2><p>Each event includes its participant roster and registration date.</p></td><td class="count">{{ number_format($eventRegistrations->count()) }} event{{ $eventRegistrations->count() === 1 ? '' : 's' }}</td></tr></table>

    @forelse($eventRegistrations as $event)
        <div class="event-block">
            <table class="event-head">
                <tr>
                    <td class="event-identity">
                        <table class="identity-table">
                            <tr>
                                <td>
                                    @if(is_array($event->images) && !empty($event->images[0]))
                                        <img class="event-thumb" src="{{ storage_url($event->images[0]) }}" alt="">
                                    @else
                                        <div class="event-thumb-fallback">CAL</div>
                                    @endif
                                </td>
                                <td class="identity-copy"><span class="event-title">{{ $event->title }}</span><span class="event-meta">{{ $event->start_date ? $event->start_date->format('M d, Y - h:i A') : 'Date to be confirmed' }} - {{ $event->venue ?: 'Venue to be confirmed' }}</span></td>
                            </tr>
                        </table>
                    </td>
                    <td class="event-count"><strong>{{ number_format($event->registrations_count) }}</strong><span>participants</span></td>
                </tr>
            </table>

            <table class="data">
                <thead><tr><th>Student</th><th>Student ID</th><th>Course / section</th><th>Registered</th></tr></thead>
                <tbody>
                    @forelse($event->registrations as $registration)
                        @php
                            $user = $registration->user;
                            $yearNumber = $user && $user->year_level ? substr((string) $user->year_level, 0, 1) : '';
                            $studentName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown student';
                            $courseSection = trim(($user->course ?? 'Course not set') . ' / ' . $yearNumber . ($user->section ?? ''));
                        @endphp
                        <tr>
                            <td><span class="person">{{ $studentName ?: 'Unknown student' }}</span></td>
                            <td><span class="sub">{{ $user->student_id ?? 'Not available' }}</span></td>
                            <td>{{ $courseSection }}</td>
                            <td><span class="date">{{ $registration->registered_at ? $registration->registered_at->format('M d, Y - h:i A') : 'Not available' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No registrations recorded for this event.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty">No approved events are available in the current report scope.</div>
    @endforelse

    <div class="footer">SOCCSync - Computer-generated report - No signature required <span class="page">Page {PAGE_NUM} of {PAGE_COUNT}</span></div>
</body>
</html>
