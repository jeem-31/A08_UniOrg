<x-mail::message>
# Organization System Activated

A new organization system has been successfully configured and activated.

## Organization Details

- **Name**: {{ $organizationName }}
- **Acronym**: {{ $organizationAcronym }}
- **Type**: {{ $organizationType }}
- **Activation Time**: {{ $activationTimestamp }}

## Configured By

- **Name**: {{ $configuredByName }}
- **Email**: {{ $configuredByEmail }}

## Enabled Modules

The following modules have been enabled for this organization:

@foreach($enabledModules as $module)
- {{ ucfirst($module) }}
@endforeach

## Next Steps

The organization system is now active and ready for use. Members can log in and start using the enabled features.

You can monitor the organization's activity through the OSAS admin dashboard.

---

This is an automated notification from the UniOrg system.

Thanks,<br>
{{ config('app.name') }} - OSAS Team
</x-mail::message>
