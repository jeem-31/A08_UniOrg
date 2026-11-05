<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Application Approved</title>
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
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
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
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            background: #26488C;
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 16px;
        }
        .button:hover {
            background: #1a3461;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #26488C;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
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
        <h1>🎉 Organization Application Approved!</h1>
    </div>
    
    <div class="content">
        <p>Dear <strong>{{ $requesterName }}</strong>,</p>
        
        <p>Congratulations! Your 
            @if($applicationType === 'renew')
                <span class="highlight">renewal application</span>
            @else
                <span class="highlight">organization application</span>
            @endif
            for <strong>{{ $organizationName }}</strong> has been approved by the OSAS Administrator.
        </p>
        
        <div class="success-box">
            <h3 style="margin-top: 0; color: #28a745;">✅ Application Status: APPROVED</h3>
            <p style="margin: 5px 0; color: #155724;">
                <strong>Organization:</strong> {{ $organizationName }}<br>
                <strong>Approved on:</strong> {{ date('F d, Y') }}
            </p>
        </div>
        
        <h3 style="color: #26488C;">🚀 Next Step: Create Your System</h3>
        
        <p>Your organization is now officially recognized! To start managing your organization through UniOrg, you need to <strong>create your system account</strong>.</p>
        
        <div class="info-box">
            <h4 style="margin-top: 0; color: #26488C;">📋 What is "Create System"?</h4>
            <p style="margin: 10px 0;">Creating a system will:</p>
            <ul style="margin: 5px 0;">
                <li>Generate your organization's admin account</li>
                <li>Provide you with login credentials</li>
                <li>Activate your organization management dashboard</li>
                <li>Allow you to manage members, events, and documents</li>
            </ul>
        </div>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ route('system-request.combined') }}" class="button">
                🔐 Create System Now
            </a>
        </p>
        
        <h3 style="color: #26488C;">📝 What You'll Need:</h3>
        <ul>
            <li><strong>Organization Name:</strong> {{ $organizationName }}</li>
            <li><strong>Organization Type:</strong> (as registered)</li>
            <li><strong>President Information:</strong> Name, Student ID, Email</li>
        </ul>
        
        <div style="background: #fff3cd; border-left: 4px solid #C4973B; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <strong>⚠️ Important:</strong>
            <p style="margin: 10px 0;">Only the <strong>Organization President</strong> can create the system. Make sure you have the correct information before proceeding.</p>
        </div>
        
        <h3 style="color: #26488C;">📞 Need Help?</h3>
        <p>If you have any questions about creating your system, please contact:</p>
        <ul>
            <li><strong>OSAS Office</strong></li>
            <li>Email: osas@lspu.edu.ph</li>
            <li>Office Hours: Monday - Friday, 8:00 AM - 5:00 PM</li>
        </ul>
        
        <p style="margin-top: 30px;">Congratulations once again on your approval!</p>
        
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
