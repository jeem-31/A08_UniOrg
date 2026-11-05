<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Student Payments Report</title>
    <style>
        @page { margin: 34px 34px 46px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #282934; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; }
        .topline { height: 4px; margin-bottom: 18px; background: #8238b3; }
        .masthead { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .masthead td { vertical-align: top; }
        .brand { color: #8238b3; font-size: 9px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { margin: 5px 0 4px; color: #20212b; font-size: 21px; letter-spacing: -.4px; }
        .subtitle { margin: 0; color: #727582; font-size: 9px; }
        .generated { color: #727582; font-size: 8px; line-height: 1.5; text-align: right; }
        .generated strong { display: block; color: #464854; font-size: 9px; }
        .scope { width: 100%; margin-bottom: 16px; border: 1px solid #e3e4e9; border-collapse: collapse; background: #fafafd; }
        .scope td { width: 25%; padding: 9px 11px; border-right: 1px solid #e3e4e9; vertical-align: top; }
        .scope td:last-child { border-right: 0; }
        .label { display: block; margin-bottom: 4px; color: #888a94; font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .value { color: #343540; font-size: 10px; font-weight: bold; }
        .value.purple { color: #8238b3; }
        .report-heading { width: 100%; margin-bottom: 7px; border-collapse: collapse; }
        .report-heading td { vertical-align: bottom; }
        .report-heading h2 { margin: 0; color: #353641; font-size: 12px; }
        .report-heading p { margin: 3px 0 0; color: #8b8d96; font-size: 8px; }
        .report-heading .count { color: #727582; font-size: 8px; text-align: right; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .data th { padding: 8px 7px; border-top: 1px solid #8238b3; border-bottom: 1px solid #d9dbe2; background: #f3eafa; color: #63318a; font-size: 7px; font-weight: bold; letter-spacing: .6px; text-align: left; text-transform: uppercase; }
        .data td { padding: 8px 7px; border-bottom: 1px solid #ececf0; color: #555762; font-size: 8px; vertical-align: middle; word-wrap: break-word; }
        .data tr:nth-child(even) td { background: #fcfcfd; }
        .data th:nth-child(1) { width: 23%; }.data th:nth-child(2) { width: 18%; }.data th:nth-child(3) { width: 16%; }.data th:nth-child(4) { width: 12%; }.data th:nth-child(5) { width: 13%; }.data th:nth-child(6) { width: 18%; }
        .person { color: #30313c; font-size: 8px; font-weight: bold; }.sub { display: block; margin-top: 2px; color: #999ba4; font-size: 7px; font-weight: normal; }
        .transaction { color: #666874; font-family: monospace; font-size: 7px; }
        .tag, .method, .status { display: inline-block; padding: 3px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }
        .tag { background: #f3eafa; color: #8238b3; }.method { background: #f1f2f5; color: #62646e; }.amount { color: #28855a; font-size: 8px; font-weight: bold; }.date { color: #4d4f59; font-size: 8px; }.time { display: block; margin-top: 2px; color: #999ba4; font-size: 7px; }
        .empty { padding: 28px; color: #888a93; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -28px; left: 0; padding-top: 8px; border-top: 1px solid #e3e4e9; color: #9698a1; font-size: 7px; }
        .footer .page { float: right; }
    </style>
</head>
<body>
    <div class="topline"></div>
    <table class="masthead">
        <tr>
            <td><div class="brand">SOCCSync · Dean finance</div><h1>Student payments</h1><p class="subtitle">Completed payment ledger for the department</p></td>
            <td class="generated"><strong>Report generated</strong>{{ now()->format('M d, Y · h:i A') }}<br>Payment status: Completed</td>
        </tr>
    </table>

    <table class="scope">
        <tr>
            <td><span class="label">Records in report</span><span class="value">{{ number_format(count($payments)) }}</span></td>
            <td><span class="label">Category</span><span class="value purple">{{ isset($category) && $category !== 'all' ? ucfirst(str_replace('_', ' ', $category)) : 'All categories' }}</span></td>
            <td><span class="label">From</span><span class="value">{{ !empty($startDate) ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : 'Beginning' }}</span></td>
            <td><span class="label">To</span><span class="value">{{ !empty($endDate) ? \Carbon\Carbon::parse($endDate)->format('M d, Y') : 'Present' }}</span></td>
        </tr>
    </table>

    <table class="report-heading"><tr><td><h2>Payment records</h2><p>Student, transaction, category, method, and payment date</p></td><td class="count">{{ number_format(count($payments)) }} record{{ count($payments) === 1 ? '' : 's' }}</td></tr></table>
    @if(count($payments) > 0)
        <table class="data">
            <thead><tr><th>Student</th><th>Transaction ID</th><th>Category</th><th>Amount</th><th>Method</th><th>Date paid</th></tr></thead>
            <tbody>
                @foreach($payments as $payment)
                    <tr>
                        <td><span class="person">{{ trim(($payment->user->first_name ?? '') . ' ' . ($payment->user->last_name ?? '')) ?: 'Unknown student' }}</span><span class="sub">{{ $payment->user->student_id ?? 'Student ID unavailable' }}</span></td>
                        <td><span class="transaction">{{ $payment->transaction_id }}</span></td>
                        <td><span class="tag">{{ ucfirst(str_replace('_', ' ', $payment->category ?? 'other')) }}</span></td>
                        <td><span class="amount">&#8369;{{ number_format($payment->amount, 2) }}</span></td>
                        <td><span class="method">{{ ucfirst($payment->payment_method ?? 'Manual') }}</span></td>
                        <td><span class="date">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : 'N/A' }}</span><span class="time">{{ $payment->paid_at ? $payment->paid_at->format('h:i A') : '' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No completed payments match the selected report scope.</div>
    @endif

    <div class="footer">SOCCSync · Computer-generated report · No signature required <span class="page">Page {PAGE_NUM} of {PAGE_COUNT}</span></div>
</body>
</html>
