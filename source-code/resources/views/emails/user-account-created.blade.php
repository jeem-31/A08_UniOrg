<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your UniOrg Account</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #26488C 0%, #1a3461 100%); color: white; padding: 20px; text-align: left; border-radius: 8px 8px 0 0; }
        .header-content { display: flex; align-items: center; justify-content: flex-start; gap: 15px; }
        .header-logo { width: 60px; height: 60px; object-fit: contain; }
        .brand-copy { text-align: left; }
        .header-title { font-size: 28px; font-weight: 800; margin: 0; }
        .header-subtitle { color: #C4973B; font-size: 13px; margin: 5px 0 0; font-weight: 600; }
        .content { padding: 20px; }
        .password-box { background-color: #f8f9fa; border: 1px dashed #C4973B; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 2px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding-top: 20px; font-size: 12px; color: #777; border-top: 1px solid #eee; margin-top: 20px; }
        .btn { display: inline-block; background-color: #173B76; color: #ffffff !important; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <img src="{{ $message->embed(public_path('images/LSPU_LOGO.png')) }}" alt="LSPU Logo" class="header-logo">
                <div class="brand-copy">
                    <h2 class="header-title">UniOrg</h2>
                    <p class="header-subtitle">University Organization Management Platform</p>
                </div>
            </div>
        </div>
        <div class="content">
            <p>Hi <strong>{{ $name }}</strong>,</p>
            
            <p>Your organization, <strong>{{ $organizationName }}</strong>, has successfully activated its platform on UniOrg.</p>
            
            <p>You have been registered as the <strong>{{ $role }}</strong> of the organization.</p>
            
            <p>Here is your temporary password to access your account. We highly recommend changing it immediately after logging in.</p>
            
            <div class="password-box">
                {{ $temporaryPassword }}
            </div>
            
            <p>To login, please use your registered Student ID / Employee ID and the temporary password above.</p>
            
            <div style="text-align: center;">
                <a href="{{ url('/') }}" class="btn" style="background-color: #173B76; color: #ffffff;">Login to UniOrg</a>
            </div>
            
            <p>Thank you,<br>The UniOrg Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Laguna State Polytechnic University. All rights reserved.
        </div>
    </div>
</body>
</html>
