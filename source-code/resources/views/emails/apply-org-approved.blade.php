<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approved</title>
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
            background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);
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
            border-radius: 0 0 10px 10px;
        }
        .button {
            display: inline-block;
            background: #26488C;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
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
        <h1>🎉 Application Approved!</h1>
    </div>
    
    <div class="content">
        <p>Dear <strong>{{ $requesterName }}</strong>,</p>
        
        <p>Congratulations! Your application for <strong>{{ $organizationName }}</strong> has been approved by the OSAS Administrator.</p>
        
        <p>You can now proceed to set up your organization's platform on UniOrg. Please click the button below to go to the registration form, then select the <strong>Create Platform</strong> option:</p>
        
        <p style="text-align: center;">
            <a href="{{ url('/apply-org/form') }}" class="button" style="color: white !important;">Create Your Platform</a>
        </p>
        
        <p>If you have any questions or need assistance, please contact the OSAS office.</p>
        
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
