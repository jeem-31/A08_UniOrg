<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Event Proposal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #1a3a5c 0%, #26488C 100%);
            padding: 30px;
        }
        .header-table {
            width: 100%;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header img {
            height: 100px;
            display: block;
        }
        .header-text {
            padding-left: 25px;
        }
        .header-text h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .header-text p {
            color: #cbd5e1;
            margin: 5px 0 0;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #1a3a5c;
            font-size: 18px;
            margin-top: 0;
        }
        .badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .detail-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .detail-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-card td {
            padding: 8px 0;
            font-size: 14px;
        }
        .detail-card td:first-child {
            color: #64748b;
            width: 120px;
            font-weight: 600;
        }
        .detail-card td:last-child {
            color: #1e293b;
            font-weight: 500;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background: #26488C;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 15px;
            margin: 20px 0;
        }
        .btn:hover {
            background: #1a3a5c;
        }
        .footer {
            text-align: center;
            padding: 20px 30px;
            color: #94a3b8;
            font-size: 13px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td width="1">
                        <img src="{{ $logoUrl }}" alt="LSPU Logo">
                    </td>
                    <td class="header-text">
                        <h1>New Event Proposal</h1>
                        <p>Submitted for OSAS Review and Approval</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">
            <h2>Hello OSAS Administrator,</h2>
            <p>A new event proposal has been submitted for your review. Please find the details below:</p>

            <span class="badge">⏳ Pending Review</span>

            <div class="detail-card">
                <table>
                    <tr>
                        <td>Event Title</td>
                        <td>{{ $event->title }}</td>
                    </tr>
                    <tr>
                        <td>Organization</td>
                        <td>{{ $event->organization->org_acronym ?? $event->organization->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Start Date</td>
                        <td>{{ $event->start_date ? $event->start_date->format('F d, Y - h:i A') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>End Date</td>
                        <td>{{ $event->end_date ? $event->end_date->format('F d, Y - h:i A') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Venue</td>
                        <td>{{ $event->venue }}</td>
                    </tr>
                    <tr>
                        <td>Category</td>
                        <td>{{ $event->category_label }}</td>
                    </tr>
                    <tr>
                        <td>Budget</td>
                        <td>₱{{ number_format($event->planned_budget, 2) }}</td>
                    </tr>
                </table>
            </div>

            <hr class="divider">

            <p style="text-align: center;">
                <a href="{{ route('osas.proposals.index') }}" class="btn">Review Proposal</a>
            </p>

            <p style="font-size: 14px; color: #64748b;">Please log in to the UniOrg system to approve or reject this proposal. You can also view the request letter and other attached documents.</p>
        </div>

        <div class="footer">
            <p>This is an automated notification from the UniOrg Event Management System.</p>
            <p>LSPU - OSAS Office</p>
            <p>&copy; {{ date('Y') }} UniOrg. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
