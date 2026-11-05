Hi {{ $firstName }},

@if($deactivated)
Your account has been deactivated in {{ $organization->name }} by {{ $changedBy }}. You can no longer sign in or access this organization, including both the Admin and Student views.
@else
Your account has been reactivated in {{ $organization->name }} by {{ $changedBy }}. Your previous organization access has been restored.
@endif

@if(!$deactivated)
Sign in to UniOrg here:
@endif
{{ $dashboardUrl }}

@if($deactivated)
If you believe this was a mistake, please contact your organization adviser.
@else
Welcome back!
@endif

This is an automated account notification from UniOrg.
