@php($primaryColor = $primaryColor ?? '#8238B3')
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title>{{ $title }}</title><style>@media(max-width:600px){.shell{width:100%!important}.body{padding:28px 20px!important}.cell{display:block!important;width:100%!important;padding:6px 0!important}}</style></head>
<body style="margin:0;background:#f4f5f7;color:#18212f;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" class="shell" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:560px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<tr><td style="height:5px;background:{{ $primaryColor }}"></td></tr>
<tr><td class="body" style="padding:34px 42px 30px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px"><tr>
<td width="76" style="width:76px;vertical-align:middle;text-align:left">@if($organizationLogo)<img src="{{ $organizationLogo }}" alt="{{ $organizationName }} logo" style="display:block;max-width:64px;max-height:64px;width:auto;height:auto">@endif</td>
<td style="vertical-align:middle;text-align:center"><p style="margin:0;color:{{ $primaryColor }};font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;line-height:1.45">{{ $organizationName }}</p></td>
<td width="76" style="width:76px;vertical-align:middle;text-align:right"><img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png" alt="UniOrg logo" width="58" height="58" style="display:inline-block;width:58px;height:58px;object-fit:contain"></td>
</tr></table>
<p style="margin:0 0 10px;color:#6b7280;font-size:14px">Hello {{ $recipientName }},</p><h1 style="margin:0 0 12px;color:#111827;font-size:27px;line-height:1.2">{{ $title }}</h1><p style="margin:0 0 26px;color:#4b5563;font-size:15px;line-height:1.65">{{ $intro }}</p>
@if($highlight)<div style="margin:0 0 24px;padding:16px 18px;border:1px solid #e5e7eb;border-left:4px solid {{ $primaryColor }};border-radius:6px;background:#f9fafb"><p style="margin:0;color:#111827;font-size:15px;font-weight:bold">{{ $highlight }}</p></div>@endif
@if($details)<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px;border-top:1px solid #edf0f2;border-bottom:1px solid #edf0f2">@foreach($details as $label=>$value)<tr><td class="cell" style="width:38%;padding:12px 8px 12px 0;color:#6b7280;font-size:12px">{{ $label }}</td><td class="cell" style="padding:12px 0;color:#111827;font-size:13px;font-weight:bold">{{ $value }}</td></tr>@endforeach</table>@endif
<p style="margin:0 0 26px;color:#4b5563;font-size:14px;line-height:1.65">{{ $body }}</p><a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 20px;background:{{ $primaryColor }};border-radius:6px;color:#fff;font-size:14px;font-weight:bold;text-decoration:none">{{ $actionLabel }}</a><p style="margin:24px 0 0;color:#6b7280;font-size:12px;line-height:1.6">If you need help, contact your organization’s student support team.</p>
</td></tr>
</table><p style="margin:16px 0 0;color:#9ca3af;font-size:11px">This is an automated message. Please do not reply directly.</p>
</td></tr></table></body></html>
