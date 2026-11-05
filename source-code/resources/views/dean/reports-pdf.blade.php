<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>System Reports</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 4px solid #A855F7;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #A855F7;
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: bold;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        .header .date {
            color: #999;
            font-size: 11px;
            margin-top: 10px;
        }
        
        .stats-section {
            margin: 30px 0;
        }
        .section-title {
            color: #A855F7;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #E5E7EB;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .stats-row {
            display: table-row;
        }
        .stat-card {
            display: table-cell;
            width: 50%;
            padding: 15px;
            vertical-align: top;
        }
        .stat-inner {
            background: #F9FAFB;
            border-left: 5px solid #8B5CF6;
            padding: 20px;
            border-radius: 8px;
            min-height: 80px;
        }
        .stat-label {
            color: #6B7280;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .stat-value {
            color: #1F2937;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-desc {
            color: #9CA3AF;
            font-size: 10px;
        }
        
        /* Color variations */
        .blue { border-left-color: #3B82F6; }
        .purple { border-left-color: #A855F7; }
        .yellow { border-left-color: #FBBF24; }
        .green { border-left-color: #22C55E; }
        
        .summary-box {
            background: #EEF2FF;
            border: 2px solid #A855F7;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        .summary-box h3 {
            color: #A855F7;
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .summary-item {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #DDD6FE;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-label {
            color: #6B7280;
            font-size: 11px;
            display: inline-block;
            width: 60%;
        }
        .summary-value {
            color: #A855F7;
            font-weight: bold;
            font-size: 12px;
            float: right;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #9CA3AF;
            font-size: 10px;
            border-top: 2px solid #E5E7EB;
            padding-top: 20px;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Reports</h1>
        <div class="subtitle">SOCCSync - Complete Overview</div>
        <div class="date">Generated on {{ now()->format('F d, Y h:i A') }}</div>
    </div>

    <div class="stats-section">
        <div class="section-title">System Statistics</div>
        
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-inner blue">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value">{{ $stats['total_students'] }}</div>
                        <div class="stat-desc">Registered members</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner blue">
                        <div class="stat-label">Total Events</div>
                        <div class="stat-value">{{ $stats['total_events'] }}</div>
                        <div class="stat-desc">Organized events</div>
                    </div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-inner purple">
                        <div class="stat-label">Total Payments</div>
                        <div class="stat-value">{{ $stats['total_payments'] }}</div>
                        <div class="stat-desc">Completed transactions</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner purple">
                        <div class="stat-label">Total Announcements</div>
                        <div class="stat-value">{{ $stats['total_announcements'] }}</div>
                        <div class="stat-desc">Posted announcements</div>
                    </div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-inner yellow">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">₱{{ number_format($stats['total_revenue'], 2) }}</div>
                        <div class="stat-desc">From all payments</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner yellow">
                        <div class="stat-label">Total Complaints</div>
                        <div class="stat-value">{{ $stats['total_complaints'] }}</div>
                        <div class="stat-desc">Submitted complaints</div>
                    </div>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-inner green">
                        <div class="stat-label">Pending Payments</div>
                        <div class="stat-value">{{ $stats['pending_payments'] }}</div>
                        <div class="stat-desc">Awaiting verification</div>
                    </div>
                </div>
                <div class="stat-card">
                    <!-- Empty cell for layout -->
                </div>
            </div>
        </div>
    </div>

    <div class="summary-box">
        <h3>Financial Summary</h3>
        <div class="summary-item">
            <span class="summary-label">Total Revenue Collected</span>
            <span class="summary-value">₱{{ number_format($stats['total_revenue'], 2) }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Completed Payment Transactions</span>
            <span class="summary-value">{{ $stats['total_payments'] }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Payments Pending Verification</span>
            <span class="summary-value">{{ $stats['pending_payments'] }}</span>
        </div>
    </div>

    <div class="summary-box">
        <h3>Activities Summary</h3>
        <div class="summary-item">
            <span class="summary-label">Total Registered Students</span>
            <span class="summary-value">{{ $stats['total_students'] }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Events Organized</span>
            <span class="summary-value">{{ $stats['total_events'] }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Announcements Posted</span>
            <span class="summary-value">{{ $stats['total_announcements'] }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Complaints Submitted</span>
            <span class="summary-value">{{ $stats['total_complaints'] }}</span>
        </div>
    </div>

    <div class="footer">
        <p><strong>This is a computer-generated report. No signature required.</strong></p>
        <p>© {{ now()->year }} SOCCSync - Student Organization Management System</p>
        <p>Report generated for Dean's Office</p>
    </div>
</body>
</html>
