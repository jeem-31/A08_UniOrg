<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Received</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #C4973B 0%, #a67d2e 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e0e0e0;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #C4973B;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .status-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .timeline {
            margin: 20px 0;
            padding-left: 20px;
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 15px;
        }
        .timeline-item:before {
            content: "●";
            position: absolute;
            left: 0;
            color: #C4973B;
            font-size: 20px;
        }
        .timeline-item.active:before {
            color: #28a745;
        }
        .timeline-item.pending:before {
            color: #ffc107;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #e0e0e0;
            margin-top: 20px;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 Application Received!</h1>
    </div>
    
    <div class="content">
        <p>Dear <strong>{{ $requesterName }}</strong>,</p>
        
        <p>Thank you for submitting your 
            @if($applicationType === 'renew')
                <span class="highlight">renewal application</span>
            @else
                <span class="highlight">organization application</span>
            @endif
            for <strong>{{ $organizationName }}</strong>.
        </p>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #C4973B;">📋 Application Status</h3>
            <p style="margin: 10px 0;">
                <span class="status-badge">⏳ Under Review</span>
            </p>
            <p style="margin: 5px 0; color: #666;">
                <strong>Organization:</strong> {{ $organizationName }}<br>
                <strong>Submitted by:</strong> {{ $requesterName }}<br>
                <strong>Date:</strong> {{ date('F d, Y') }}
            </p>
        </div>
        
        <h3 style="color: #C4973B;">🔄 What Happens Next?</h3>
        
        <div class="timeline">
            <div class="timeline-item active">
                <strong>Application Submitted</strong><br>
                <small style="color: #666;">Your application has been received successfully</small>
            </div>
            <div class="timeline-item pending">
                <strong>Document Review</strong><br>
                <small style="color: #666;">OSAS will review your submitted documents</small>
            </div>
            <div class="timeline-item">
                <strong>Verification</strong><br>
                <small style="color: #666;">Your organization details will be verified</small>
            </div>
            <div class="timeline-item">
                <strong>Decision</strong><br>
                <small style="color: #666;">You'll receive an email with the final decision</small>
            </div>
        </div>
        
        <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <strong>📧 Email Notifications:</strong>
            <p style="margin: 10px 0;">You will receive an email notification when:</p>
            <ul style="margin: 5px 0;">
                <li>Your application is approved ✅</li>
                <li>Additional information is needed 📝</li>
                <li>Your application status changes 🔄</li>
            </ul>
        </div>
        
        <h3 style="color: #C4973B;">⏱️ Processing Time</h3>
        <p>Applications are typically reviewed within <strong>3-5 business days</strong>. You will be notified via email once a decision has been made.</p>
        
        <h3 style="color: #C4973B;">📞 Need Help?</h3>
        <p>If you have any questions about your application, please contact:</p>
        <ul>
            <li><strong>OSAS Office</strong></li>
            <li>Email: osas@lspu.edu.ph</li>
            <li>Office Hours: Monday - Friday, 8:00 AM - 5:00 PM</li>
        </ul>
        
        <p style="margin-top: 30px;">Thank you for your patience!</p>
        
        <p>Best regards,<br>
        <strong>OSAS Administration Team</strong><br>
        UniOrg Management System</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} UniOrg. All rights reserved.</p>
    </div>
</body>
</html>
