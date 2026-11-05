Hi {{ $firstName }},

@if(($notificationType ?? 'assigned') === 'removed')
You are no longer assigned as {{ $position }} in {{ $organization->name }}. This change was made by {{ $assignedBy }}.
@else
You have been assigned as {{ $position }} in {{ $organization->name }} by {{ $assignedBy }}.
@endif

@if(($notificationType ?? 'assigned') === 'removed')
Your admin access from this position has been removed. Sign in to see your updated organization access:
@else
Your account can now open the admin view for the modules assigned to this position. Sign in to UniOrg here:
@endif
{{ $dashboardUrl }}

@if(($notificationType ?? 'assigned') !== 'removed')
After signing in, use the profile dropdown to switch between Student View and Admin View.
@endif

This is an automated account notification from UniOrg.
