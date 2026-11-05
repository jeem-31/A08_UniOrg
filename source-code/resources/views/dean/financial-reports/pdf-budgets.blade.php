<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Event Budgets Report</title>
    <style>
        @page { margin: 34px 34px 46px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #282934; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; }
        .topline { height: 4px; margin-bottom: 18px; background: #d7ac28; }
        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .masthead td { vertical-align: top; }
        .brand { color: #9a7410; font-size: 9px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { margin: 5px 0 4px; color: #20212b; font-size: 21px; letter-spacing: -.4px; }
        .subtitle { margin: 0; color: #727582; font-size: 9px; }
        .generated { color: #727582; font-size: 8px; line-height: 1.5; text-align: right; }.generated strong { display: block; color: #464854; font-size: 9px; }
        .scope { width: 100%; margin-bottom: 16px; border: 1px solid #e3e4e9; border-collapse: collapse; background: #fffdf6; }.scope td { width: 25%; padding: 9px 11px; border-right: 1px solid #e3e4e9; vertical-align: top; }.scope td:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 4px; color: #888a94; font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }.value { color: #343540; font-size: 10px; font-weight: bold; }.value.gold { color: #9a7410; }
        .report-heading { width: 100%; margin-bottom: 7px; border-collapse: collapse; }.report-heading td { vertical-align: bottom; }.report-heading h2 { margin: 0; color: #353641; font-size: 12px; }.report-heading p { margin: 3px 0 0; color: #8b8d96; font-size: 8px; }.report-heading .count { color: #727582; font-size: 8px; text-align: right; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }.data th { padding: 8px 7px; border-top: 1px solid #d7ac28; border-bottom: 1px solid #d9dbe2; background: #fff7df; color: #80620d; font-size: 7px; font-weight: bold; letter-spacing: .6px; text-align: left; text-transform: uppercase; }.data td { padding: 8px 7px; border-bottom: 1px solid #ececf0; color: #555762; font-size: 8px; vertical-align: middle; word-wrap: break-word; }.data tr:nth-child(even) td { background: #fffefa; }.data th:nth-child(1) { width: 24%; }.data th:nth-child(2) { width: 17%; }.data th:nth-child(3) { width: 14%; }.data th:nth-child(4) { width: 17%; }.data th:nth-child(5) { width: 12%; }.data th:nth-child(6) { width: 16%; }
        .event-title { color: #30313c; font-size: 8px; font-weight: bold; }.sub { display: block; margin-top: 2px; color: #999ba4; font-size: 7px; }.owner,.venue,.date { color: #666874; font-size: 8px; }.budget { color: #87670c; font-size: 8px; font-weight: bold; }.status { display: inline-block; padding: 3px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }.approved { background: #eaf8f1; color: #218252; }.pending { background: #fff5df; color: #a66b00; }.rejected { background: #fff0f0; color: #c93636; }.empty { padding: 28px; color: #888a93; text-align: center; }.footer { position: fixed; right: 0; bottom: -28px; left: 0; padding-top: 8px; border-top: 1px solid #e3e4e9; color: #9698a1; font-size: 7px; }.footer .page { float: right; }
    </style>
</head>
<body>
    <div class="topline"></div>
    <table class="masthead"><tr><td><div class="brand">SOCCSync · Dean finance</div><h1>Event budgets</h1><p class="subtitle">Planned budgets submitted for department events</p></td><td class="generated"><strong>Report generated</strong>{{ now()->format('M d, Y · h:i A') }}<br>Budget register</td></tr></table>

    <table class="scope"><tr>
        <td><span class="label">Budget records</span><span class="value">{{ number_format($eventBudgets->count()) }}</span></td>
        <td><span class="label">Planned budget total</span><span class="value gold">&#8369;{{ number_format($eventBudgets->sum('planned_budget'), 2) }}</span></td>
        <td><span class="label">Approved</span><span class="value">{{ number_format($eventBudgets->where('approval_status', 'approved')->count()) }}</span></td>
        <td><span class="label">Pending review</span><span class="value">{{ number_format($eventBudgets->where('approval_status', 'pending')->count()) }}</span></td>
    </tr></table>

    <table class="report-heading"><tr><td><h2>Event budget register</h2><p>Event, owner, schedule, venue, planned amount, and review status</p></td><td class="count">{{ number_format($eventBudgets->count()) }} record{{ $eventBudgets->count() === 1 ? '' : 's' }}</td></tr></table>
    @if($eventBudgets->count() > 0)
        <table class="data">
            <thead><tr><th>Event</th><th>Submitted by</th><th>Planned budget</th><th>Schedule</th><th>Venue</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($eventBudgets as $event)
                    @php $status = $event->approval_status ?? 'pending'; @endphp
                    <tr>
                        <td><span class="event-title">{{ $event->title }}</span><span class="sub">Event #{{ $event->id }}</span></td>
                        <td><span class="owner">{{ $event->user ? $event->user->short_name : 'Organization admin' }}</span></td>
                        <td><span class="budget">&#8369;{{ number_format($event->planned_budget ?? 0, 2) }}</span></td>
                        <td><span class="date">{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('M d, Y h:i A') : 'To be confirmed' }}</span></td>
                        <td><span class="venue">{{ $event->venue ?: 'To be confirmed' }}</span></td>
                        <td><span class="status {{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No event budgets are available in the current report scope.</div>
    @endif

    <div class="footer">SOCCSync · Computer-generated report · No signature required <span class="page">Page {PAGE_NUM} of {PAGE_COUNT}</span></div>
</body>
</html>
