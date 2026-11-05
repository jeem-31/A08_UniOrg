<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Request Update</title>
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
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
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
        .reason-box {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #26488C;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            background: #26488C;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #e0e0e0;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>System Request Update</h1>
    </div>
    
    <div class="content">
        <p>Dear <strong>{{ $requesterName }}</strong>,</p>
        
        <p>Thank you for your interest in using the UniOrg Management System for <strong>{{ $organizationName }}</strong>.</p>
        
        <p>After careful review, we regret to inform you that your system request has not been approved at this time.</p>
        
        <div class="reason-box">
            <h3 style="margin-top: 0; color: #dc2626;">📋 Reason for Non-Approval:</h3>
            <p style="margin: 0;">{{ $reason }}</p>
        </div>
        
        <div class="info-box">
            <h3 style="margin-top: 0; color: #26488C;">💡 What You Can Do:</h3>
            <ul style="margin: 10px 0;">
                <li>Review the reason provided above</li>
                <li>Address any concerns or missing requirements</li>
                <li>Submit a new request once ready</li>
                <li>Contact the OSAS office for clarification</li>
            </ul>
        </div>
        
        <p>We encourage you to resubmit your request after addressing the concerns mentioned above. Our team is here to help you through the process.</p>
        
        <p style="text-align: center;">
            <a href="{{ route('system-request.combined') }}" class="button">Submit New Request</a>
        </p>
        
        <p>If you have any questions or need further assistance, please don't hesitate to contact the OSAS office.</p>
        
        <p>Best regards,<br>
        <strong>OSAS Administration Team</strong><br>
        UniOrg Management System</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>For inquiries, please contact the OSAS office directly.</p>
        <p>&copy; {{ date('Y') }} UniOrg. All rights reserved.</p>
    </div>
</body>
</html>
