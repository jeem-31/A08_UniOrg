<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt - {{ $payment->transaction_id }}</title>
    <script>window.onload = function() { window.print(); };</script>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #111827; margin: 20px; padding: 0; }
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
        .r-tbl .val { color: #111827; font-weight: 500; text-align: right; width: 60%; }
        .r-amt { border: 1px solid #e5e7eb; border-radius: 5px; padding: 14px; text-align: center; margin-bottom: 18px; background: #f9fafb; }
        .r-amt h2 { font-size: 12px; font-weight: 600; color: #6b7280; margin: 0 0 4px 0; }
        .r-amt .num { font-size: 26px; font-weight: 700; color: #111827; }
        .r-foot { font-size: 10px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
    </style>
</head>
<body>
    @include('student.payments.receipt-body', ['payment' => $payment])
</body>
</html>
