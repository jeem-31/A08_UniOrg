@php($isRemoval = ($notificationType ?? 'assigned') === 'removed')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isRemoval ? 'Your UniOrg position was removed' : 'Your UniOrg position' }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { width: 100% !important; }
            .mobile-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .mobile-stack { display: block !important; width: 100% !important; text-align: left !important; }
            .mobile-hide { display: none !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f4f5f8; color:#202331; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#f4f5f8;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" class="email-shell" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:640px;">
                    <tr>
                        <td class="mobile-pad" style="padding:0 8px 18px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td valign="middle" style="width:58px;">
                                        <img src="{{ $logoUrl }}" width="48" height="48" alt="UniOrg logo" style="display:block; width:48px; height:48px; object-fit:contain; border:0;">
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div style="font-size:20px; line-height:24px; font-weight:700; color:#202331;">UniOrg</div>
                                        <div style="font-size:12px; line-height:18px; color:#737887;">Student organization platform</div>
                                    </td>
                                    <td class="mobile-hide" align="right" valign="middle" style="font-size:10px; line-height:14px; letter-spacing:1.2px; font-weight:700; color:#8a8f9d;">ACCOUNT UPDATE</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; border:1px solid #e3e5eb; border-radius:14px; overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="height:5px; line-height:5px; font-size:0; background:{{ $primaryColor }};">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="mobile-pad" style="padding:30px 38px 10px;">
                                        <div style="font-size:11px; line-height:16px; letter-spacing:1px; font-weight:700; color:{{ $primaryColor }};">ORGANIZATION ACCESS</div>
                                        <h1 style="margin:8px 0 12px; font-size:30px; line-height:37px; letter-spacing:-.4px; font-weight:700; color:#202331;">{{ $isRemoval ? 'Your role has changed' : 'You have a new role' }}</h1>
                                        <p style="margin:0; font-size:16px; line-height:25px; color:#596070;">Hi <strong style="color:#202331;">{{ $firstName }}</strong>, {{ $isRemoval ? 'you are no longer assigned to this position in' : 'your access has been updated in' }} {{ $organization->name }}.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="mobile-pad" style="padding:18px 38px 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#faf9fd; border:1px solid #e9e5f1; border-left:4px solid {{ $primaryColor }}; border-radius:9px;">
                                            <tr>
                                                <td style="padding:17px 18px;">
                                                    <div style="font-size:11px; line-height:16px; letter-spacing:.7px; font-weight:700; color:#878b99;">{{ $isRemoval ? 'POSITION REMOVED' : 'ASSIGNED POSITION' }}</div>
                                                    <div style="padding-top:5px; font-size:25px; line-height:32px; font-weight:700; color:#202331;">{{ $position }}</div>
                                                    <div style="padding-top:5px; font-size:13px; line-height:20px; color:#697080;">{{ $organization->name }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="mobile-pad" style="padding:18px 38px 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td class="mobile-stack" valign="top" style="width:50%; padding:0 18px 12px 0;">
                                                    <div style="font-size:11px; line-height:16px; letter-spacing:.6px; font-weight:700; color:#878b99;">WHAT CHANGED</div>
                                                    <div style="padding-top:5px; font-size:14px; line-height:21px; color:#454b5a;">{{ $isRemoval ? 'Your admin access from this position has been removed.' : 'Your account can now open the admin view for the modules assigned to this position.' }}</div>
                                                </td>
                                                <td class="mobile-stack" valign="top" style="width:50%; padding:0 0 12px 18px;">
                                                    <div style="font-size:11px; line-height:16px; letter-spacing:.6px; font-weight:700; color:#878b99;">{{ $isRemoval ? 'UPDATED BY' : 'ASSIGNED BY' }}</div>
                                                    <div style="padding-top:5px; font-size:14px; line-height:21px; color:#454b5a;">{{ $assignedBy }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="mobile-pad" style="padding:17px 38px 32px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="border-radius:7px; background:{{ $primaryColor }};">
                                                    <a href="{{ $dashboardUrl }}" style="display:inline-block; padding:13px 20px; border:1px solid {{ $primaryColor }}; border-radius:7px; color:#ffffff; font-size:14px; line-height:18px; font-weight:700; text-decoration:none;">Open UniOrg&nbsp; &rarr;</a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:18px 0 0; font-size:13px; line-height:21px; color:#697080;">{{ $isRemoval ? 'Sign in to see your updated organization access. Any other organization positions you hold are not affected.' : 'After signing in, use the profile dropdown to switch between Student View and Admin View.' }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="mobile-pad" style="padding:18px 8px 0; text-align:left;">
                            <p style="margin:0; font-size:11px; line-height:18px; color:#8a8f9d;">This is an automated account notification from UniOrg.</p>
                            <p style="margin:3px 0 0; font-size:11px; line-height:18px; color:#8a8f9d;">&copy; {{ date('Y') }} UniOrg</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
