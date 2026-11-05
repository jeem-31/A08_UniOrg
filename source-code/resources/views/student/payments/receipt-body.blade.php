<style>
    .r-wrap { max-width: 700px; min-width: 640px; margin: 0 auto; font-family: Arial, sans-serif; color: #111827; }
    .r-top { border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 18px; }
    .r-top table { width: 100%; }
    .r-top td { vertical-align: middle; }
    .r-top h1 { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 2px 0; }
    .r-top p { font-size: 12px; color: #6b7280; margin: 0; }
    .r-logo { width: 44px; height: 44px; object-fit: contain; border-radius: 6px; }
    .r-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; margin-bottom: 18px; }
    .r-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .r-grid td { width: 50%; vertical-align: top; padding: 0; }
    .r-grid td + td { padding-left: 14px; }
    .r-panel { border: 1px solid #e5e7eb; border-radius: 5px; padding: 10px 12px; }
    .r-panel h3 { font-size: 10px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 6px 0; padding: 0; }
    .r-tbl { width: 100%; border-collapse: collapse; }
    .r-tbl td { padding: 2px 0; font-size: 12px; }
    .r-tbl .lbl { color: #6b7280; width: 40%; white-space: nowrap; }
    .r-tbl .val { color: #111827; font-weight: 500; text-align: right; width: 60%; white-space: nowrap; }
    .r-amt { border: 1px solid #e5e7eb; border-radius: 5px; padding: 14px; text-align: center; margin-bottom: 18px; background: #f9fafb; }
    .r-amt h2 { font-size: 12px; font-weight: 600; color: #6b7280; margin: 0 0 4px 0; }
    .r-amt .num { font-size: 26px; font-weight: 700; color: #111827; }
    .r-foot { font-size: 10px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
</style>
<div class="r-wrap">
    <div class="r-top">
        <table><tr>
            <td>
                <h1>Student Organization of the College of Computer Studies</h1>
                <p>Official Payment Receipt &middot; {{ $payment->transaction_id }}</p>
            </td>
            @php $rLogo = organization_logo(); @endphp
            @if($rLogo)
            <td style="width:54px; text-align:right;">
                <img class="r-logo" src="{{ $rLogo }}" alt="Logo">
            </td>
            @endif
        </tr></table>
    </div>
    @if($payment->status === 'completed')
    <div class="r-badge">Payment Completed</div>
    @else
    <div class="r-badge" style="background: #fef2f2; color: #dc2626; border-color: #fecaca;">Payment {{ ucfirst($payment->status) }}</div>
    @endif
    <table class="r-grid"><tr>
        <td>
            <div class="r-panel">
                <h3>Transaction</h3>
                <table class="r-tbl">
                    <tr><td class="lbl">Transaction ID</td><td class="val">{{ $payment->transaction_id }}</td></tr>
                    <tr><td class="lbl">Date Paid</td><td class="val">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y h:i A') : $payment->created_at->format('M d, Y h:i A') }}</td></tr>
                    <tr><td class="lbl">Method</td><td class="val">{{ strtoupper($payment->payment_method) }}</td></tr>
                    @if($payment->reference_number)
                    <tr><td class="lbl">Reference</td><td class="val">{{ $payment->reference_number }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        <td>
            <div class="r-panel">
                <h3>Student</h3>
                <table class="r-tbl">
                    <tr><td class="lbl">Name</td><td class="val">{{ $payment->user->first_name }} {{ $payment->user->last_name }}</td></tr>
                    <tr><td class="lbl">Student ID</td><td class="val">{{ $payment->user->student_id }}</td></tr>
                    <tr><td class="lbl">Email</td><td class="val">{{ $payment->user->email }}</td></tr>
                    <tr><td class="lbl">Section</td><td class="val">{{ course_acronym($payment->user->course) ?: 'N/A' }} {{ preg_replace('/[^0-9]/', '', $payment->user->year_level ?? '') }}{{ $payment->user->section ?? '' }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>
    <div class="r-amt">
        <h2>Payment Details &mdash; {{ ucfirst(str_replace('_', ' ', $payment->category)) }}</h2>
        <div class="num">&#x20B1;{{ number_format($payment->amount, 2) }}</div>
    </div>
    @if($payment->announcement)
    <div class="r-panel" style="margin-bottom:16px;">
        <h3>Announcement</h3>
        <table class="r-tbl">
            <tr><td class="lbl">Title</td><td class="val">{{ $payment->announcement->title }}</td></tr>
            <tr><td class="lbl">Due Date</td><td class="val">{{ $payment->announcement->due_date->format('M d, Y') }}</td></tr>
        </table>
    </div>
    @endif
    @if($payment->notes)
    <div class="r-panel" style="margin-bottom:16px;">
        <h3>Notes</h3>
        <p style="font-size:12px;color:#374151;margin:4px 0 0 0;">{{ $payment->notes }}</p>
    </div>
    @endif
    <div class="r-foot">This is an official receipt. Generated {{ now()->format('M d, Y h:i A') }}</div>
</div>
